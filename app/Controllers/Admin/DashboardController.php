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

        // Editors see numbers for their own content, admins for the whole site.
        $owner = Auth::isAdmin() ? null : Auth::id();
        return view('admin/dashboard', [
            'title' => t('nav_dashboard'),
            'nav' => 'dashboard',
            'counts' => [
                'pages' => count((new Pages())->all($owner)),
                'images' => count((new Images())->all($owner)),
                'blogs' => count((new Blogs())->all($owner)),
                'posts' => (new Posts())->countFor($owner),
                'pending' => (new Comments())->countPending($owner),
            ],
            'recentPosts' => (new Posts())->recent(5, $owner),
            'pending' => array_slice((new Comments())->pendingFor($owner), 0, 5),
        ]);
    }
}
