<?php
/**
 * Video or map: raw HTML from an administrator, an iframe for a known provider, or a link.
 *
 * @var array<string, mixed> $content
 */
$html = (string) ($content['html'] ?? '');
$url = (string) ($content['url'] ?? '');
if ($html !== ''): ?>
    <div class="embed-html"><?= raw($html) ?></div>
<?php elseif (($embed = \Angelma\Embed::parse($url)) !== null): ?>
    <div class="embed embed-<?= e($embed['ratio']) ?>">
        <iframe src="<?= e($embed['src']) ?>" title="<?= e(t('embed_title_' . $embed['provider'])) ?>"
                loading="lazy" sandbox="<?= e(\Angelma\Embed::SANDBOX) ?>"
                allow="fullscreen; picture-in-picture" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
    </div>
<?php elseif (\Angelma\Embed::isHttpUrl($url)): ?>
    <p class="embed-link"><a href="<?= e($url) ?>" rel="noopener"><?= e($url) ?></a></p>
<?php endif;
