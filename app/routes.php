<?php

declare(strict_types=1);

use Angelma\Controllers\Admin\AccountController;
use Angelma\Controllers\Admin\BlogsController;
use Angelma\Controllers\Admin\BoxesController;
use Angelma\Controllers\Admin\DashboardController;
use Angelma\Controllers\Admin\ImagesController;
use Angelma\Controllers\Admin\PagesController;
use Angelma\Controllers\Admin\PostsController;
use Angelma\Controllers\Admin\RowsController;
use Angelma\Controllers\AuthController;
use Angelma\Controllers\BlogController;
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
    $r->post('/admin/pages/{id}/rows', [RowsController::class, 'create']);

    $r->post('/admin/rows/{id}/move', [RowsController::class, 'move']);
    $r->post('/admin/rows/{id}/delete', [RowsController::class, 'delete']);
    $r->post('/admin/rows/{id}/alignment', [RowsController::class, 'alignment']);
    $r->post('/admin/rows/{id}/boxes', [BoxesController::class, 'create']);

    $r->get('/admin/boxes/{id}', [BoxesController::class, 'edit']);
    $r->post('/admin/boxes/{id}', [BoxesController::class, 'update']);
    $r->post('/admin/boxes/{id}/move', [BoxesController::class, 'move']);
    $r->post('/admin/boxes/{id}/delete', [BoxesController::class, 'delete']);
    $r->post('/admin/preview', [BoxesController::class, 'preview']);

    $r->get('/admin/images', [ImagesController::class, 'index']);
    $r->post('/admin/images', [ImagesController::class, 'upload']);
    $r->get('/admin/images/picker', [ImagesController::class, 'picker']);
    $r->post('/admin/images/{id}/alt', [ImagesController::class, 'alt']);
    $r->post('/admin/images/{id}/delete', [ImagesController::class, 'delete']);

    $r->get('/admin/blogs', [BlogsController::class, 'index']);
    $r->post('/admin/blogs', [BlogsController::class, 'create']);
    $r->get('/admin/blogs/{id}', [BlogsController::class, 'edit']);
    $r->post('/admin/blogs/{id}', [BlogsController::class, 'update']);
    $r->post('/admin/blogs/{id}/delete', [BlogsController::class, 'delete']);
    $r->get('/admin/blogs/{id}/posts/new', [PostsController::class, 'new']);
    $r->post('/admin/blogs/{id}/posts/new', [PostsController::class, 'create']);
    $r->get('/admin/posts/{id}', [PostsController::class, 'edit']);
    $r->post('/admin/posts/{id}', [PostsController::class, 'update']);
    $r->post('/admin/posts/{id}/delete', [PostsController::class, 'delete']);

    // Public blogs.
    $r->get('/blog/{blog}', [BlogController::class, 'index']);
    $r->get('/blog/{blog}/feed', [BlogController::class, 'feed']);
    $r->get('/blog/{blog}/{post}', [BlogController::class, 'post']);
    $r->post('/blog/{blog}/{post}/comment', [BlogController::class, 'comment']);

    // Public pages. The catch-all page route must stay last.
    $r->get('/', [SiteController::class, 'page']);
    $r->get('/{slug}', [SiteController::class, 'page']);
};
