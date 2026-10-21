<?php
/** @var string $content */
/** @var string $title */
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'Angelma') ?></title>
</head>
<body>
<?= $content ?>
</body>
</html>
