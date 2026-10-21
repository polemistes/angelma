<?php
/**
 * Approve and delete buttons for one comment.
 *
 * @var array<string, mixed> $comment
 * @var bool $canApprove
 * @var bool $canDelete
 * @var string $return
 */
?>
<div class="comment-actions">
    <?php if ($canApprove && $comment['status'] === 'pending'): ?>
        <form method="post" action="<?= e(url('/admin/comments/' . $comment['id'] . '/approve')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="return" value="<?= e($return) ?>">
            <button type="submit" class="button small"><?= e(t('comment_approve')) ?></button>
        </form>
    <?php endif ?>
    <?php if ($canDelete): ?>
        <form method="post" action="<?= e(url('/admin/comments/' . $comment['id'] . '/delete')) ?>"
              data-confirm="<?= e(t('comment_confirm_delete')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="return" value="<?= e($return) ?>">
            <button type="submit" class="button small danger"><?= e(t('delete')) ?></button>
        </form>
    <?php endif ?>
</div>
