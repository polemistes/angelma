<?php
/**
 * A page: its rows of boxes. Rows inside container boxes are rendered by the same
 * partial, recursively.
 *
 * @var array<string, mixed> $page
 * @var array<int, list<array<string, mixed>>> $rowsByParent rows grouped by parent box id, 0 for the page's own
 * @var array<int, list<array<string, mixed>>> $boxes boxes grouped by row id
 */
?>
<h1 class="visually-hidden"><?= e($page['title']) ?></h1>
<?= partial('rows', ['rows' => $rowsByParent[0] ?? [], 'rowsByParent' => $rowsByParent, 'boxes' => $boxes]) ?>
