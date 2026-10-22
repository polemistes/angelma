<?php
/**
 * Delete a container: with its boxes, or keeping them on the workbench.
 *
 * @var array<string, mixed> $box
 * @var array<string, mixed> $page
 * @var int $count boxes inside the container, at any depth
 */
$back = url('/admin/pages/' . $page['id']) . '#box-' . (int) $box['id'];
$action = url('/admin/boxes/' . $box['id'] . '/delete');
?>
<section class="panel">
    <p><?= e(t('box_delete_question', ['label' => box_label($box), 'page' => (string) $page['name'], 'count' => $count])) ?></p>
    <?php if ($count > 0): ?>
        <p class="hint"><?= e(t('box_delete_explain')) ?></p>
    <?php endif ?>
    <p class="actions">
        <?php if ($count > 0): ?>
            <form method="post" action="<?= e($action) ?>" class="inline">
                <?= csrf_field() ?>
                <input type="hidden" name="keep" value="1">
                <button type="submit" class="button"><?= e(t('box_delete_keep_boxes')) ?></button>
            </form>
        <?php endif ?>
        <form method="post" action="<?= e($action) ?>" class="inline">
            <?= csrf_field() ?>
            <button type="submit" class="button danger"><?= e(t($count > 0 ? 'box_delete_with_boxes' : 'box_delete_empty')) ?></button>
        </form>
        <a class="button secondary" href="<?= e($back) ?>"><?= e(t('cancel')) ?></a>
    </p>
</section>
