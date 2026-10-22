<?php
/**
 * Choose where a box goes: a row on any page, or a new row at the end of a page.
 *
 * @var array<string, mixed> $box
 * @var array<string, mixed> $page the page the box is on now
 * @var list<array{page: array<string, mixed>, options: list<array{0: string, 1: string}>, newRow: string}> $groups
 * @var string $selected
 * @var int $width
 * @var string|null $error
 */
use Angelma\Repos\Boxes;

$back = url('/admin/pages/' . $page['id']) . '#row-' . (int) $box['row_id'];
?>
<p class="muted"><?= e(t('box_move_now', ['page' => (string) $page['name']])) ?></p>

<form method="post" action="<?= e(url('/admin/boxes/' . $box['id'] . '/move-to')) ?>" class="form panel">
    <?= csrf_field() ?>
    <label>
        <span><?= e(t('box_move_target')) ?></span>
        <select name="target" required>
            <option value=""><?= e(t('box_move_choose')) ?></option>
            <?php foreach ($groups as $group): ?>
                <optgroup label="<?= e($group['page']['name']) ?>">
                    <?php foreach ($group['options'] as [$value, $label]): ?>
                        <option value="<?= e($value) ?>"<?= $value === $selected ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach ?>
                    <option value="<?= e($group['newRow']) ?>"<?= $group['newRow'] === $selected ? ' selected' : '' ?>><?= e(t('box_move_new_row')) ?></option>
                </optgroup>
            <?php endforeach ?>
        </select>
        <?= $error !== null ? partial('field_error', ['errors' => ['target' => $error], 'name' => 'target']) : '' ?>
    </label>
    <label>
        <span><?= e(t('box_width')) ?></span>
        <select name="width">
            <?php foreach (Boxes::WIDTHS as $w): ?>
                <option value="<?= $w ?>"<?= $w === $width ? ' selected' : '' ?>><?= e(t('width_option_' . $w)) ?></option>
            <?php endforeach ?>
        </select>
        <span class="hint"><?= e(t('box_move_width_hint')) ?></span>
    </label>
    <p class="actions">
        <button type="submit" class="button"><?= e(t('box_move_button')) ?></button>
        <a class="button secondary" href="<?= e($back) ?>"><?= e(t('cancel')) ?></a>
    </p>
</form>
