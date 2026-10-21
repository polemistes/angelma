<?php
/**
 * @var array<string, mixed> $blog
 * @var list<array<string, mixed>> $posts
 * @var int $page
 * @var int $pages
 */
?>
<section class="blog">
    <header class="blog-header">
        <h1><?= e($blog['title']) ?></h1>
        <?php if ((string) $blog['description'] !== ''): ?>
            <p class="blog-description"><?= e($blog['description']) ?></p>
        <?php endif ?>
    </header>

    <?php if ($posts === []): ?>
        <p><?= e(t('blog_no_posts_yet')) ?></p>
    <?php else: ?>
        <ol class="post-list">
            <?php foreach ($posts as $post): ?>
                <li class="post-summary">
                    <h2><a href="<?= e(url('/blog/' . $blog['slug'] . '/' . $post['slug'])) ?>"><?= e($post['title']) ?></a></h2>
                    <p class="post-meta"><?= partial('post_meta', ['post' => $post]) ?></p>
                    <?php $excerpt = \Angelma\Markdown::firstParagraph((string) $post['body_markdown']); ?>
                    <?php if ($excerpt !== ''): ?>
                        <p class="post-excerpt"><?= e($excerpt) ?></p>
                    <?php endif ?>
                </li>
            <?php endforeach ?>
        </ol>
    <?php endif ?>

    <?= partial('pagination', ['page' => $page, 'pages' => $pages, 'path' => '/blog/' . $blog['slug']]) ?>

    <p class="feed-link"><a href="<?= e(url('/blog/' . $blog['slug'] . '/feed')) ?>"><?= e(t('blog_feed')) ?></a></p>
</section>
