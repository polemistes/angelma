<?php
/**
 * @var string $selected
 */
?>
<select name="role">
    <?php foreach (\Angelma\Repos\Users::ROLES as $role): ?>
        <option value="<?= e($role) ?>"<?= $selected === $role ? ' selected' : '' ?>><?= e(t('role_' . $role)) ?></option>
    <?php endforeach ?>
</select>
<span class="hint"><?= e(t('user_roles_hint')) ?></span>
