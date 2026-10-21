<?php
/**
 * New or existing post.
 *
 * @var array<string, mixed> $blog
 * @var array<string, mixed>|null $post
 * @var array<string, mixed> $input
 * @var array<string, string> $errors
 */
use Angelma\Repos\Posts;

$err = static fn (string $name): string => partial('field_error', ['errors' => $errors, 'name' => $name]);
$action = $post === null ? '/admin/blogs/' . $blog['id'] . '/posts/new' : '/admin/posts/' . $post['id'];
?>
<p class="actions">
    <a class="button secondary" href="<?= e(url('/admin/blogs/' . $blog['id'])) ?>"><?= e(t('post_back_to_blog', ['blog' => (string) $blog['title']])) ?></a>
    <?php if ($post !== null && $post['status'] === 'published'): ?>
        <a class="button secondary" href="<?= e(url('/blog/' . $blog['slug'] . '/' . $post['slug'])) ?>"><?= e(t('post_view')) ?></a>
    <?php endif ?>
</p>

<form method="post" action="<?= e(url($action)) ?>" class="form panel">
    <?= csrf_field() ?>
    <label>
        <span><?= e(t('post_field_title')) ?></span>
        <input type="text" name="title" value="<?= e($input['title']) ?>" required maxlength="200" data-slug-target="post-slug">
        <?= $err('title') ?>
    </label>
    <label>
        <span><?= e(t('field_slug')) ?></span>
        <input type="text" name="slug" id="post-slug" value="<?= e($input['slug']) ?>" maxlength="80" pattern="[a-z0-9]+(-[a-z0-9]+)*">
        <span class="hint"><?= e(t('post_field_slug_hint', ['blog' => (string) $blog['slug']])) ?></span>
        <?= $err('slug') ?>
    </label>

    <div class="markdown-editor">
        <label>
            <span><?= e(t('post_field_body')) ?></span>
            <textarea name="body_markdown" rows="20" data-preview-target="post-preview"><?= e($input['body_markdown']) ?></textarea>
            <span class="hint"><?= e(t('markdown_hint')) ?></span>
            <?= $err('body_markdown') ?>
        </label>
        <div class="field">
            <span><?= e(t('preview')) ?></span>
            <div class="preview prose" id="post-preview" aria-live="polite"><?= raw(\Angelma\Markdown::render((string) $input['body_markdown'])) ?></div>
        </div>
    </div>

    <div class="field-row">
        <label>
            <span><?= e(t('post_field_status')) ?></span>
            <select name="status">
                <?php foreach (Posts::STATUSES as $status): ?>
                    <option value="<?= e($status) ?>"<?= $input['status'] === $status ? ' selected' : '' ?>><?= e(t('status_' . $status)) ?></option>
                <?php endforeach ?>
            </select>
            <span class="hint"><?= e(t('post_field_status_hint')) ?></span>
        </label>
        <label>
            <span><?= e(t('post_field_comments')) ?></span>
            <select name="comments_mode">
                <?php foreach (Posts::COMMENT_MODES as $mode): ?>
                    <option value="<?= e($mode) ?>"<?= $input['comments_mode'] === $mode ? ' selected' : '' ?>><?= e(t('comments_mode_' . $mode)) ?></option>
                <?php endforeach ?>
            </select>
        </label>
        <label>
            <span><?= e(t('post_field_published_at')) ?></span>
            <input type="datetime-local" name="published_at" value="<?= e($input['published_at']) ?>">
            <span class="hint"><?= e(t('post_field_published_at_hint', ['tz' => \Angelma\App::setting('timezone')])) ?></span>
            <?= $err('published_at') ?>
        </label>
    </div>

    <p class="actions"><button type="submit" class="button"><?= e(t('save')) ?></button></p>
</form>

<?php if ($post !== null): ?>
    <form method="post" action="<?= e(url('/admin/posts/' . $post['id'] . '/delete')) ?>"
          data-confirm="<?= e(t('post_confirm_delete', ['title' => (string) $post['title']])) ?>">
        <?= csrf_field() ?>
        <button type="submit" class="button danger"><?= e(t('post_delete')) ?></button>
    </form>
<?php endif ?>
