<?php
/**
 * @var array<string, mixed> $page
 * @var array<string, mixed> $input
 * @var array<string, string> $errors
 * @var bool $isHome
 * @var bool $isWorkbench
 * @var array<int, list<array<string, mixed>>> $rowsByParent
 * @var array<int, list<array<string, mixed>>> $boxes
 * @var list<array<string, mixed>> $workbenchBoxes boxes that can be moved here from the workbench
 */
?>
<p class="actions">
    <a class="button secondary" href="<?= e($isHome ? url('/') : url('/' . $page['slug'])) ?>"><?= e(t('page_view')) ?></a>
    <a class="button secondary" href="<?= e(url('/admin/pages')) ?>"><?= e(t('page_back_to_list')) ?></a>
</p>

<?php if ($isWorkbench): ?>
<p class="hint workbench-hint"><?= e(t('workbench_hint')) ?></p>
<?php else: ?>
<details class="panel" id="page-settings"<?= $errors !== [] ? ' open' : '' ?>>
    <summary><h2><?= e(t('page_settings')) ?></h2></summary>
    <form method="post" action="<?= e(url('/admin/pages/' . $page['id'])) ?>" class="form">
        <?= csrf_field() ?>
        <?= partial('page_fields', ['input' => $input, 'errors' => $errors, 'prefix' => 'edit']) ?>
        <p class="actions"><button type="submit" class="button"><?= e(t('save')) ?></button></p>
    </form>
    <?php if ($isHome): ?>
        <p class="muted small"><?= e(t('page_is_home')) ?></p>
    <?php else: ?>
        <form method="post" action="<?= e(url('/admin/pages/' . $page['id'] . '/delete')) ?>"
              data-confirm="<?= e(t('page_confirm_delete', ['name' => (string) $page['name']])) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="button danger"><?= e(t('page_delete')) ?></button>
        </form>
    <?php endif ?>
</details>
<?php endif ?>

<?= render('admin/layout_editor', ['page' => $page, 'rowsByParent' => $rowsByParent, 'boxes' => $boxes, 'workbenchBoxes' => $workbenchBoxes]) ?>
