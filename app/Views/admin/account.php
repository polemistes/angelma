<?php
/**
 * @var array<string, mixed> $user
 * @var string $display
 * @var array<string, string> $errors
 */
$err = static fn (string $name): string => partial('field_error', ['errors' => $errors, 'name' => $name]);
?>
<form method="post" action="<?= e(url('/admin/account')) ?>" class="form panel">
    <?= csrf_field() ?>
    <p class="muted"><?= e(t('account_username', ['username' => (string) $user['username']])) ?> ·
        <?= e(t('role_' . $user['role'])) ?></p>

    <label>
        <span><?= e(t('field_display_name')) ?></span>
        <input type="text" name="display_name" value="<?= e($display) ?>" required maxlength="80">
        <?= $err('display_name') ?>
    </label>

    <h2><?= e(t('account_change_password')) ?></h2>
    <p class="hint"><?= e(t('account_password_hint')) ?></p>
    <label>
        <span><?= e(t('account_current_password')) ?></span>
        <input type="password" name="current_password" autocomplete="current-password">
        <?= $err('current_password') ?>
    </label>
    <label>
        <span><?= e(t('account_new_password')) ?></span>
        <input type="password" name="new_password" autocomplete="new-password" minlength="<?= \Angelma\Auth::MIN_PASSWORD ?>">
        <span class="hint"><?= e(t('password_hint', ['min' => \Angelma\Auth::MIN_PASSWORD])) ?></span>
        <?= $err('new_password') ?>
    </label>
    <label>
        <span><?= e(t('field_password_repeat')) ?></span>
        <input type="password" name="new_password_repeat" autocomplete="new-password">
        <?= $err('new_password_repeat') ?>
    </label>

    <p class="actions"><button type="submit" class="button"><?= e(t('save')) ?></button></p>
</form>
