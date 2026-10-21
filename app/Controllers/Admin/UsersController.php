<?php

declare(strict_types=1);

namespace Angelma\Controllers\Admin;

use Angelma\Auth;
use Angelma\Installer;
use Angelma\Repos\Users;
use Angelma\Request;
use Angelma\Response;

/**
 * User accounts. Administrators only. The last active administrator cannot be
 * demoted, deactivated or deleted.
 */
final class UsersController
{
    public function index(Request $request): Response
    {
        Auth::requireRole('admin');
        return $this->list();
    }

    public function create(Request $request): Response
    {
        Auth::requireRole('admin');
        $users = new Users();
        $input = [
            'username' => $request->str('username'),
            'display_name' => $request->str('display_name'),
            'role' => $request->str('role'),
            'password' => $request->input('password'),
            'password_repeat' => $request->input('password_repeat'),
        ];
        $errors = Installer::validateAdmin($input);
        if (!isset($errors['username']) && $users->usernameTaken($input['username'])) {
            $errors['username'] = t('user_error_taken');
        }
        if (!in_array($input['role'], Users::ROLES, true)) {
            $errors['role'] = t('user_error_role');
        }
        if ($errors !== []) {
            unset($input['password'], $input['password_repeat']);
            return $this->list($input, $errors, 422);
        }

        $users->create([
            'username' => $input['username'],
            'display_name' => $input['display_name'],
            'password_hash' => password_hash($input['password'], PASSWORD_DEFAULT),
            'role' => $input['role'],
            'is_active' => 1,
        ]);
        flash('success', t('user_created', ['username' => mb_strtolower($input['username'])]));
        return redirect('/admin/users');
    }

    public function edit(Request $request, int $id): Response
    {
        $user = self::load($id);
        return $this->editForm($user, $user);
    }

    public function update(Request $request, int $id): Response
    {
        $user = self::load($id);
        $users = new Users();
        $input = [
            'display_name' => $request->str('display_name'),
            'role' => $request->str('role'),
            'is_active' => $request->bool('is_active') ? 1 : 0,
            'password' => $request->input('password'),
            'password_repeat' => $request->input('password_repeat'),
        ];
        $errors = [];
        if ($input['display_name'] === '' || mb_strlen($input['display_name']) > 80) {
            $errors['display_name'] = t('error_display_name');
        }
        if (!in_array($input['role'], Users::ROLES, true)) {
            $errors['role'] = t('user_error_role');
        }
        if ($input['password'] !== '' || $input['password_repeat'] !== '') {
            if (mb_strlen($input['password']) < Auth::MIN_PASSWORD) {
                $errors['password'] = t('error_password_short', ['min' => Auth::MIN_PASSWORD]);
            } elseif ($input['password'] !== $input['password_repeat']) {
                $errors['password_repeat'] = t('error_password_repeat');
            }
        }
        $losesAdmin = $input['role'] !== 'admin' || $input['is_active'] === 0;
        if ($losesAdmin && self::isLastActiveAdmin($user, $users)) {
            $errors['role'] = t('user_error_last_admin');
        }
        if ($errors !== []) {
            unset($input['password'], $input['password_repeat']);
            return $this->editForm($user, $input + $user, $errors, 422);
        }

        $changes = [
            'display_name' => $input['display_name'],
            'role' => $input['role'],
            'is_active' => $input['is_active'],
        ];
        if ($input['password'] !== '') {
            $changes['password_hash'] = password_hash($input['password'], PASSWORD_DEFAULT);
        }
        $users->update($id, $changes);
        flash('success', t('user_saved', ['username' => (string) $user['username']]));
        return redirect('/admin/users');
    }

    public function delete(Request $request, int $id): Response
    {
        $user = self::load($id);
        $users = new Users();
        if ($id === Auth::id()) {
            flash('error', t('user_error_delete_self'));
            return redirect('/admin/users/' . $id);
        }
        if (self::isLastActiveAdmin($user, $users)) {
            flash('error', t('user_error_last_admin'));
            return redirect('/admin/users/' . $id);
        }
        $users->delete($id, Auth::id());
        flash('success', t('user_deleted', ['username' => (string) $user['username']]));
        return redirect('/admin/users');
    }

    /** @param array<string, mixed> $user */
    private static function isLastActiveAdmin(array $user, Users $users): bool
    {
        return $user['role'] === 'admin' && (int) $user['is_active'] === 1 && $users->countActiveAdmins() <= 1;
    }

    /** @return array<string, mixed> */
    private static function load(int $id): array
    {
        Auth::requireRole('admin');
        $user = (new Users())->find($id);
        if ($user === null) {
            abort(404);
        }
        return $user;
    }

    /**
     * @param array<string, string> $input
     * @param array<string, string> $errors
     */
    private function list(array $input = [], array $errors = [], int $status = 200): Response
    {
        return view('admin/users', [
            'title' => t('nav_users'),
            'nav' => 'users',
            'users' => (new Users())->all(),
            'input' => $input + ['username' => '', 'display_name' => '', 'role' => 'editor'],
            'errors' => $errors,
        ], $status);
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $input
     * @param array<string, string> $errors
     */
    private function editForm(array $user, array $input, array $errors = [], int $status = 200): Response
    {
        return view('admin/users_edit', [
            'title' => t('user_edit_title', ['username' => (string) $user['username']]),
            'nav' => 'users',
            'user' => $user,
            'input' => $input,
            'errors' => $errors,
            'isSelf' => (int) $user['id'] === Auth::id(),
        ], $status);
    }
}
