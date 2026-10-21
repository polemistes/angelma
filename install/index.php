<?php

declare(strict_types=1);

/*
 * The browser installer. Served while data/installed.lock does not exist.
 * Steps: 1 requirements, 2 site, 3 administrator, 4 finish.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use Angelma\App;
use Angelma\Auth;
use Angelma\Installer;
use Angelma\Request;
use Angelma\Response;
use Angelma\Schemes;

$request = Request::fromGlobals();
App::$request = $request;

/** Wrap installer content in a minimal page. */
$page = static function (string $body, int $status = 200): Response {
    $html = '<!doctype html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<meta name="robots" content="noindex">'
        . '<title>' . e(t('install_title')) . '</title>'
        . '<link rel="stylesheet" href="' . e(url('/install/install.css')) . '">'
        . '<script src="' . e(url('/install/install.js')) . '" defer></script>'
        . '</head><body><main class="install"><h1>' . e(t('install_title')) . '</h1>'
        . $body . '</main></body></html>';
    return new Response($html, $status, [
        'Content-Type' => 'text/html; charset=utf-8',
        'Content-Security-Policy' => App::CSP,
        'Cache-Control' => 'no-store',
    ]);
};

if (App::installed()) {
    $page('<p class="notice">' . e(t('install_already')) . '</p>')->send();
    return;
}

App::startSession($request);
$installer = new Installer(App::$root);
$state = is_array($_SESSION['install'] ?? null) ? $_SESSION['install'] : [];

// Furthest step the visitor may open.
$allowed = 1;
if ($installer->requirementsMet()) {
    $allowed = 2;
    if (isset($state['site'])) {
        $allowed = 3;
        if (isset($state['admin'])) {
            $allowed = 4;
        }
    }
}
$step = max(1, min($allowed, (int) ($request->query('step') ?: '1')));
$errors = [];
$input = [];
$failure = '';

if ($request->isPost()) {
    if (!Auth::verifyCsrf($request)) {
        $page('<p class="error">' . e(t('error_403_text')) . '</p>', 403)->send();
        return;
    }
    $go = static fn (int $to): Response => Response::redirect(url('/install/', ['step' => $to]));

    if ($step === 1) {
        $go($installer->requirementsMet() ? 2 : 1)->send();
        return;
    }
    if ($step === 2) {
        $input = [
            'site_title' => $request->str('site_title'),
            'page_width' => $request->str('page_width'),
            'scheme' => $request->str('scheme'),
        ];
        $errors = Installer::validateSite($input);
        if ($errors === []) {
            $_SESSION['install']['site'] = $input;
            $go(3)->send();
            return;
        }
    }
    if ($step === 3) {
        $input = [
            'username' => $request->str('username'),
            'display_name' => $request->str('display_name'),
            'password' => $request->input('password'),
            'password_repeat' => $request->input('password_repeat'),
        ];
        $errors = Installer::validateAdmin($input);
        if ($errors === []) {
            $_SESSION['install']['admin'] = [
                'username' => $input['username'],
                'display_name' => $input['display_name'],
                'password_hash' => password_hash($input['password'], PASSWORD_DEFAULT),
            ];
            $go(4)->send();
            return;
        }
        unset($input['password'], $input['password_repeat']);
    }
    if ($step === 4) {
        try {
            $installer->install($state['site'], $state['admin']);
            unset($_SESSION['install']);
            $page(
                '<h2>' . e(t('install_done_title')) . '</h2>'
                . '<p>' . e(t('install_done_text')) . '</p>'
                . '<p class="warning">' . e(t('install_delete_folder')) . '</p>'
                . '<p class="actions"><a class="button" href="' . e(url('/')) . '">' . e(t('install_view_site')) . '</a> '
                . '<a class="button" href="' . e(url('/admin')) . '">' . e(t('install_open_admin')) . '</a></p>'
            )->send();
            return;
        } catch (Throwable $e) {
            error_log((string) $e);
            $failure = $e instanceof RuntimeException && !($e instanceof PDOException)
                ? $e->getMessage()
                : t('install_error_failed');
        }
    }
}

$field = static function (string $name, string $label, string $type, string $value, array $errors, string $extra = ''): string {
    $error = isset($errors[$name]) ? '<span class="field-error">' . e($errors[$name]) . '</span>' : '';
    return '<label><span>' . e($label) . '</span>'
        . '<input type="' . e($type) . '" name="' . e($name) . '" value="' . e($value) . '" ' . $extra . '>'
        . $error . '</label>';
};

$steps = '<ol class="steps">';
foreach ([1 => 'install_step_requirements', 2 => 'install_step_site', 3 => 'install_step_admin', 4 => 'install_step_finish'] as $n => $key) {
    $class = $n === $step ? 'current' : ($n < $step ? 'done' : '');
    $label = e(t($key));
    $steps .= '<li class="' . $class . '">' . ($n <= $allowed && $n !== $step
        ? '<a href="' . e(url('/install/', ['step' => $n])) . '">' . $label . '</a>' : $label) . '</li>';
}
$steps .= '</ol>';

$body = $steps . '<form method="post" action="' . e(url('/install/', ['step' => $step])) . '">' . csrf_field();

if ($step === 1) {
    $body .= '<h2>' . e(t('install_step_requirements')) . '</h2><ul class="checks">';
    foreach ($installer->requirements() as $check) {
        $class = $check['ok'] ? 'ok' : ($check['required'] ? 'fail' : 'warn');
        $body .= '<li class="' . $class . '">' . e($check['label'])
            . ($check['detail'] !== '' ? ' <small>(' . e($check['detail']) . ')</small>' : '') . '</li>';
    }
    $body .= '<li class="pending" id="rewrite-check" data-ok="' . e(t('install_rewrite_ok'))
        . '" data-fail="' . e(t('install_rewrite_fail')) . '">' . e(t('install_rewrite_checking')) . '</li></ul>';
    if ($installer->requirementsMet()) {
        $body .= '<p class="actions"><button type="submit">' . e(t('install_continue')) . '</button></p>';
    } else {
        $body .= '<p class="error">' . e(t('install_fix_requirements')) . '</p>'
            . '<p class="actions"><a class="button" href="' . e(url('/install/')) . '">' . e(t('install_check_again')) . '</a></p>';
    }
}

if ($step === 2) {
    $values = $input + ($state['site'] ?? []) + ['site_title' => '', 'page_width' => '960', 'scheme' => Schemes::DEFAULT];
    $body .= '<h2>' . e(t('install_step_site')) . '</h2>'
        . $field('site_title', t('field_site_title'), 'text', $values['site_title'], $errors, 'required maxlength="100" autofocus')
        . $field('page_width', t('field_page_width'), 'number', $values['page_width'], $errors, 'required min="600" max="1600" step="10"')
        . '<fieldset><legend>' . e(t('field_scheme')) . '</legend><div class="schemes">';
    foreach (Schemes::builtIn() as $id => $colors) {
        $checked = $values['scheme'] === $id ? ' checked' : '';
        $body .= '<label class="scheme"><input type="radio" name="scheme" value="' . e($id) . '"' . $checked . '>'
            . '<span class="swatch">';
        foreach (['outside_bg', 'page_bg', 'menu_bg', 'link', 'text'] as $key) {
            $body .= '<i style="background:' . e($colors[$key]) . '"></i>';
        }
        $body .= '</span> ' . e(t('scheme_' . $id)) . '</label>';
    }
    $body .= '</div>' . (isset($errors['scheme']) ? '<span class="field-error">' . e($errors['scheme']) . '</span>' : '')
        . '</fieldset><p class="actions"><button type="submit">' . e(t('install_continue')) . '</button></p>';
}

if ($step === 3) {
    $values = $input + ($state['admin'] ?? []) + ['username' => '', 'display_name' => ''];
    $body .= '<h2>' . e(t('install_step_admin')) . '</h2>'
        . $field('username', t('field_username'), 'text', $values['username'], $errors, 'required autocomplete="username" autofocus')
        . $field('display_name', t('field_display_name'), 'text', $values['display_name'], $errors, 'required maxlength="80"')
        . $field('password', t('field_password'), 'password', '', $errors, 'required autocomplete="new-password" minlength="' . Installer::MIN_PASSWORD . '"')
        . $field('password_repeat', t('field_password_repeat'), 'password', '', $errors, 'required autocomplete="new-password"')
        . '<p class="hint">' . e(t('password_hint', ['min' => Installer::MIN_PASSWORD])) . '</p>'
        . '<p class="actions"><button type="submit">' . e(t('install_continue')) . '</button></p>';
}

if ($step === 4) {
    $site = $state['site'];
    $admin = $state['admin'];
    $body .= '<h2>' . e(t('install_step_finish')) . '</h2>'
        . ($failure !== '' ? '<p class="error">' . e($failure) . '</p>' : '')
        . '<dl class="summary">'
        . '<dt>' . e(t('field_site_title')) . '</dt><dd>' . e($site['site_title']) . '</dd>'
        . '<dt>' . e(t('field_page_width')) . '</dt><dd>' . e($site['page_width']) . ' px</dd>'
        . '<dt>' . e(t('field_scheme')) . '</dt><dd>' . e(t('scheme_' . $site['scheme'])) . '</dd>'
        . '<dt>' . e(t('field_username')) . '</dt><dd>' . e($admin['username']) . '</dd>'
        . '<dt>' . e(t('field_display_name')) . '</dt><dd>' . e($admin['display_name']) . '</dd>'
        . '</dl><p>' . e(t('install_finish_text')) . '</p>'
        . '<p class="actions"><button type="submit">' . e(t('install_button')) . '</button></p>';
}

$body .= '</form>';
$page($body, $errors !== [] || $failure !== '' ? 422 : 200)->send();
