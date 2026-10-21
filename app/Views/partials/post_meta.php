<?php
/** @var array<string, mixed> $post */
?>
<time datetime="<?= e($post['published_at']) ?>"><?= e(format_date($post['published_at'])) ?></time>
<?php if ((string) ($post['author_name'] ?? '') !== ''): ?>
    · <?= e(t('post_by', ['name' => (string) $post['author_name']])) ?>
<?php endif;
