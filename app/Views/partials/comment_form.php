<?php
/**
 * The comment form, or a note saying why there is none (PLAN.md section 6.7).
 *
 * @var array<string, mixed> $post
 * @var array<string, mixed>|null $user
 * @var array<string, string> $input
 * @var array<string, string> $errors
 * @var string $formTime
 * @var string $action
 * @var string $loginUrl
 */
use Angelma\Controllers\BlogController;

$mode = (string) $post['comments_mode'];
$err = static fn (string $name): string => partial('field_error', ['errors' => $errors, 'name' => $name]);
?>
<?php if ($mode === 'closed'): ?>
    <p class="comments-note"><?= e(t('comments_closed')) ?></p>
<?php elseif ($mode === 'users' && $user === null): ?>
    <p class="comments-note"><a href="<?= e($loginUrl) ?>"><?= e(t('comments_login')) ?></a></p>
<?php else: ?>
    <form method="post" action="<?= e(url($action)) ?>#comment-form" class="form comment-form" id="comment-form">
        <h3><?= e(t('comment_write')) ?></h3>
        <?php if (isset($errors['form'])): ?>
            <p class="flash flash-error" role="alert"><?= e($errors['form']) ?></p>
        <?php endif ?>
        <?= csrf_field() ?>
        <input type="hidden" name="form_time" value="<?= e($formTime) ?>">
        <div class="hp" aria-hidden="true">
            <label><?= e(t('comment_honeypot')) ?> <input type="text" name="website" value="" tabindex="-1" autocomplete="off"></label>
        </div>
        <?php if ($user === null): ?>
            <label>
                <span><?= e(t('comment_name')) ?></span>
                <input type="text" name="author_name" value="<?= e($input['author_name']) ?>" required maxlength="<?= BlogController::COMMENT_MAX_NAME ?>" autocomplete="name">
                <?= $err('author_name') ?>
            </label>
        <?php else: ?>
            <p class="comment-as"><?= e(t('comment_as', ['name' => (string) $user['display_name']])) ?></p>
        <?php endif ?>
        <label>
            <span><?= e(t('comment_body')) ?></span>
            <textarea name="body" rows="5" required maxlength="<?= BlogController::COMMENT_MAX_LENGTH ?>"><?= e($input['body']) ?></textarea>
            <?= $err('body') ?>
        </label>
        <?php if ($user === null && \Angelma\App::setting('moderate_anonymous', '1') === '1'): ?>
            <p class="comment-hint"><?= e(t('comment_moderation_hint')) ?></p>
        <?php endif ?>
        <p><button type="submit" class="button"><?= e(t('comment_submit')) ?></button></p>
    </form>
<?php endif;
