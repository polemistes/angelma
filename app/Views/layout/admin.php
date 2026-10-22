<?php
/**
 * Admin frame: navigation on the left, the screen on the right.
 *
 * @var string $content
 */
use Angelma\App;
use Angelma\Auth;

$user = Auth::user();
$version = require App::$root . '/app/version.php';
$current = $nav ?? '';
$items = [
    ['dashboard', '/admin', 'nav_dashboard', 'editor'],
    ['pages', '/admin/pages', 'nav_pages', 'editor'],
    ['images', '/admin/images', 'nav_images', 'editor'],
    ['blogs', '/admin/blogs', 'nav_blogs', 'editor'],
    ['comments', '/admin/comments', 'nav_comments', 'editor'],
    ['users', '/admin/users', 'nav_users', 'admin'],
    ['settings', '/admin/settings', 'nav_settings', 'admin'],
    ['backup', '/admin/backup', 'nav_backup', 'admin'],
    ['account', '/admin/account', 'nav_account', 'user'],
];
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<meta name="csrf-token" content="<?= e(Auth::csrfToken()) ?>">
<meta name="base-path" content="<?= e(App::$base) ?>">
<title><?= e(($title ?? t('nav_dashboard')) . ' - ' . App::setting('site_title') . ' ' . t('admin_suffix')) ?></title>
<link rel="stylesheet" href="<?= e(url('/assets/admin.css', ['v' => $version])) ?>">
<script src="<?= e(url('/assets/admin.js', ['v' => $version])) ?>" defer></script>
</head>
<body class="admin">
<div class="admin-shell">
    <aside class="admin-side">
        <a class="admin-brand" href="<?= e(url('/admin')) ?>"><?= e(App::setting('site_title')) ?></a>
        <nav class="admin-nav" aria-label="<?= e(t('admin_nav_label')) ?>">
            <ul>
                <?php foreach ($items as [$key, $href, $label, $role]): ?>
                    <?php if (Auth::can($role)): ?>
                        <li><a href="<?= e(url($href)) ?>"<?= $key === $current ? ' class="active" aria-current="page"' : '' ?>><?= e(t($label)) ?></a></li>
                    <?php endif ?>
                <?php endforeach ?>
                <li class="nav-sep"><a href="<?= e(url('/')) ?>"><?= e(t('nav_view_site')) ?></a></li>
                <li>
                    <form method="post" action="<?= e(url('/logout')) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="link-button"><?= e(t('nav_logout')) ?></button>
                    </form>
                </li>
            </ul>
        </nav>
        <?php if ($user !== null): ?>
            <p class="admin-user"><?= e(t('admin_logged_in_as', ['name' => (string) $user['display_name']])) ?><br>
                <small><?= e(t('role_' . $user['role'])) ?></small></p>
        <?php endif ?>
    </aside>
    <main class="admin-main" id="content">
        <?php if (App::maintenance()): ?>
            <div class="flash flash-warning maintenance-bar"><?= e(t('maintenance_bar')) ?> <a href="<?= e(url('/admin/settings')) ?>#maintenance"><?= e(t('maintenance_bar_link')) ?></a></div>
        <?php endif ?>
        <?php if (isset($title)): ?>
            <h1><?= e($title) ?></h1>
        <?php endif ?>
        <?= partial('flash') ?>
        <?= $content ?>
    </main>
</div>
</body>
</html>
