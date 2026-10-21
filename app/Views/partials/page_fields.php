<?php
/**
 * Shared fields of the new page and page settings forms.
 *
 * @var array<string, mixed> $input
 * @var array<string, string> $errors
 * @var string $prefix id prefix, so two forms on one page do not clash
 */
$err = static fn (string $name): string => partial('field_error', ['errors' => $errors, 'name' => $name]);
?>
<label>
    <span><?= e(t('page_field_name')) ?></span>
    <input type="text" name="name" id="<?= e($prefix) ?>-name" value="<?= e($input['name']) ?>" required maxlength="60"
           data-slug-target="<?= e($prefix) ?>-slug">
    <span class="hint"><?= e(t('page_field_name_hint')) ?></span>
    <?= $err('name') ?>
</label>
<label>
    <span><?= e(t('page_field_title')) ?></span>
    <input type="text" name="title" value="<?= e($input['title']) ?>" maxlength="150">
    <span class="hint"><?= e(t('page_field_title_hint')) ?></span>
    <?= $err('title') ?>
</label>
<label>
    <span><?= e(t('field_slug')) ?></span>
    <input type="text" name="slug" id="<?= e($prefix) ?>-slug" value="<?= e($input['slug']) ?>" maxlength="80"
           pattern="[a-z0-9]+(-[a-z0-9]+)*">
    <span class="hint"><?= e(t('page_field_slug_hint')) ?></span>
    <?= $err('slug') ?>
</label>
<label>
    <span><?= e(t('page_field_meta')) ?></span>
    <input type="text" name="meta_description" value="<?= e($input['meta_description']) ?>" maxlength="300">
    <span class="hint"><?= e(t('page_field_meta_hint')) ?></span>
    <?= $err('meta_description') ?>
</label>
