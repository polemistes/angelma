<?php
/**
 * @var string $return
 * @var string $username
 * @var string $error
 */
?>
<section class="login">
    <h1><?= e(t('login_title')) ?></h1>
    <?php if ($error !== ''): ?>
        <p class="flash flash-error" role="alert"><?= e($error) ?></p>
    <?php endif ?>
    <form method="post" action="<?= e(url('/login')) ?>" class="form">
        <?= csrf_field() ?>
        <input type="hidden" name="return" value="<?= e($return) ?>">
        <label>
            <span><?= e(t('field_username')) ?></span>
            <input type="text" name="username" value="<?= e($username) ?>" required autocomplete="username" autofocus>
        </label>
        <label>
            <span><?= e(t('field_password')) ?></span>
            <input type="password" name="password" required autocomplete="current-password">
        </label>
        <p><button type="submit" class="button"><?= e(t('login_button')) ?></button></p>
    </form>
</section>
