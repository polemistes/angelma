<?php
/**
 * Previous / next links and page numbers.
 *
 * @var int $page
 * @var int $pages
 * @var string $path
 */
if ($pages <= 1) {
    return;
}
$link = static fn (int $n): string => url($path, $n > 1 ? ['page' => $n] : []);
?>
<nav class="pagination" aria-label="<?= e(t('pagination')) ?>">
    <?php if ($page > 1): ?>
        <a href="<?= e($link($page - 1)) ?>" rel="prev"><?= e(t('pagination_newer')) ?></a>
    <?php endif ?>
    <?php for ($n = 1; $n <= $pages; $n++): ?>
        <?php if ($n === $page): ?>
            <span aria-current="page"><?= $n ?></span>
        <?php else: ?>
            <a href="<?= e($link($n)) ?>"><?= $n ?></a>
        <?php endif ?>
    <?php endfor ?>
    <?php if ($page < $pages): ?>
        <a href="<?= e($link($page + 1)) ?>" rel="next"><?= e(t('pagination_older')) ?></a>
    <?php endif ?>
</nav>
