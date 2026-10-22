<?php
/**
 * The Update screen (PLAN.md section 6.16).
 *
 * @var string $current installed version
 * @var array<string, mixed> $check result of Updater::check()
 * @var string $notesHtml release notes rendered from Markdown
 * @var list<array{key: string, ok: bool, detail: string}> $preflight
 * @var string|null $previous version kept in data/updates/previous
 * @var string $releasePage
 */
$ready = true;
foreach ($preflight as $item) {
    $ready = $ready && $item['ok'];
}
$installable = $ready && !empty($check['zip_url']) && !empty($check['sum_url']);
?>
<section class="panel">
    <h2><?= e(t('update_installed')) ?></h2>
    <p><strong>Angelma <?= e($current) ?></strong></p>
    <p class="muted small">
        <?= e(isset($check['checked_at']) ? t('update_last_check', ['time' => format_date(gmdate('Y-m-d\TH:i:s\Z', (int) $check['checked_at']), 'j F Y H:i')]) : t('update_never_checked')) ?>
    </p>
    <form method="post" action="<?= e(url('/admin/update/check')) ?>" class="inline">
        <?= csrf_field() ?>
        <button type="submit" class="button secondary"><?= e(t('update_check_now')) ?></button>
    </form>
</section>

<?php if (isset($check['error'])): ?>
    <section class="panel">
        <p class="flash flash-error"><?= e(t('update_error', ['error' => (string) $check['error']])) ?></p>
        <p><a href="<?= e($releasePage) ?>" rel="noopener"><?= e(t('update_release_page')) ?></a></p>
    </section>
<?php elseif (!empty($check['newer'])): ?>
    <section class="panel">
        <h2><?= e(t('update_available', ['version' => (string) $check['version']])) ?></h2>
        <?php if (($check['published_at'] ?? '') !== ''): ?>
            <p class="muted small"><?= e(t('update_published', ['date' => format_date((string) $check['published_at'])])) ?></p>
        <?php endif ?>
        <?php if (trim($notesHtml) !== ''): ?>
            <div class="prose release-notes"><?= raw($notesHtml) ?></div>
        <?php endif ?>

        <h3><?= e(t('update_preflight')) ?></h3>
        <ul class="check-list">
            <?php foreach ($preflight as $item): ?>
                <li>
                    <span class="badge <?= $item['ok'] ? 'ok' : 'bad' ?>"><?= e(t($item['ok'] ? 'update_ok' : 'update_failed_check')) ?></span>
                    <?= e(t('update_check_' . $item['key'])) ?>
                    <?php if ($item['detail'] !== ''): ?><span class="muted small">(<?= e($item['detail']) ?>)</span><?php endif ?>
                </li>
            <?php endforeach ?>
        </ul>

        <?php if ($installable): ?>
            <form method="post" action="<?= e(url('/admin/update/install')) ?>"
                  data-confirm="<?= e(t('update_confirm', ['version' => (string) $check['version']])) ?>">
                <?= csrf_field() ?>
                <p class="actions"><button type="submit" class="button"><?= e(t('update_install', ['version' => (string) $check['version']])) ?></button></p>
                <p class="hint"><?= e(t('update_install_hint')) ?></p>
            </form>
        <?php else: ?>
            <p class="flash flash-warning"><?= e(t('update_manual')) ?></p>
            <p><a href="<?= e(($check['html_url'] ?? '') !== '' ? (string) $check['html_url'] : $releasePage) ?>" rel="noopener"><?= e(t('update_release_page')) ?></a></p>
        <?php endif ?>
    </section>
<?php elseif (isset($check['version'])): ?>
    <section class="panel">
        <p><?= e(t('update_up_to_date')) ?></p>
    </section>
<?php endif ?>

<?php if ($previous !== null): ?>
    <section class="panel">
        <h2><?= e(t('update_previous')) ?></h2>
        <p><?= e(t('update_previous_kept', ['version' => $previous])) ?></p>
        <form method="post" action="<?= e(url('/admin/update/rollback')) ?>"
              data-confirm="<?= e(t('update_restore_confirm', ['version' => $previous])) ?>">
            <?= csrf_field() ?>
            <p class="actions"><button type="submit" class="button danger"><?= e(t('update_restore', ['version' => $previous])) ?></button></p>
        </form>
    </section>
<?php endif ?>
