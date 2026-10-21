<?php
/** @var bool $zipAvailable */
?>
<section class="panel">
    <p><?= e(t('backup_intro')) ?></p>
    <?php if ($zipAvailable): ?>
        <form method="post" action="<?= e(url('/admin/backup')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="button"><?= e(t('backup_download')) ?></button>
        </form>
        <p class="hint"><?= e(t('backup_restore_hint')) ?></p>
    <?php else: ?>
        <p class="flash flash-warning"><?= e(t('backup_no_zip')) ?></p>
    <?php endif ?>
</section>
