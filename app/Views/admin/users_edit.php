<?php
/**
 * @var array<string, mixed> $user
 * @var array<string, mixed> $input
 * @var array<string, string> $errors
 * @var bool $isSelf
 */
$err = static fn (string $name): string => partial('field_error', ['errors' => $errors, 'name' => $name]);
?>
<p class="actions"><a class="button secondary" href="<?= e(url('/admin/users')) ?>"><?= e(t('user_back')) ?></a></p>

<form method="post" action="<?= e(url('/admin/users/' . $user['id'])) ?>" class="form panel">
    <?= csrf_field() ?>
    <p class="muted"><?= e(t('account_username', ['username' => (string) $user['username']])) ?></p>
    <label>
        <span><?= e(t('field_display_name')) ?></span>
        <input type="text" name="display_name" value="<?= e($input['display_name']) ?>" required maxlength="80">
        <?= $err('display_name') ?>
    </label>
    <label>
        <span><?= e(t('user_role')) ?></span>
        <?= partial('role_select', ['selected' => (string) $input['role']]) ?>
        <?= $err('role') ?>
    </label>
    <label class="check">
        <input type="checkbox" name="is_active" value="1"<?= (int) $input['is_active'] === 1 ? ' checked' : '' ?>>
        <span><?= e(t('user_active_label')) ?></span>
    </label>
    <h2><?= e(t('user_set_password')) ?></h2>
    <p class="hint"><?= e(t('account_password_hint')) ?></p>
    <label>
        <span><?= e(t('account_new_password')) ?></span>
        <input type="password" name="password" autocomplete="new-password" minlength="<?= \Angelma\Auth::MIN_PASSWORD ?>">
        <?= $err('password') ?>
    </label>
    <label>
        <span><?= e(t('field_password_repeat')) ?></span>
        <input type="password" name="password_repeat" autocomplete="new-password">
        <?= $err('password_repeat') ?>
    </label>
    <p class="actions"><button type="submit" class="button"><?= e(t('save')) ?></button></p>
</form>

<?php if (!$isSelf): ?>
    <form method="post" action="<?= e(url('/admin/users/' . $user['id'] . '/delete')) ?>"
          data-confirm="<?= e(t('user_confirm_delete', ['username' => (string) $user['username']])) ?>">
        <?= csrf_field() ?>
        <button type="submit" class="button danger"><?= e(t('user_delete')) ?></button>
        <span class="hint"><?= e(t('user_delete_hint')) ?></span>
    </form>
<?php endif ?>
