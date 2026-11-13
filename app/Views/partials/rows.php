<?php
/**
 * A list of rows with their boxes: the page's own rows, or the rows inside a container
 * box, which renders this partial again (PLAN.md section 6.13).
 *
 * @var list<array<string, mixed>> $rows the rows to show
 * @var array<int, list<array<string, mixed>>> $rowsByParent all rows of the page by parent box id
 * @var array<int, list<array<string, mixed>>> $boxes boxes grouped by row id
 */
// "Edit" links for editors (PLAN.md section 6.17). A container shows nothing of its own.
$editLinks = edit_links();
$here = \Angelma\App::$request?->path ?? '/';
?>
<?php foreach ($rows as $row): ?>
    <?php
    $rowBoxes = $boxes[(int) $row['id']] ?? [];
    if ($rowBoxes === []) {
        continue;
    }
    $sum = min(12, array_sum(array_column($rowBoxes, 'width')));
    ?>
    <div class="row cols-<?= (int) $sum ?> align-<?= e($row['alignment']) ?>">
        <?php foreach ($rowBoxes as $box): ?>
            <?php $height = $box['height'] !== null ? (int) $box['height'] : 0; ?>
            <?php $edit = $editLinks && $box['type'] !== 'container'; ?>
            <div class="box box-<?= e($box['type']) ?> w-<?= (int) $box['width'] ?><?= $height > 0 ? ' fixed' : '' ?>"<?= $edit ? ' id="box-' . (int) $box['id'] . '"' : '' ?><?= $height > 0 ? ' style="--h:' . $height . 'px"' : '' ?>>
                <?php if ($edit): ?>
                    <a class="edit-link" href="<?= e(url('/admin/boxes/' . (int) $box['id'], ['return' => $here . '#box-' . (int) $box['id']])) ?>"><?= e(t('edit')) ?></a>
                <?php endif ?>
                <?= partial('box_' . $box['type'], [
                    'box' => $box,
                    'content' => $box['content'],
                    'rowsByParent' => $rowsByParent,
                    'boxes' => $boxes,
                ]) ?>
            </div>
        <?php endforeach ?>
    </div>
<?php endforeach ?>
