<?php
/**
 * Container box: shows the rows inside it (PLAN.md section 6.13).
 *
 * @var array<string, mixed> $box
 * @var array<int, list<array<string, mixed>>> $rowsByParent all rows of the page by parent box id
 * @var array<int, list<array<string, mixed>>> $boxes boxes grouped by row id
 */
echo partial('rows', [
    'rows' => $rowsByParent[(int) $box['id']] ?? [],
    'rowsByParent' => $rowsByParent,
    'boxes' => $boxes,
]);
