<?php
/**
 * One blog post with its comments and the comment form.
 *
 * @var array<string, mixed> $blog
 * @var array<string, mixed> $post
 * @var list<array<string, mixed>> $comments
 * @var bool $moderator
 * @var array<string, mixed>|null $user
 * @var array<string, string> $input
 * @var array<string, string> $errors
 * @var string $formTime
 */
$postPath = '/blog/' . $blog['slug'] . '/' . $post['slug'];
?>
<article class="post">
    <p class="post-blog"><a href="<?= e(url('/blog/' . $blog['slug'])) ?>"><?= e($blog['title']) ?></a></p>
    <h1><?= e($post['title']) ?></h1>
    <p class="post-meta"><?= partial('post_meta', ['post' => $post]) ?></p>
    <div class="prose post-body"><?= raw(\Angelma\Markdown::render((string) $post['body_markdown'])) ?></div>
</article>

<section class="comments" id="comments">
    <h2><?= e(t('comments_title')) ?></h2>
    <?php if ($comments === []): ?>
        <p class="muted-text"><?= e(t('comments_none')) ?></p>
    <?php else: ?>
        <ol class="comment-list">
            <?php foreach ($comments as $comment): ?>
                <?php $pending = $comment['status'] === 'pending'; ?>
                <li class="comment<?= $pending ? ' comment-pending' : '' ?>" id="comment-<?= (int) $comment['id'] ?>">
                    <p class="comment-meta">
                        <strong><?= e($comment['author_name']) ?></strong>
                        · <time datetime="<?= e($comment['created_at']) ?>"><?= e(format_date($comment['created_at'])) ?></time>
                        <?php if ($pending): ?>
                            <span class="comment-badge"><?= e(t('comment_waiting')) ?></span>
                        <?php endif ?>
                    </p>
                    <div class="comment-body"><?= nl2br(e($comment['body']), false) ?></div>
                </li>
            <?php endforeach ?>
        </ol>
    <?php endif ?>

    <?= partial('comment_form', [
        'post' => $post,
        'user' => $user,
        'input' => $input,
        'errors' => $errors,
        'formTime' => $formTime,
        'action' => $postPath . '/comment',
        'loginUrl' => url('/login', ['return' => $postPath . '#comments']),
    ]) ?>
</section>
