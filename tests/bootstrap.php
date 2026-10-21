<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

Angelma\App::$root = dirname(__DIR__);
date_default_timezone_set('UTC');
$_SESSION = [];
