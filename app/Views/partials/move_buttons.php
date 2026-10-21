<?php
/**
 * Up and down (or left and right) buttons, each its own POST form.
 *
 * @var string $action
 * @var bool $first
 * @var bool $last
 * @var string $up
 * @var string $down
 * @var string $upIcon
 * @var string $downIcon
 */
foreach (['up' => [$first, $up, $upIcon], 'down' => [$last, $down, $downIcon]] as $direction => [$disabled, $label, $icon]): ?>
<form method="post" action="<?= e(url($action)) ?>" class="inline">
    <?= csrf_field() ?>
    <input type="hidden" name="direction" value="<?= e($direction) ?>">
    <button type="submit" class="button small secondary" title="<?= e($label) ?>" aria-label="<?= e($label) ?>"<?= $disabled ? ' disabled' : '' ?>><?= e($icon) ?></button>
</form>
<?php endforeach;
