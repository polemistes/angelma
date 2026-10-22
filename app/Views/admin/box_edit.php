<?php
/**
 * Edit one box. Common fields first, then the fields of the box type.
 *
 * @var array<string, mixed> $box
 * @var array<string, mixed> $page
 * @var array<string, mixed> $content
 * @var string $height
 * @var int $width
 * @var list<int> $widths
 * @var array<string, string> $errors
 */
use Angelma\Auth;
use Angelma\Controllers\Admin\BoxesController;

$err = static fn (string $name): string => partial('field_error', ['errors' => $errors, 'name' => $name]);
$back = url('/admin/pages/' . $page['id']) . '#row-' . (int) $box['row_id'];
?>
<p class="muted"><?= e(t('box_on_page', ['name' => (string) $page['name']])) ?></p>

<form method="post" action="<?= e(url('/admin/boxes/' . $box['id'])) ?>" class="form panel box-form">
    <?= csrf_field() ?>

    <div class="field-row">
        <label>
            <span><?= e(t('box_width')) ?></span>
            <select name="width">
                <?php foreach ($widths as $w): ?>
                    <option value="<?= $w ?>"<?= $w === $width ? ' selected' : '' ?>><?= e(t('width_option_' . $w)) ?></option>
                <?php endforeach ?>
            </select>
            <?= $err('width') ?>
        </label>
        <?php if ($box['type'] !== 'container'): ?>
            <label>
                <span><?= e(t('box_height')) ?></span>
                <input type="number" name="height" value="<?= e($height) ?>" min="<?= BoxesController::MIN_HEIGHT ?>"
                       max="<?= BoxesController::MAX_HEIGHT ?>" placeholder="<?= e(t('box_height_auto')) ?>">
                <span class="hint"><?= e(t('box_height_hint')) ?></span>
                <?= $err('height') ?>
            </label>
        <?php endif ?>
    </div>

    <?php if ($box['type'] === 'container'): ?>
        <p class="hint"><?= e(t('box_container_edit_hint')) ?></p>
    <?php endif ?>

    <?php if ($box['type'] === 'text'): ?>
        <div class="markdown-editor">
            <label>
                <span><?= e(t('box_markdown')) ?></span>
                <textarea name="markdown" id="markdown" rows="18" data-preview-target="markdown-preview"><?= e($content['markdown'] ?? '') ?></textarea>
                <span class="hint"><?= e(t('markdown_hint')) ?></span>
                <?= $err('markdown') ?>
            </label>
            <div class="field">
                <span><?= e(t('preview')) ?></span>
                <div class="preview prose" id="markdown-preview" aria-live="polite"><?= raw(\Angelma\Markdown::render((string) ($content['markdown'] ?? ''))) ?></div>
            </div>
        </div>
    <?php endif ?>

    <?php if ($box['type'] === 'image'): ?>
        <fieldset class="picker" data-picker="<?= e(url('/admin/images/picker')) ?>">
            <legend><?= e(t('box_image_choose')) ?></legend>
            <div class="picker-current">
                <?php if ($selectedImage !== null): ?>
                    <img src="<?= e(image_url((string) $selectedImage['filename'], 's')) ?>" alt="" data-picker-preview>
                    <span data-picker-name><?= e($selectedImage['original_name']) ?></span>
                <?php else: ?>
                    <img src="" alt="" data-picker-preview hidden>
                    <span data-picker-name class="muted"><?= e(t('box_no_image')) ?></span>
                <?php endif ?>
            </div>
            <label class="picker-search">
                <span><?= e(t('picker_search')) ?></span>
                <input type="search" data-picker-search placeholder="<?= e(t('picker_search_placeholder')) ?>">
            </label>
            <div class="picker-list" data-picker-list>
                <?= partial('image_picker_list', [
                    'images' => $pickerImages,
                    'selected' => (int) ($content['image_id'] ?? 0),
                ]) ?>
            </div>
            <?= $err('image_id') ?>
        </fieldset>
        <label>
            <span><?= e(t('box_image_alt')) ?></span>
            <input type="text" name="alt" value="<?= e($content['alt'] ?? '') ?>" maxlength="300">
            <span class="hint"><?= e(t('box_image_alt_hint')) ?></span>
            <?= $err('alt') ?>
        </label>
        <label>
            <span><?= e(t('box_image_caption')) ?></span>
            <input type="text" name="caption" value="<?= e($content['caption'] ?? '') ?>" maxlength="300">
            <span class="hint"><?= e(t('box_image_caption_hint')) ?></span>
            <?= $err('caption') ?>
        </label>
        <fieldset>
            <legend><?= e(t('box_image_fit')) ?></legend>
            <?php foreach (['cover', 'contain'] as $fit): ?>
                <label class="check"><input type="radio" name="fit" value="<?= e($fit) ?>"<?= ($content['fit'] ?? 'cover') === $fit ? ' checked' : '' ?>>
                    <span><?= e(t('box_image_fit_' . $fit)) ?></span></label>
            <?php endforeach ?>
            <span class="hint"><?= e(t('box_image_fit_hint')) ?></span>
        </fieldset>
    <?php endif ?>

    <?php if ($box['type'] === 'blog'): ?>
        <?php if ($blogs === []): ?>
            <p class="flash flash-warning"><?= e(t('box_blog_none')) ?> <a href="<?= e(url('/admin/blogs')) ?>#new-blog"><?= e(t('blogs_new')) ?></a></p>
        <?php endif ?>
        <label>
            <span><?= e(t('box_blog')) ?></span>
            <select name="blog_id" required>
                <option value=""><?= e(t('box_blog_choose')) ?></option>
                <?php foreach ($blogs as $b): ?>
                    <option value="<?= (int) $b['id'] ?>"<?= (int) ($content['blog_id'] ?? 0) === (int) $b['id'] ? ' selected' : '' ?>><?= e($b['title']) ?></option>
                <?php endforeach ?>
            </select>
            <?= $err('blog_id') ?>
        </label>
        <label>
            <span><?= e(t('box_blog_count')) ?></span>
            <input type="number" name="count" value="<?= (int) ($content['count'] ?? 5) ?>" min="1" max="20" required>
            <?= $err('count') ?>
        </label>
        <label class="check">
            <input type="checkbox" name="show_excerpts" value="1"<?= (int) ($content['show_excerpts'] ?? 1) === 1 ? ' checked' : '' ?>>
            <span><?= e(t('box_blog_excerpts')) ?></span>
        </label>
    <?php endif ?>

    <?php if ($box['type'] === 'embed'): ?>
        <label>
            <span><?= e(t('embed_url')) ?></span>
            <input type="url" name="url" value="<?= e($content['url'] ?? '') ?>" maxlength="2000" placeholder="https://www.youtube.com/watch?v=...">
            <span class="hint"><?= e(t('embed_url_hint')) ?></span>
            <?= $err('url') ?>
        </label>
        <?php if (Auth::isAdmin()): ?>
            <label>
                <span><?= e(t('embed_html')) ?></span>
                <textarea name="html" rows="6"><?= e($content['html'] ?? '') ?></textarea>
                <span class="hint"><?= e(t('embed_html_hint')) ?></span>
            </label>
        <?php endif ?>
    <?php endif ?>

    <p class="actions">
        <button type="submit" class="button"><?= e(t('save')) ?></button>
        <a class="button secondary" href="<?= e($back) ?>"><?= e(t('cancel')) ?></a>
    </p>
</form>

<?php if ($box['type'] === 'image'): ?>
    <form method="post" action="<?= e(url('/admin/images')) ?>" enctype="multipart/form-data" class="form panel">
        <?= csrf_field() ?>
        <input type="hidden" name="box_id" value="<?= (int) $box['id'] ?>">
        <h2><?= e(t('box_image_upload')) ?></h2>
        <label>
            <span><?= e(t('image_files')) ?></span>
            <input type="file" name="images[]" accept="image/jpeg,image/png,image/gif,image/webp" required>
            <span class="hint"><?= e(t('box_image_upload_hint', ['max' => \Angelma\Images::megabytes($maxBytes)])) ?></span>
        </label>
        <p class="actions"><button type="submit" class="button secondary"><?= e(t('image_upload_button')) ?></button></p>
    </form>
<?php endif ?>
