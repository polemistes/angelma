<?php
/** @var array<string, mixed> $comment */
$postPath = '/blog/' . $comment['blog_slug'] . '/' . $comment['post_slug'];
?>
<li id="comment-<?= (int) $comment['id'] ?>">
    <p class="comment-admin-meta">
        <strong><?= e($comment['author_name']) ?></strong>
        <?php if ($comment['user_id'] === null): ?><span class="badge"><?= e(t('comment_guest')) ?></span><?php endif ?>
        <span class="muted">· <?= e(format_date($comment['created_at'], \Angelma\App::setting('date_format', 'j F Y') . ' H:i')) ?> ·
            <?= e(t('comment_on')) ?> <a href="<?= e(url($postPath)) ?>#comments"><?= e($comment['post_title']) ?></a></span>
    </p>
    <div class="comment-admin-body"><?= nl2br(e($comment['body']), false) ?></div>
    <?= partial('comment_actions', [
        'comment' => $comment,
        'canApprove' => true,
        'canDelete' => true,
        'return' => '/admin/comments',
    ]) ?>
</li>
