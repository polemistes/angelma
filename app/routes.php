<?php

declare(strict_types=1);

use Angelma\Controllers\Admin\AccountController;
use Angelma\Controllers\Admin\DashboardController;
use Angelma\Controllers\Admin\PagesController;
use Angelma\Controllers\AuthController;
use Angelma\Controllers\SiteController;
use Angelma\Response;
use Angelma\Router;

return static function (Router $r): void {
    // Used by the installer to check that URL rewriting works.
    $r->get('/install/rewrite-check', static fn (): Response => Response::text('ok'));

    // Login and logout.
    $r->get('/login', [AuthController::class, 'showLogin']);
    $r->post('/login', [AuthController::class, 'login']);
    $r->post('/logout', [AuthController::class, 'logout']);

    // Admin.
    $r->get('/admin', [DashboardController::class, 'index']);
    $r->get('/admin/account', [AccountController::class, 'show']);
    $r->post('/admin/account', [AccountController::class, 'save']);

    $r->get('/admin/pages', [PagesController::class, 'index']);
    $r->post('/admin/pages', [PagesController::class, 'create']);
    $r->get('/admin/pages/{id}', [PagesController::class, 'edit']);
    $r->post('/admin/pages/{id}', [PagesController::class, 'update']);
    $r->post('/admin/pages/{id}/delete', [PagesController::class, 'delete']);

    // Public pages. The catch-all page route must stay last.
    $r->get('/', [SiteController::class, 'page']);
    $r->get('/{slug}', [SiteController::class, 'page']);
};
