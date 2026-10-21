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
        <label>
            <span><?= e(t('box_height')) ?></span>
            <input type="number" name="height" value="<?= e($height) ?>" min="<?= BoxesController::MIN_HEIGHT ?>"
                   max="<?= BoxesController::MAX_HEIGHT ?>" placeholder="<?= e(t('box_height_auto')) ?>">
            <span class="hint"><?= e(t('box_height_hint')) ?></span>
            <?= $err('height') ?>
        </label>
    </div>

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
