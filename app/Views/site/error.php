<?php
/** @var string $title */
/** @var string $message */
?>
<section class="message-page">
    <h1><?= e($title) ?></h1>
    <p><?= e($message) ?></p>
    <p><a href="<?= e(url('/')) ?>"><?= e(t('back_to_home')) ?></a></p>
</section>
