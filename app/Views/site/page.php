<?php
/**
 * A page: its rows of boxes.
 *
 * @var array<string, mixed> $page
 * @var list<array<string, mixed>> $rows
 * @var array<int, list<array<string, mixed>>> $boxes boxes grouped by row id
 */
?>
<h1 class="visually-hidden"><?= e($page['title']) ?></h1>
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
            <div class="box box-<?= e($box['type']) ?> w-<?= (int) $box['width'] ?><?= $height > 0 ? ' fixed' : '' ?>"<?= $height > 0 ? ' style="--h:' . $height . 'px"' : '' ?>>
                <?= partial('box_' . $box['type'], ['box' => $box, 'content' => $box['content']]) ?>
            </div>
        <?php endforeach ?>
    </div>
<?php endforeach ?>
