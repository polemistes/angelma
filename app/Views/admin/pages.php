<?php
/**
 * @var list<array<string, mixed>> $pages
 * @var int $homeId
 * @var bool $canOrder administrators may change the menu order
 * @var int|null $workbenchBoxes boxes on the workbench, null while it does not exist yet
 * @var array<string, string> $input
 * @var array<string, string> $errors
 */
$pageCount = count($pages);
?>
<p class="actions workbench-link">
    <a class="button secondary" href="<?= e(url('/admin/workbench')) ?>"><?= e(t('workbench_name')) ?><?php if ($workbenchBoxes !== null): ?> (<?= e(t('workbench_boxes', ['count' => $workbenchBoxes])) ?>)<?php endif ?></a>
    <span class="muted small"><?= e(t('workbench_lead')) ?></span>
</p>
<?php if ($pages === []): ?>
    <p class="muted"><?= e(t('pages_none')) ?></p>
<?php else: ?>
    <?php if ($canOrder): ?>
        <p class="hint"><?= e(t('pages_order_hint')) ?></p>
    <?php endif ?>
    <div class="table-wrap">
        <table class="list">
            <thead>
            <tr>
                <?php if ($canOrder): ?><th><?= e(t('settings_order')) ?></th><?php endif ?>
                <th><?= e(t('page_field_name')) ?></th>
                <th><?= e(t('field_address')) ?></th>
                <th><?= e(t('field_owner')) ?></th>
                <th><?= e(t('pages_in_menu')) ?></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($pages as $i => $page): ?>
                <?php $isHome = (int) $page['id'] === $homeId; ?>
                <tr>
                    <?php if ($canOrder): ?>
                        <td class="order-cell">
                            <?= partial('move_buttons', [
                                'action' => '/admin/pages/' . $page['id'] . '/move',
                                'first' => $i === 0,
                                'last' => $i === $pageCount - 1,
                                'up' => t('move_up'),
                                'down' => t('move_down'),
                                'upIcon' => '↑',
                                'downIcon' => '↓',
                            ]) ?>
                        </td>
                    <?php endif ?>
                    <td>
                        <a href="<?= e(url('/admin/pages/' . $page['id'])) ?>"><?= e($page['name']) ?></a>
                        <?php if ($isHome): ?><span class="badge ok"><?= e(t('pages_home')) ?></span><?php endif ?>
                    </td>
                    <td><a href="<?= e($isHome ? url('/') : url('/' . $page['slug'])) ?>">/<?= e($isHome ? '' : $page['slug']) ?></a></td>
                    <td><?= e($page['owner_name']) ?></td>
                    <td><?= e((int) $page['show_in_menu'] === 1 ? t('yes') : t('no')) ?></td>
                    <td class="actions-cell">
                        <a class="button small secondary" href="<?= e(url('/admin/pages/' . $page['id'])) ?>"><?= e(t('edit')) ?></a>
                        <?php if (!$isHome): ?>
                            <form method="post" action="<?= e(url('/admin/pages/' . $page['id'] . '/delete')) ?>"
                                  data-confirm="<?= e(t('page_confirm_delete', ['name' => (string) $page['name']])) ?>">
                                <?= csrf_field() ?>
                                <button type="submit" class="button small danger"><?= e(t('delete')) ?></button>
                            </form>
                        <?php endif ?>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>

<section class="panel" id="new-page">
    <h2><?= e(t('pages_new')) ?></h2>
    <form method="post" action="<?= e(url('/admin/pages')) ?>" class="form">
        <?= csrf_field() ?>
        <?= partial('page_fields', ['input' => $input, 'errors' => $errors, 'prefix' => 'new']) ?>
        <p class="actions"><button type="submit" class="button"><?= e(t('pages_create')) ?></button></p>
    </form>
</section>
