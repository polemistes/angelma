<?php
/**
 * Image box: full box width, srcset with the three sizes, optional caption.
 * With a fixed box height the image is cropped (cover) or letterboxed (contain).
 *
 * @var array<string, mixed> $box
 * @var array<string, mixed> $content
 */
$image = !empty($content['image_id']) ? (new \Angelma\Repos\Images())->find((int) $content['image_id']) : null;
if ($image === null) {
    return;
}
$file = (string) $image['filename'];
$w = (int) $image['width'];
$sources = [];
foreach (['s' => \Angelma\Images::SMALL, 'm' => \Angelma\Images::MEDIUM, 'l' => \Angelma\Images::LARGE] as $size => $max) {
    $sources[min($w, $max)] = image_url($file, $size);
}
$srcset = implode(', ', array_map(static fn (int $width, string $src): string => $src . ' ' . $width . 'w', array_keys($sources), $sources));
$pageWidth = max(600, min(1600, (int) \Angelma\App::setting('page_width', '960')));
$slot = (int) round($pageWidth * (int) $box['width'] / 12);
$alt = trim((string) ($content['alt'] ?? '')) !== '' ? (string) $content['alt'] : (string) $image['alt'];
$caption = trim((string) ($content['caption'] ?? ''));
$fit = ($content['fit'] ?? 'cover') === 'contain' ? 'contain' : 'cover';
?>
<figure class="image fit-<?= e($fit) ?>">
    <img src="<?= e(image_url($file, 'm')) ?>" srcset="<?= e($srcset) ?>"
         sizes="(max-width: 699px) 100vw, <?= $slot ?>px"
         width="<?= $w ?>" height="<?= (int) $image['height'] ?>" alt="<?= e($alt) ?>" loading="lazy">
    <?php if ($caption !== ''): ?>
        <figcaption><?= e($caption) ?></figcaption>
    <?php endif ?>
</figure>
