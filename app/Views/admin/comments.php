<?php
/**
 * @var list<array<string, mixed>> $pending
 * @var list<array<string, mixed>> $approved
 */
$row = static function (array $comment): string {
    return render('admin/comment_row', ['comment' => $comment]);
};
?>
<section class="panel">
    <h2><?= e(t('comments_pending_title', ['count' => count($pending)])) ?></h2>
    <?php if ($pending === []): ?>
        <p class="muted"><?= e(t('dashboard_no_pending')) ?></p>
    <?php else: ?>
        <ul class="plain-list comment-admin-list">
            <?php foreach ($pending as $comment): ?><?= $row($comment) ?><?php endforeach ?>
        </ul>
    <?php endif ?>
</section>

<section class="panel">
    <h2><?= e(t('comments_recent_title')) ?></h2>
    <?php if ($approved === []): ?>
        <p class="muted"><?= e(t('comments_none')) ?></p>
    <?php else: ?>
        <ul class="plain-list comment-admin-list">
            <?php foreach ($approved as $comment): ?><?= $row($comment) ?><?php endforeach ?>
        </ul>
    <?php endif ?>
</section>
