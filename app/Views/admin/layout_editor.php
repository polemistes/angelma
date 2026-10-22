<?php
/**
 * Rows and boxes of a page, shown under the page settings. The rows themselves, and
 * the rows inside container boxes, are rendered by admin/layout_rows.php.
 *
 * @var array<string, mixed> $page
 * @var array<int, list<array<string, mixed>>> $rowsByParent rows grouped by parent box id, 0 for the page's own
 * @var array<int, list<array<string, mixed>>> $boxes boxes grouped by row id
 */
$rows = $rowsByParent[0] ?? [];
?>
<section class="layout" id="layout">
    <h2><?= e(t('layout_title')) ?></h2>
    <?php if ($rows === []): ?>
        <p class="muted"><?= e(t('layout_empty')) ?></p>
    <?php endif ?>

    <?= render('admin/layout_rows', [
        'page' => $page,
        'rows' => $rows,
        'rowsByParent' => $rowsByParent,
        'boxes' => $boxes,
        'depth' => 0,
    ]) ?>

    <form method="post" action="<?= e(url('/admin/pages/' . $page['id'] . '/rows')) ?>">
        <?= csrf_field() ?>
        <button type="submit" class="button"><?= e(t('row_add')) ?></button>
    </form>
</section>
