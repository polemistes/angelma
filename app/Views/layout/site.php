<?php
/**
 * Public page frame: header, menu, content, footer.
 *
 * @var string $content
 */
$frame = \Angelma\Frame::site($current_page_id ?? null);
$pageTitle = (string) ($title ?? '');
$fullTitle = !empty($is_home) || $pageTitle === '' ? $frame['site_title'] : $pageTitle . ' - ' . $frame['site_title'];
$version = require \Angelma\App::$root . '/app/version.php';
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($fullTitle) ?></title>
<?php if (!empty($meta_description)): ?>
<meta name="description" content="<?= e($meta_description) ?>">
<?php endif ?>
<?php if (!empty($feed_url)): ?>
<link rel="alternate" type="application/rss+xml" title="<?= e($feed_title ?? '') ?>" href="<?= e($feed_url) ?>">
<?php endif ?>
<link rel="stylesheet" href="<?= e(url('/assets/site.css', ['v' => $version])) ?>">
<style><?= raw($frame['scheme_css']) ?>:root{--page-width:<?= (int) $frame['page_width'] ?>px}</style>
</head>
<body>
<div class="site">
    <header class="site-header">
        <a class="site-brand" href="<?= e(url('/')) ?>">
            <?php if ($frame['logo'] !== null): ?>
                <img class="site-logo" src="<?= e(image_url($frame['logo']['filename'], 's')) ?>"
                     alt="<?= e($frame['logo']['alt']) ?>">
            <?php endif ?>
            <span class="site-title"><?= e($frame['site_title']) ?></span>
        </a>
        <?php if ($frame['tagline'] !== ''): ?>
            <p class="site-tagline"><?= e($frame['tagline']) ?></p>
        <?php endif ?>
    </header>

    <nav class="site-menu" aria-label="<?= e(t('menu_label')) ?>">
        <ul>
            <?php foreach ($frame['menu'] as $item): ?>
                <?php $active = (int) $item['id'] === $frame['current_page_id']; ?>
                <li><a href="<?= e((int) $item['id'] === $frame['home_page_id'] ? url('/') : url('/' . $item['slug'])) ?>"<?= $active ? ' class="active" aria-current="page"' : '' ?>><?= e($item['name']) ?></a></li>
            <?php endforeach ?>
        </ul>
        <div class="menu-user">
            <?php if ($frame['user'] !== null): ?>
                <span class="menu-name"><?= e($frame['user']['display_name']) ?></span>
                <form method="post" action="<?= e(url('/logout')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit"><?= e(t('menu_logout')) ?></button>
                </form>
            <?php else: ?>
                <?php $here = \Angelma\App::$request?->fullPath() ?? '/'; ?>
                <a href="<?= e(url('/login', str_starts_with($here, '/login') ? [] : ['return' => $here])) ?>"><?= e(t('menu_login')) ?></a>
            <?php endif ?>
        </div>
    </nav>

    <main class="site-content" id="content">
        <?= partial('flash') ?>
        <?= $content ?>
    </main>

    <footer class="site-footer">
        <?php if ($frame['footer_html'] !== ''): ?>
            <div class="footer-text"><?= raw($frame['footer_html']) ?></div>
        <?php endif ?>
        <p class="powered-by"><?= e(t('powered_by')) ?></p>
    </footer>
</div>
</body>
</html>
