<?php

declare(strict_types=1);

namespace Angelma\Controllers\Admin;

use Angelma\App;
use Angelma\Auth;
use Angelma\Markdown;
use Angelma\Repos\Settings;
use Angelma\Request;
use Angelma\Response;
use Angelma\Updater;

/**
 * The Update screen: check GitHub for a newer release, install it, restore the previous
 * version (PLAN.md section 6.16). Administrators only.
 */
final class UpdateController
{
    public function show(Request $request): Response
    {
        Auth::requireRole('admin');
        $updater = Updater::fromApp();
        $check = $updater->check();
        return view('admin/update', [
            'title' => t('nav_update'),
            'nav' => 'update',
            'current' => $updater->currentVersion(),
            'check' => $check,
            'notesHtml' => Markdown::render((string) ($check['notes'] ?? '')),
            'preflight' => $updater->preflight(),
            'previous' => $updater->previousVersion(),
            'releasePage' => $updater->releasePageUrl(),
        ]);
    }

    public function check(Request $request): Response
    {
        Auth::requireRole('admin');
        $result = Updater::fromApp()->check(true);
        flash(isset($result['error']) ? 'error' : 'success', isset($result['error'])
            ? t('update_error', ['error' => (string) $result['error']])
            : t('update_checked'));
        return redirect('/admin/update');
    }

    public function install(Request $request): Response
    {
        Auth::requireRole('admin');
        $updater = Updater::fromApp();
        $check = $updater->check();
        if (empty($check['newer'])) {
            flash('error', t('update_nothing'));
            return redirect('/admin/update');
        }
        foreach ($updater->preflight() as $item) {
            if (!$item['ok']) {
                flash('error', t('update_preflight_failed'));
                return redirect('/admin/update');
            }
        }
        try {
            $summary = $updater->install($check);
        } catch (\Throwable $e) {
            error_log((string) $e);
            flash('error', t('update_failed', ['detail' => $e->getMessage()]));
            return redirect('/admin/update');
        }
        self::forgetCheck();
        flash('success', t('update_done', ['version' => (string) $summary['version'], 'files' => (int) $summary['copied']]));
        return redirect('/admin/update');
    }

    public function rollback(Request $request): Response
    {
        Auth::requireRole('admin');
        try {
            $summary = Updater::fromApp()->rollback();
        } catch (\Throwable $e) {
            error_log((string) $e);
            flash('error', t('update_failed', ['detail' => $e->getMessage()]));
            return redirect('/admin/update');
        }
        self::forgetCheck();
        flash('success', t('update_restored', ['version' => (string) $summary['version']]));
        return redirect('/admin/update');
    }

    /** The cached check was made by the old version: drop it so the next screen checks again. */
    private static function forgetCheck(): void
    {
        (new Settings())->set(Updater::SETTING, '');
        App::forgetSettings();
    }
}
