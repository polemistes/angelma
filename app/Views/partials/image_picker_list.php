<?php
/**
 * Choosable images for the image box form. Radios named image_id.
 *
 * @var list<array<string, mixed>> $images
 * @var int $selected
 */
?>
<?php if ($images === []): ?>
    <p class="muted"><?= e(t('picker_none')) ?></p>
<?php else: ?>
    <ul class="picker-grid">
        <?php foreach ($images as $image): ?>
            <li>
                <label class="picker-item" title="<?= e($image['original_name']) ?>">
                    <input type="radio" name="image_id" value="<?= (int) $image['id'] ?>"
                           data-src="<?= e(image_url((string) $image['filename'], 's')) ?>"
                           data-name="<?= e($image['original_name']) ?>"<?= (int) $image['id'] === $selected ? ' checked' : '' ?>>
                    <img src="<?= e(image_url((string) $image['filename'], 's')) ?>" alt="" loading="lazy">
                    <span><?= e($image['original_name']) ?></span>
                </label>
            </li>
        <?php endforeach ?>
    </ul>
<?php endif ?>
