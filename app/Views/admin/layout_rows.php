<?php
/**
 * Rows with their boxes in the layout editor: the page's own rows, or the rows inside
 * a container box, which renders this template again (PLAN.md section 6.13).
 *
 * @var array<string, mixed> $page
 * @var list<array<string, mixed>> $rows the rows to show
 * @var array<int, list<array<string, mixed>>> $rowsByParent all rows of the page by parent box id
 * @var array<int, list<array<string, mixed>>> $boxes boxes grouped by row id
 * @var int $depth 0 for the page's own rows, 1 inside a container, and so on
 * @var list<array<string, mixed>> $workbenchBoxes boxes that can be moved here from the workbench
 */
use Angelma\Repos\Boxes;
use Angelma\Repos\Rows;

$rowCount = count($rows);
// Containers may only be added where they do not nest too deep; the server checks too.
$types = $depth < Boxes::MAX_DEPTH ? Boxes::TYPES : array_values(array_diff(Boxes::TYPES, ['container']));
?>
<?php foreach ($rows as $i => $row): ?>
    <?php
    $rowBoxes = $boxes[(int) $row['id']] ?? [];
    $used = array_sum(array_column($rowBoxes, 'width'));
    $free = 12 - $used;
    $boxCount = count($rowBoxes);
    // Show the alignment: the first box starts where it would on the public page.
    $start = match ($row['alignment']) {
        'right' => 1 + $free,
        'center' => 1 + intdiv($free, 2),
        default => 1,
    };
    ?>
    <div class="layout-row panel" id="row-<?= (int) $row['id'] ?>">
        <div class="row-toolbar">
            <strong><?= e(t('row_label', ['n' => $i + 1])) ?></strong>
            <span class="muted small"><?= e(t('row_used', ['used' => $used])) ?></span>

            <form method="post" action="<?= e(url('/admin/rows/' . $row['id'] . '/alignment')) ?>" class="inline align-form">
                <?= csrf_field() ?>
                <label class="small"><?= e(t('row_alignment')) ?>
                    <select name="alignment" data-autosubmit>
                        <?php foreach (Rows::ALIGNMENTS as $alignment): ?>
                            <option value="<?= e($alignment) ?>"<?= $row['alignment'] === $alignment ? ' selected' : '' ?>><?= e(t('align_' . $alignment)) ?></option>
                        <?php endforeach ?>
                    </select>
                </label>
                <button type="submit" class="button small secondary"><?= e(t('set')) ?></button>
            </form>

            <span class="toolbar-spacer"></span>
            <?= partial('move_buttons', [
                'action' => '/admin/rows/' . $row['id'] . '/move',
                'first' => $i === 0,
                'last' => $i === $rowCount - 1,
                'up' => t('move_up'),
                'down' => t('move_down'),
                'upIcon' => '↑',
                'downIcon' => '↓',
            ]) ?>
            <form method="post" action="<?= e(url('/admin/rows/' . $row['id'] . '/delete')) ?>" class="inline"
                  data-confirm="<?= e(t('row_confirm_delete')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="button small danger"><?= e(t('row_delete')) ?></button>
            </form>
        </div>

        <div class="row-boxes">
            <?php foreach ($rowBoxes as $j => $box): ?>
                <?php $isContainer = $box['type'] === 'container'; ?>
                <div class="layout-box w-<?= (int) $box['width'] ?><?= $isContainer ? ' is-container' : '' ?>" id="box-<?= (int) $box['id'] ?>"<?= $j === 0 && $start > 1 ? ' style="grid-column:' . (int) $start . ' / span ' . (int) $box['width'] . '"' : '' ?>>
                    <div class="box-head">
                        <strong><?= e(t('box_type_' . $box['type'])) ?></strong>
                        <span class="muted small"><?= e(t('box_width_of', ['width' => (int) $box['width']])) ?><?php if ($box['height'] !== null): ?>, <?= e(t('box_height_value', ['height' => (int) $box['height']])) ?><?php endif ?></span>
                    </div>
                    <?php if ($isContainer): ?>
                        <div class="container-rows">
                            <?php if (empty($rowsByParent[(int) $box['id']])): ?>
                                <p class="muted small"><?= e(t('container_empty')) ?></p>
                            <?php endif ?>
                            <?= render('admin/layout_rows', [
                                'page' => $page,
                                'rows' => $rowsByParent[(int) $box['id']] ?? [],
                                'rowsByParent' => $rowsByParent,
                                'boxes' => $boxes,
                                'workbenchBoxes' => $workbenchBoxes,
                                'depth' => $depth + 1,
                            ]) ?>
                            <form method="post" action="<?= e(url('/admin/boxes/' . $box['id'] . '/rows')) ?>" class="inline">
                                <?= csrf_field() ?>
                                <button type="submit" class="button small secondary"><?= e(t('row_add_in_container')) ?></button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="box-preview"><?= partial('box_summary', ['box' => $box]) ?></div>
                    <?php endif ?>
                    <div class="actions">
                        <?= partial('move_buttons', [
                            'action' => '/admin/boxes/' . $box['id'] . '/move',
                            'first' => $j === 0,
                            'last' => $j === $boxCount - 1,
                            'up' => t('move_left'),
                            'down' => t('move_right'),
                            'upIcon' => '←',
                            'downIcon' => '→',
                        ]) ?>
                        <a class="button small" href="<?= e(url('/admin/boxes/' . $box['id'])) ?>"><?= e(t('edit')) ?></a>
                        <a class="button small secondary" href="<?= e(url('/admin/boxes/' . $box['id'] . '/move-to')) ?>"><?= e(t('box_move')) ?></a>
                        <?php if ($isContainer): ?>
                            <a class="button small danger" href="<?= e(url('/admin/boxes/' . $box['id'] . '/delete')) ?>"><?= e(t('box_delete')) ?></a>
                        <?php else: ?>
                            <form method="post" action="<?= e(url('/admin/boxes/' . $box['id'] . '/delete')) ?>" class="inline"
                                  data-confirm="<?= e(t('box_confirm_delete')) ?>">
                                <?= csrf_field() ?>
                                <button type="submit" class="button small danger"><?= e(t('delete')) ?></button>
                            </form>
                        <?php endif ?>
                    </div>
                </div>
            <?php endforeach ?>
        </div>

        <?php if ($free >= min(Boxes::WIDTHS)): ?>
            <form method="post" action="<?= e(url('/admin/rows/' . $row['id'] . '/boxes')) ?>" class="add-box">
                <?= csrf_field() ?>
                <label class="small"><?= e(t('box_type')) ?>
                    <select name="type">
                        <?php foreach ($types as $type): ?>
                            <option value="<?= e($type) ?>"><?= e(t('box_type_' . $type)) ?></option>
                        <?php endforeach ?>
                    </select>
                </label>
                <label class="small"><?= e(t('box_width')) ?>
                    <select name="width">
                        <?php foreach (Boxes::WIDTHS as $width): ?>
                            <?php if ($width <= $free): ?>
                                <option value="<?= $width ?>"<?= $width === $free ? ' selected' : '' ?>><?= e(t('width_option_' . $width)) ?></option>
                            <?php endif ?>
                        <?php endforeach ?>
                    </select>
                </label>
                <button type="submit" class="button small"><?= e(t('box_add')) ?></button>
            </form>
            <?php $fitting = array_values(array_filter($workbenchBoxes, static fn (array $b): bool => (int) $b['width'] <= $free)); ?>
            <?php if ($fitting !== []): ?>
                <form method="post" action="<?= e(url('/admin/rows/' . $row['id'] . '/boxes')) ?>" class="add-box move-here">
                    <?= csrf_field() ?>
                    <label class="small"><?= e(t('box_move_from_workbench')) ?>
                        <select name="box_id">
                            <?php foreach ($fitting as $b): ?>
                                <option value="<?= (int) $b['id'] ?>"><?= e(box_label($b)) ?> (<?= e(t('box_width_of', ['width' => (int) $b['width']])) ?>)</option>
                            <?php endforeach ?>
                        </select>
                    </label>
                    <button type="submit" class="button small secondary"><?= e(t('box_move_here')) ?></button>
                </form>
            <?php endif ?>
        <?php else: ?>
            <p class="muted small"><?= e(t('row_full')) ?></p>
        <?php endif ?>
    </div>
<?php endforeach ?>
