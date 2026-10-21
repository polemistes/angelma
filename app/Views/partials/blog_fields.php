<?php
/**
 * @var array<string, mixed> $input
 * @var array<string, string> $errors
 * @var string $prefix
 */
$err = static fn (string $name): string => partial('field_error', ['errors' => $errors, 'name' => $name]);
?>
<label>
    <span><?= e(t('blog_field_title')) ?></span>
    <input type="text" name="title" value="<?= e($input['title']) ?>" required maxlength="100" data-slug-target="<?= e($prefix) ?>-slug">
    <?= $err('title') ?>
</label>
<label>
    <span><?= e(t('field_slug')) ?></span>
    <input type="text" name="slug" id="<?= e($prefix) ?>-slug" value="<?= e($input['slug']) ?>" maxlength="80" pattern="[a-z0-9]+(-[a-z0-9]+)*">
    <span class="hint"><?= e(t('blog_field_slug_hint')) ?></span>
    <?= $err('slug') ?>
</label>
<label>
    <span><?= e(t('blog_field_description')) ?></span>
    <textarea name="description" rows="3" class="short" maxlength="1000"><?= e($input['description']) ?></textarea>
    <span class="hint"><?= e(t('blog_field_description_hint')) ?></span>
    <?= $err('description') ?>
</label>
