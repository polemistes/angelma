<?php
/**
 * What visitors see while maintenance mode is on (PLAN.md section 6.15).
 *
 * @var string $title
 * @var string $message Markdown from Settings, may be empty
 */
?>
<section class="message-page">
    <h1><?= e($title) ?></h1>
    <?php if (trim($message) === ''): ?>
        <p><?= e(t('maintenance_default_text')) ?></p>
    <?php else: ?>
        <div class="prose"><?= raw(\Angelma\Markdown::render($message)) ?></div>
    <?php endif ?>
</section>
