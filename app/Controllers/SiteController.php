<?php

declare(strict_types=1);

namespace Angelma\Controllers;

use Angelma\App;
use Angelma\Repos\Boxes;
use Angelma\Repos\Pages;
use Angelma\Repos\Rows;
use Angelma\Request;
use Angelma\Response;

/**
 * Public pages: / and /{slug}.
 */
final class SiteController
{
    public function page(Request $request, string $slug = ''): Response
    {
        $pages = new Pages();
        $homeId = (int) App::setting('home_page_id');
        $page = $slug === '' ? $pages->find($homeId) : $pages->findBySlug($slug);
        if ($page === null) {
            abort(404);
        }

        return view('site/page', [
            'page' => $page,
            'rows' => (new Rows())->forPage((int) $page['id']),
            'boxes' => (new Boxes())->forPage((int) $page['id']),
            'title' => (string) $page['title'],
            'is_home' => (int) $page['id'] === $homeId,
            'meta_description' => (string) $page['meta_description'],
            'current_page_id' => (int) $page['id'],
        ]);
    }
}
