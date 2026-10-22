<?php

declare(strict_types=1);

namespace Angelma\Controllers\Admin;

use Angelma\App;
use Angelma\Auth;
use Angelma\Repos\Images as ImagesRepo;
use Angelma\Repos\Pages;
use Angelma\Repos\Settings;
use Angelma\Request;
use Angelma\Response;
use Angelma\Schemes;

/**
 * Site settings and the menu. Administrators only.
 */
final class SettingsController
{
    public const MAX_TITLE = 100;
    public const MAX_TAGLINE = 200;
    public const MAX_FOOTER = 10000;
    public const MAX_DATE_FORMAT = 40;

    public function show(Request $request): Response
    {
        Auth::requireRole('admin');
        $values = App::settings() + App::DEFAULT_SETTINGS;
        $values['custom'] = self::customColors($values);
        return $this->form($values);
    }

    public function save(Request $request): Response
    {
        Auth::requireRole('admin');
        $values = [
            'site_title' => $request->str('site_title'),
            'site_tagline' => $request->str('site_tagline'),
            'logo_image_id' => (string) $request->int('logo_image_id'),
            'page_width' => $request->str('page_width'),
            'scheme' => $request->str('scheme'),
            'footer_markdown' => trim($request->input('footer_markdown')),
            'home_page_id' => (string) $request->int('home_page_id'),
            'timezone' => $request->str('timezone'),
            'date_format' => $request->str('date_format'),
            'moderate_anonymous' => $request->bool('moderate_anonymous') ? '1' : '0',
        ];
        $custom = [];
        $posted = is_array($request->post['custom'] ?? null) ? $request->post['custom'] : [];
        foreach (Schemes::KEYS as $key) {
            $custom[$key] = is_string($posted[$key] ?? null) ? strtolower(trim($posted[$key])) : '';
        }

        // "Start from current scheme" without JavaScript: copy and show the form again.
        if ($request->str('copy_scheme') !== '') {
            $source = Schemes::get($values['scheme']) ?? Schemes::resolve(App::setting('scheme'), App::setting('custom_scheme'));
            $values['custom'] = $source;
            $values['scheme'] = 'custom';
            flash('success', t('settings_scheme_copied'));
            return $this->form($values);
        }

        $errors = [];
        if ($values['site_title'] === '' || mb_strlen($values['site_title']) > self::MAX_TITLE) {
            $errors['site_title'] = t('install_error_title');
        }
        if (mb_strlen($values['site_tagline']) > self::MAX_TAGLINE) {
            $errors['site_tagline'] = t('settings_error_tagline', ['max' => self::MAX_TAGLINE]);
        }
        if ($values['logo_image_id'] === '0') {
            $values['logo_image_id'] = '';
        } elseif ((new ImagesRepo())->find((int) $values['logo_image_id']) === null) {
            $errors['logo_image_id'] = t('box_error_image');
        }
        if (!preg_match('/^\d+$/', $values['page_width']) || (int) $values['page_width'] < 600 || (int) $values['page_width'] > 1600) {
            $errors['page_width'] = t('error_page_width');
        }
        if ($values['scheme'] !== 'custom' && Schemes::get($values['scheme']) === null) {
            $errors['scheme'] = t('install_error_scheme');
        }
        foreach ($custom as $key => $color) {
            if (!Schemes::validColor($color)) {
                $errors['custom'] = t('settings_error_color');
            }
        }
        if (mb_strlen($values['footer_markdown']) > self::MAX_FOOTER) {
            $errors['footer_markdown'] = t('box_error_markdown_long', ['max' => self::MAX_FOOTER]);
        }
        if ((new Pages())->find((int) $values['home_page_id']) === null) {
            $errors['home_page_id'] = t('settings_error_home');
        }
        if (!in_array($values['timezone'], \DateTimeZone::listIdentifiers(), true)) {
            $errors['timezone'] = t('settings_error_timezone');
        }
        if ($values['date_format'] === '' || mb_strlen($values['date_format']) > self::MAX_DATE_FORMAT) {
            $errors['date_format'] = t('settings_error_date_format', ['max' => self::MAX_DATE_FORMAT]);
        }

        if ($errors !== []) {
            $values['custom'] = $custom;
            return $this->form($values, $errors, 422);
        }

        $values['custom_scheme'] = (string) json_encode(Schemes::normalize($custom));
        $values['page_width'] = (string) (int) $values['page_width'];
        (new Settings())->setMany($values);
        App::forgetSettings();
        flash('success', t('settings_saved'));
        return redirect('/admin/settings');
    }

    /** Save which pages are shown in the menu. */
    public function menu(Request $request): Response
    {
        Auth::requireRole('admin');
        $shown = is_array($request->post['show'] ?? null) ? array_map('intval', $request->post['show']) : [];
        $pages = new Pages();
        foreach ($pages->all() as $page) {
            $pages->setShowInMenu((int) $page['id'], in_array((int) $page['id'], $shown, true));
        }
        flash('success', t('settings_menu_saved'));
        return redirect('/admin/settings#menu');
    }

    public function moveMenu(Request $request, int $id): Response
    {
        Auth::requireRole('admin');
        if ((new Pages())->find($id) === null) {
            abort(404);
        }
        (new Pages())->moveInMenu($id, $request->str('direction') === 'up' ? 'up' : 'down');
        return redirect('/admin/settings#menu');
    }

    /**
     * @param array<string, string> $values
     * @return array<string, string>
     */
    private static function customColors(array $values): array
    {
        $custom = json_decode($values['custom_scheme'] ?? '', true);
        if (is_array($custom) && $custom !== []) {
            return Schemes::normalize($custom);
        }
        return Schemes::resolve($values['scheme'] ?? Schemes::DEFAULT);
    }

    /**
     * @param array<string, mixed> $values
     * @param array<string, string> $errors
     */
    private function form(array $values, array $errors = [], int $status = 200): Response
    {
        $logoId = (int) ($values['logo_image_id'] ?? 0);
        $images = new ImagesRepo();
        return view('admin/settings', [
            'title' => t('nav_settings'),
            'nav' => 'settings',
            'values' => $values,
            'errors' => $errors,
            'schemes' => Schemes::builtIn(),
            'pages' => (new Pages())->all(),
            'pickerImages' => $images->all('', ImagesController::PICKER_LIMIT),
            'logo' => $logoId > 0 ? $images->find($logoId) : null,
            'timezones' => \DateTimeZone::listIdentifiers(),
        ], $status);
    }
}
