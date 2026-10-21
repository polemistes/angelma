<?php

declare(strict_types=1);

use Angelma\Controllers\SiteController;
use Angelma\Response;
use Angelma\Router;

return static function (Router $r): void {
    // Used by the installer to check that URL rewriting works.
    $r->get('/install/rewrite-check', static fn (): Response => Response::text('ok'));

    // Public pages. The catch-all page route must stay last.
    $r->get('/', [SiteController::class, 'page']);
    $r->get('/{slug}', [SiteController::class, 'page']);
};
