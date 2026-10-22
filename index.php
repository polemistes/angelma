<?php

declare(strict_types=1);

/*
 * Front controller: every request that is not a static file comes here.
 */

// While the updater replaces files (PLAN.md section 6.15), answer 503 before loading
// any code. A lock older than five minutes is ignored: it was left behind by a crash.
if (is_file(__DIR__ . '/data/updating.lock') && (int) @filemtime(__DIR__ . '/data/updating.lock') > time() - 300) {
    http_response_code(503);
    header('Retry-After: 30');
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>Updating</title></head><body style="font-family:sans-serif;padding:3rem 1rem;text-align:center">'
        . '<p>This site is being updated. Please try again in a minute.</p></body></html>';
    exit;
}

require __DIR__ . '/app/bootstrap.php';

Angelma\App::handle(Angelma\Request::fromGlobals())->send();
