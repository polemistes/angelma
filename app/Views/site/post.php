<?php
/**
 * One blog post. Comments follow in step 5.1.
 *
 * @var array<string, mixed> $blog
 * @var array<string, mixed> $post
 */
?>
<article class="post">
    <p class="post-blog"><a href="<?= e(url('/blog/' . $blog['slug'])) ?>"><?= e($blog['title']) ?></a></p>
    <h1><?= e($post['title']) ?></h1>
    <p class="post-meta"><?= partial('post_meta', ['post' => $post]) ?></p>
    <div class="prose post-body"><?= raw(\Angelma\Markdown::render((string) $post['body_markdown'])) ?></div>
</article>
