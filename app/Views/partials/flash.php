<?php foreach (take_flashes() as $flash): ?>
    <p class="flash flash-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></p>
<?php endforeach ?>
