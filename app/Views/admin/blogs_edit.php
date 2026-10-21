<?php
/**
 * @var array<string, mixed> $blog
 * @var array<string, mixed> $input
 * @var array<string, string> $errors
 * @var list<array<string, mixed>> $posts
 */
?>
<p class="actions">
    <a class="button" href="<?= e(url('/admin/blogs/' . $blog['id'] . '/posts/new')) ?>"><?= e(t('post_new')) ?></a>
    <a class="button secondary" href="<?= e(url('/blog/' . $blog['slug'])) ?>"><?= e(t('blog_view')) ?></a>
    <a class="button secondary" href="<?= e(url('/admin/blogs')) ?>"><?= e(t('blog_back_to_list')) ?></a>
</p>

<section class="panel">
    <h2><?= e(t('blog_posts')) ?></h2>
    <?php if ($posts === []): ?>
        <p class="muted"><?= e(t('blog_no_posts')) ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="list">
                <thead>
                <tr>
                    <th><?= e(t('post_field_title')) ?></th>
                    <th><?= e(t('post_field_status')) ?></th>
                    <th><?= e(t('post_field_published_at')) ?></th>
                    <th><?= e(t('post_author')) ?></th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($posts as $post): ?>
                    <tr>
                        <td><a href="<?= e(url('/admin/posts/' . $post['id'])) ?>"><?= e($post['title']) ?></a></td>
                        <td><span class="badge<?= $post['status'] === 'published' ? ' ok' : ' warn' ?>"><?= e(t('status_' . $post['status'])) ?></span></td>
                        <td><?= e(format_date($post['published_at'])) ?></td>
                        <td><?= e($post['author_name']) ?></td>
                        <td class="actions-cell">
                            <?php if ($post['status'] === 'published'): ?>
                                <a class="button small secondary" href="<?= e(url('/blog/' . $blog['slug'] . '/' . $post['slug'])) ?>"><?= e(t('view')) ?></a>
                            <?php endif ?>
                            <a class="button small secondary" href="<?= e(url('/admin/posts/' . $post['id'])) ?>"><?= e(t('edit')) ?></a>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</section>

<details class="panel"<?= $errors !== [] ? ' open' : '' ?>>
    <summary><h2><?= e(t('blog_settings')) ?></h2></summary>
    <form method="post" action="<?= e(url('/admin/blogs/' . $blog['id'])) ?>" class="form">
        <?= csrf_field() ?>
        <?= partial('blog_fields', ['input' => $input, 'errors' => $errors, 'prefix' => 'edit']) ?>
        <p class="actions"><button type="submit" class="button"><?= e(t('save')) ?></button></p>
    </form>
    <form method="post" action="<?= e(url('/admin/blogs/' . $blog['id'] . '/delete')) ?>"
          data-confirm="<?= e(t('blog_confirm_delete', ['title' => (string) $blog['title']])) ?>">
        <?= csrf_field() ?>
        <button type="submit" class="button danger"><?= e(t('blog_delete')) ?></button>
        <span class="hint"><?= e(t('blog_delete_hint')) ?></span>
    </form>
</details>
