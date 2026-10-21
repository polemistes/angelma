<?php

declare(strict_types=1);

/*
 * Loads the autoloader and configuration and sets up error handling. The front
 * controller (index.php) and the installer include this file.
 */

$angelmaRoot = dirname(__DIR__);
require $angelmaRoot . '/vendor/autoload.php';
Angelma\App::boot($angelmaRoot);
unset($angelmaRoot);
