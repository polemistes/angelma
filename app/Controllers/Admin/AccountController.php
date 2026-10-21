<?php

declare(strict_types=1);

namespace Angelma\Controllers\Admin;

use Angelma\Auth;
use Angelma\Repos\Users;
use Angelma\Request;
use Angelma\Response;

/**
 * Change one's own display name and password. Open to every role.
 */
final class AccountController
{
    public function show(Request $request): Response
    {
        Auth::requireRole('user');
        return $this->form((string) Auth::user()['display_name']);
    }

    public function save(Request $request): Response
    {
        Auth::requireRole('user');
        $user = Auth::user();
        $display = $request->str('display_name');
        $current = $request->input('current_password');
        $new = $request->input('new_password');
        $repeat = $request->input('new_password_repeat');

        $errors = [];
        if ($display === '' || mb_strlen($display) > 80) {
            $errors['display_name'] = t('error_display_name');
        }
        if ($new !== '' || $repeat !== '') {
            if (!password_verify($current, (string) $user['password_hash'])) {
                $errors['current_password'] = t('account_wrong_current');
            }
            if (mb_strlen($new) < Auth::MIN_PASSWORD) {
                $errors['new_password'] = t('error_password_short', ['min' => Auth::MIN_PASSWORD]);
            } elseif ($new !== $repeat) {
                $errors['new_password_repeat'] = t('error_password_repeat');
            }
        }
        if ($errors !== []) {
            return $this->form($display, $errors, 422);
        }

        $users = new Users();
        $changes = ['display_name' => $display];
        if ($new !== '') {
            $changes['password_hash'] = password_hash($new, PASSWORD_DEFAULT);
        }
        $users->update((int) $user['id'], $changes);
        $fresh = $users->find((int) $user['id']);
        if ($new !== '' && $fresh !== null) {
            Auth::login($fresh);
        } else {
            Auth::setUser($fresh);
        }

        flash('success', $new !== '' ? t('account_password_saved') : t('account_saved'));
        return redirect('/admin/account');
    }

    /** @param array<string, string> $errors */
    private function form(string $display, array $errors = [], int $status = 200): Response
    {
        return view('admin/account', [
            'title' => t('nav_account'),
            'nav' => 'account',
            'user' => Auth::user(),
            'display' => $display,
            'errors' => $errors,
        ], $status);
    }
}
