<?php
/** @var array<string, mixed> $content */
?>
<div class="prose"><?= raw(\Angelma\Markdown::render((string) ($content['markdown'] ?? ''))) ?></div>
