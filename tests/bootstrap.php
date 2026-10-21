<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

Angelma\App::$root = dirname(__DIR__);
date_default_timezone_set('UTC');
$_SESSION = [];

/** A fresh in-memory database with all migrations, so tests never touch data/. */
function angelma_test_db(): Angelma\Db
{
    $db = Angelma\Db::sqlite(':memory:');
    $db->migrate();
    return $db;
}

Angelma\App::setDb(angelma_test_db());
