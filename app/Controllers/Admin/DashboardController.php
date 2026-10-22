<?php

declare(strict_types=1);

namespace Angelma\Controllers\Admin;

use Angelma\Auth;
use Angelma\Repos\Blogs;
use Angelma\Repos\Comments;
use Angelma\Repos\Images;
use Angelma\Repos\Pages;
use Angelma\Repos\Posts;
use Angelma\Request;
use Angelma\Response;

final class DashboardController
{
    public function index(Request $request): Response
    {
        Auth::requireRole('user');
        if (!Auth::can('editor')) {
            return redirect('/admin/account');
        }

        return view('admin/dashboard', [
            'title' => t('nav_dashboard'),
            'nav' => 'dashboard',
            'counts' => [
                'pages' => count((new Pages())->all()),
                'images' => (new Images())->count(),
                'blogs' => (new Blogs())->count(),
                'posts' => (new Posts())->count(),
                'pending' => (new Comments())->countPending(),
            ],
            'recentPosts' => (new Posts())->recent(5),
            'pending' => array_slice((new Comments())->pending(), 0, 5),
        ]);
    }
}
