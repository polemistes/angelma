<?php
/**
 * Blog box: the newest published posts of a blog.
 *
 * @var array<string, mixed> $content
 */
$blog = !empty($content['blog_id']) ? (new \Angelma\Repos\Blogs())->find((int) $content['blog_id']) : null;
if ($blog === null) {
    return;
}
$count = max(1, min(20, (int) ($content['count'] ?? 5)));
$posts = (new \Angelma\Repos\Posts())->publishedForBlog((int) $blog['id'], $count);
$excerpts = (int) ($content['show_excerpts'] ?? 1) === 1;
?>
<div class="blog-box">
    <h2 class="blog-box-title"><a href="<?= e(url('/blog/' . $blog['slug'])) ?>"><?= e($blog['title']) ?></a></h2>
    <?php if ($posts === []): ?>
        <p class="muted-text"><?= e(t('blog_no_posts_yet')) ?></p>
    <?php else: ?>
        <ul class="blog-box-posts">
            <?php foreach ($posts as $post): ?>
                <li>
                    <a class="blog-box-post" href="<?= e(url('/blog/' . $blog['slug'] . '/' . $post['slug'])) ?>"><?= e($post['title']) ?></a>
                    <span class="post-meta"><?= partial('post_meta', ['post' => $post]) ?></span>
                    <?php if ($excerpts && ($excerpt = \Angelma\Markdown::firstParagraph((string) $post['body_markdown'], 200)) !== ''): ?>
                        <p class="post-excerpt"><?= e($excerpt) ?></p>
                    <?php endif ?>
                </li>
            <?php endforeach ?>
        </ul>
    <?php endif ?>
    <p class="blog-box-all"><a href="<?= e(url('/blog/' . $blog['slug'])) ?>"><?= e(t('blog_all_posts')) ?></a></p>
</div>
