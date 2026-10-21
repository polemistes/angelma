<?php

declare(strict_types=1);

namespace Angelma\Controllers;

use Angelma\Auth;
use Angelma\Request;
use Angelma\Response;

/**
 * Login and logout.
 */
final class AuthController
{
    public function showLogin(Request $request): Response
    {
        $return = safe_return_path($request->query('return'), '');
        if (Auth::user() !== null) {
            return redirect(self::target(Auth::role(), $return));
        }
        return self::form($return);
    }

    public function login(Request $request): Response
    {
        $username = $request->str('username');
        $return = safe_return_path($request->str('return'), '');
        $result = Auth::attempt($request, $username, $request->input('password'));

        if (is_string($result)) {
            $error = $result === 'blocked'
                ? t('login_blocked', ['minutes' => intdiv(Auth::BLOCK_SECONDS, 60)])
                : t('login_invalid');
            return self::form($return, $username, $error, $result === 'blocked' ? 429 : 422);
        }

        flash('success', t('login_welcome', ['name' => (string) $result['display_name']]));
        return redirect(self::target((string) $result['role'], $return));
    }

    public function logout(Request $request): Response
    {
        Auth::logout();
        flash('success', t('logout_done'));
        return redirect('/');
    }

    /** Admins and editors go to the admin area, users back to where they came from. */
    private static function target(string $role, string $return): string
    {
        if (Auth::satisfies($role, 'editor')) {
            return str_starts_with($return, '/admin') ? $return : '/admin';
        }
        return $return !== '' && !str_starts_with($return, '/login') ? $return : '/';
    }

    private static function form(string $return, string $username = '', string $error = '', int $status = 200): Response
    {
        return view('site/login', [
            'title' => t('login_title'),
            'return' => $return,
            'username' => $username,
            'error' => $error,
        ], $status);
    }
}
