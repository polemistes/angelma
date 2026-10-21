<?php
/**
 * @var list<array<string, mixed>> $users
 * @var array<string, string> $input
 * @var array<string, string> $errors
 */
$err = static fn (string $name): string => partial('field_error', ['errors' => $errors, 'name' => $name]);
?>
<div class="table-wrap">
    <table class="list">
        <thead>
        <tr>
            <th><?= e(t('field_username')) ?></th>
            <th><?= e(t('field_display_name')) ?></th>
            <th><?= e(t('user_role')) ?></th>
            <th><?= e(t('user_status')) ?></th>
            <th><?= e(t('user_last_login')) ?></th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $user): ?>
            <tr>
                <td><a href="<?= e(url('/admin/users/' . $user['id'])) ?>"><?= e($user['username']) ?></a></td>
                <td><?= e($user['display_name']) ?></td>
                <td><?= e(t('role_' . $user['role'])) ?></td>
                <td><span class="badge<?= (int) $user['is_active'] === 1 ? ' ok' : ' warn' ?>"><?= e((int) $user['is_active'] === 1 ? t('user_active') : t('user_inactive')) ?></span></td>
                <td><?= e($user['last_login_at'] !== null ? format_date((string) $user['last_login_at']) : t('user_never')) ?></td>
                <td class="actions-cell"><a class="button small secondary" href="<?= e(url('/admin/users/' . $user['id'])) ?>"><?= e(t('edit')) ?></a></td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
</div>

<section class="panel" id="new-user">
    <h2><?= e(t('user_new')) ?></h2>
    <form method="post" action="<?= e(url('/admin/users')) ?>" class="form">
        <?= csrf_field() ?>
        <label>
            <span><?= e(t('field_username')) ?></span>
            <input type="text" name="username" value="<?= e($input['username']) ?>" required maxlength="40" autocomplete="off">
            <span class="hint"><?= e(t('error_username')) ?></span>
            <?= $err('username') ?>
        </label>
        <label>
            <span><?= e(t('field_display_name')) ?></span>
            <input type="text" name="display_name" value="<?= e($input['display_name']) ?>" required maxlength="80">
            <?= $err('display_name') ?>
        </label>
        <label>
            <span><?= e(t('user_role')) ?></span>
            <?= partial('role_select', ['selected' => $input['role']]) ?>
            <?= $err('role') ?>
        </label>
        <label>
            <span><?= e(t('field_password')) ?></span>
            <input type="password" name="password" required autocomplete="new-password" minlength="<?= \Angelma\Auth::MIN_PASSWORD ?>">
            <span class="hint"><?= e(t('password_hint', ['min' => \Angelma\Auth::MIN_PASSWORD])) ?></span>
            <?= $err('password') ?>
        </label>
        <label>
            <span><?= e(t('field_password_repeat')) ?></span>
            <input type="password" name="password_repeat" required autocomplete="new-password">
            <?= $err('password_repeat') ?>
        </label>
        <p class="actions"><button type="submit" class="button"><?= e(t('user_create')) ?></button></p>
    </form>
</section>
