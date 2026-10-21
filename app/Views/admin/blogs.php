<?php
/**
 * @var list<array<string, mixed>> $blogs
 * @var array<string, string> $input
 * @var array<string, string> $errors
 */
?>
<?php if ($blogs === []): ?>
    <p class="muted"><?= e(t('blogs_none')) ?></p>
<?php else: ?>
    <div class="table-wrap">
        <table class="list">
            <thead>
            <tr>
                <th><?= e(t('blog_field_title')) ?></th>
                <th><?= e(t('field_address')) ?></th>
                <th><?= e(t('field_owner')) ?></th>
                <th><?= e(t('blogs_posts')) ?></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($blogs as $blog): ?>
                <tr>
                    <td><a href="<?= e(url('/admin/blogs/' . $blog['id'])) ?>"><?= e($blog['title']) ?></a></td>
                    <td><a href="<?= e(url('/blog/' . $blog['slug'])) ?>">/blog/<?= e($blog['slug']) ?></a></td>
                    <td><?= e($blog['owner_name']) ?></td>
                    <td><?= (int) $blog['post_count'] ?></td>
                    <td class="actions-cell">
                        <a class="button small secondary" href="<?= e(url('/admin/blogs/' . $blog['id'])) ?>"><?= e(t('blogs_open')) ?></a>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>

<section class="panel" id="new-blog">
    <h2><?= e(t('blogs_new')) ?></h2>
    <form method="post" action="<?= e(url('/admin/blogs')) ?>" class="form">
        <?= csrf_field() ?>
        <?= partial('blog_fields', ['input' => $input, 'errors' => $errors, 'prefix' => 'new']) ?>
        <p class="actions"><button type="submit" class="button"><?= e(t('blogs_create')) ?></button></p>
    </form>
</section>
