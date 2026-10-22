<?php
/**
 * @var list<array<string, mixed>> $images
 * @var int $maxBytes
 */
use Angelma\Controllers\Admin\ImagesController;
use Angelma\Images;
?>
<section class="panel">
    <h2><?= e(t('image_upload_title')) ?></h2>
    <form method="post" action="<?= e(url('/admin/images')) ?>" enctype="multipart/form-data" class="form upload-form">
        <?= csrf_field() ?>
        <label>
            <span><?= e(t('image_files')) ?></span>
            <input type="file" name="images[]" accept="image/jpeg,image/png,image/gif,image/webp" multiple required>
            <span class="hint"><?= e(t('image_upload_hint', ['max' => Images::megabytes($maxBytes)])) ?></span>
        </label>
        <p class="actions"><button type="submit" class="button"><?= e(t('image_upload_button')) ?></button></p>
    </form>
</section>

<?php if ($images === []): ?>
    <p class="muted"><?= e(t('images_none')) ?></p>
<?php else: ?>
    <ul class="image-grid">
        <?php foreach ($images as $image): ?>
            <li class="image-card" id="image-<?= (int) $image['id'] ?>">
                <a href="<?= e(image_url((string) $image['filename'])) ?>" class="image-thumb">
                    <img src="<?= e(image_url((string) $image['filename'], 's')) ?>" alt="<?= e($image['alt']) ?>" loading="lazy">
                </a>
                <div class="image-meta">
                    <strong title="<?= e($image['original_name']) ?>"><?= e($image['original_name']) ?></strong>
                    <span class="muted small"><?= e(t('image_meta', ['width' => (int) $image['width'], 'height' => (int) $image['height'], 'size' => Images::megabytes((int) $image['size'])])) ?> · <?= e($image['uploader_name']) ?></span>
                </div>
                <form method="post" action="<?= e(url('/admin/images/' . $image['id'] . '/alt')) ?>" class="alt-form">
                    <?= csrf_field() ?>
                    <label class="small"><span><?= e(t('image_alt')) ?></span>
                        <input type="text" name="alt" value="<?= e($image['alt']) ?>" maxlength="<?= ImagesController::MAX_ALT ?>">
                    </label>
                    <p class="actions">
                        <button type="submit" class="button small secondary"><?= e(t('save')) ?></button>
                    </p>
                </form>
                <form method="post" action="<?= e(url('/admin/images/' . $image['id'] . '/delete')) ?>"
                      data-confirm="<?= e(t('image_confirm_delete', ['name' => (string) $image['original_name']])) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="button small danger"><?= e(t('delete')) ?></button>
                </form>
            </li>
        <?php endforeach ?>
    </ul>
<?php endif ?>
