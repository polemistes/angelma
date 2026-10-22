<?php
/**
 * @var array<string, int> $counts
 * @var list<array<string, mixed>> $recentPosts
 * @var list<array<string, mixed>> $pending
 * @var array<string, mixed>|null $update the cached release check, administrators only
 */
?>
<?php if (!empty($update['newer'])): ?>
    <p class="flash flash-warning"><?= e(t('dashboard_update', ['version' => (string) $update['version']])) ?>
        <a href="<?= e(url('/admin/update')) ?>"><?= e(t('dashboard_update_link')) ?></a></p>
<?php endif ?>
<ul class="stats">
    <li><a href="<?= e(url('/admin/pages')) ?>"><strong><?= (int) $counts['pages'] ?></strong> <?= e(t('nav_pages')) ?></a></li>
    <li><a href="<?= e(url('/admin/images')) ?>"><strong><?= (int) $counts['images'] ?></strong> <?= e(t('nav_images')) ?></a></li>
    <li><a href="<?= e(url('/admin/blogs')) ?>"><strong><?= (int) $counts['blogs'] ?></strong> <?= e(t('nav_blogs')) ?></a></li>
    <li><a href="<?= e(url('/admin/blogs')) ?>"><strong><?= (int) $counts['posts'] ?></strong> <?= e(t('dashboard_posts')) ?></a></li>
    <li><a href="<?= e(url('/admin/comments')) ?>"><strong><?= (int) $counts['pending'] ?></strong> <?= e(t('dashboard_pending')) ?></a></li>
</ul>

<section class="panel">
    <h2><?= e(t('dashboard_quick_links')) ?></h2>
    <p class="actions">
        <a class="button" href="<?= e(url('/admin/pages')) ?>#new-page"><?= e(t('dashboard_new_page')) ?></a>
        <a class="button" href="<?= e(url('/admin/images')) ?>"><?= e(t('dashboard_upload_image')) ?></a>
        <a class="button" href="<?= e(url('/admin/blogs')) ?>"><?= e(t('dashboard_write_post')) ?></a>
    </p>
</section>

<div class="columns">
    <section class="panel">
        <h2><?= e(t('dashboard_recent_posts')) ?></h2>
        <?php if ($recentPosts === []): ?>
            <p class="muted"><?= e(t('dashboard_no_posts')) ?></p>
        <?php else: ?>
            <ul class="plain-list">
                <?php foreach ($recentPosts as $post): ?>
                    <li>
                        <a href="<?= e(url('/admin/posts/' . $post['id'])) ?>"><?= e($post['title']) ?></a>
                        <span class="muted"><?= e($post['blog_title']) ?> ·
                            <?= e($post['status'] === 'published' ? format_date($post['published_at']) : t('status_draft')) ?></span>
                    </li>
                <?php endforeach ?>
            </ul>
        <?php endif ?>
    </section>

    <section class="panel">
        <h2><?= e(t('dashboard_pending_comments')) ?></h2>
        <?php if ($pending === []): ?>
            <p class="muted"><?= e(t('dashboard_no_pending')) ?></p>
        <?php else: ?>
            <ul class="plain-list">
                <?php foreach ($pending as $comment): ?>
                    <li>
                        <strong><?= e($comment['author_name']) ?></strong>
                        <span class="muted"><?= e(t('dashboard_on_post', ['title' => (string) $comment['post_title']])) ?></span><br>
                        <?= e(mb_strimwidth((string) $comment['body'], 0, 120, '…')) ?>
                    </li>
                <?php endforeach ?>
            </ul>
            <p><a href="<?= e(url('/admin/comments')) ?>"><?= e(t('dashboard_moderate')) ?></a></p>
        <?php endif ?>
    </section>
</div>
