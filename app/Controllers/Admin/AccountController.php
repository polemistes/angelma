<?php

declare(strict_types=1);

namespace Angelma\Controllers\Admin;

use Angelma\Auth;
use Angelma\Request;
use Angelma\Response;

final class AccountController
{
    public function show(Request $request): Response
    {
        Auth::requireRole('user');
        return view('admin/account', ['title' => t('nav_account'), 'nav' => 'account', 'user' => Auth::user()]);
    }
}
