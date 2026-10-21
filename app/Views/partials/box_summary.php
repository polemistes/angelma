<?php
/**
 * Short description of a box's content for the layout editor.
 *
 * @var array<string, mixed> $box
 */
$content = $box['content'];
switch ($box['type']) {
    case 'text':
        $text = trim((string) preg_replace('/\s+/', ' ', (string) ($content['markdown'] ?? '')));
        echo $text === '' ? '<span class="muted">' . e(t('box_empty')) . '</span>' : e(mb_strimwidth($text, 0, 140, '…'));
        break;
    case 'image':
        $image = !empty($content['image_id']) ? (new \Angelma\Repos\Images())->find((int) $content['image_id']) : null;
        echo $image === null
            ? '<span class="muted">' . e(t('box_no_image')) . '</span>'
            : '<img src="' . e(image_url((string) $image['filename'], 's')) . '" alt="" class="thumb">';
        break;
    case 'blog':
        $blog = !empty($content['blog_id']) ? (new \Angelma\Repos\Blogs())->find((int) $content['blog_id']) : null;
        echo $blog === null
            ? '<span class="muted">' . e(t('box_no_blog')) . '</span>'
            : e(t('box_blog_summary', ['title' => (string) $blog['title'], 'count' => (int) ($content['count'] ?? 5)]));
        break;
    case 'embed':
        if (($content['html'] ?? '') !== '') {
            echo e(t('box_custom_html'));
        } elseif (($content['url'] ?? '') !== '') {
            echo e(mb_strimwidth((string) $content['url'], 0, 80, '…'));
        } else {
            echo '<span class="muted">' . e(t('box_empty')) . '</span>';
        }
        break;
}
