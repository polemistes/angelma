<?php

declare(strict_types=1);

namespace Angelma;

use Angelma\Repos\LoginAttempts;
use Angelma\Repos\Users;

/**
 * Login state, roles, CSRF tokens and login rate limiting.
 */
final class Auth
{
    /** Role levels: a higher level satisfies every lower requirement. */
    public const LEVELS = ['user' => 1, 'editor' => 2, 'admin' => 3];

    public const MIN_PASSWORD = 10;
    public const MAX_FAILURES = 5;
    public const BLOCK_SECONDS = 900;

    /** Hash of a random string, checked when the username is unknown. */
    private const DUMMY_HASH = '$2y$12$PJ7z08CA8jRGw1WcZr7v8e1PoAuniJE2NKN6p0Un1slJ63ZMtgCcy';

    /** @var array<string, mixed>|false|null false = not loaded yet */
    private static array|false|null $user = false;

    // ---------------------------------------------------------------- CSRF

    public static function csrfToken(): string
    {
        if (!isset($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function verifyCsrf(Request $request): bool
    {
        $token = $request->post['_csrf'] ?? $request->header('X-CSRF-Token');
        $expected = $_SESSION['_csrf'] ?? null;
        return is_string($token) && $token !== '' && is_string($expected) && hash_equals($expected, $token);
    }

    // ---------------------------------------------------------------- current user

    /** @return array<string, mixed>|null the logged-in, active user */
    public static function user(): ?array
    {
        if (self::$user === false) {
            self::$user = null;
            $id = $_SESSION['user_id'] ?? null;
            if (is_int($id)) {
                $user = (new Users())->find($id);
                if ($user !== null && (int) $user['is_active'] === 1) {
                    self::$user = $user;
                } else {
                    unset($_SESSION['user_id']);
                }
            }
        }
        return self::$user;
    }

    /** Set the current user directly (after login, and in tests). */
    public static function setUser(?array $user): void
    {
        self::$user = $user;
        if (App::$request !== null) {
            App::$request->user = $user;
        }
    }

    /** Forget the cached user so the next call reloads it from the session. */
    public static function reset(): void
    {
        self::$user = false;
    }

    public static function id(): int
    {
        return (int) (self::user()['id'] ?? 0);
    }

    public static function role(): string
    {
        return (string) (self::user()['role'] ?? '');
    }

    public static function isAdmin(): bool
    {
        return self::role() === 'admin';
    }

    /** True when a role satisfies a requirement (admin satisfies every role). */
    public static function satisfies(string $role, string $required): bool
    {
        return (self::LEVELS[$role] ?? 0) >= (self::LEVELS[$required] ?? PHP_INT_MAX);
    }

    public static function can(string $required): bool
    {
        return self::user() !== null && self::satisfies(self::role(), $required);
    }

    /**
     * Stop unless the current user has at least the given role. Visitors who are not
     * logged in are sent to the login page with a return address.
     */
    public static function requireRole(string $role): void
    {
        if (self::user() === null) {
            $return = App::$request?->fullPath() ?? '/';
            throw new HttpException(302, Response::redirect(url('/login', ['return' => $return]), 302));
        }
        if (!self::satisfies(self::role(), $role)) {
            throw new HttpException(403);
        }
    }

    // ---------------------------------------------------------------- login

    /**
     * Try to log in. Returns the user on success, or an error key: 'blocked' when rate
     * limited, 'invalid' for a wrong username or password.
     *
     * @return array<string, mixed>|string
     */
    public static function attempt(Request $request, string $username, string $password, ?int $time = null): array|string
    {
        $time ??= time();
        $keys = self::attemptKeys($request, $username);
        $attempts = new LoginAttempts();
        $attempts->deleteOlderThan(self::stamp($time - 3600));

        if (self::isBlocked($keys, $time)) {
            return 'blocked';
        }

        $users = new Users();
        $user = $username !== '' ? $users->findByUsername($username) : null;
        // Verify against a dummy hash when the user is unknown, so timing reveals nothing.
        $hash = $user !== null ? (string) $user['password_hash'] : self::DUMMY_HASH;
        $valid = password_verify($password, $hash) && $user !== null && (int) $user['is_active'] === 1;

        if (!$valid) {
            foreach ($keys as $key) {
                $attempts->record($key, self::stamp($time));
            }
            return 'invalid';
        }

        $attempts->clear($keys['user']);
        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            $users->update((int) $user['id'], ['password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
        }
        $users->touchLogin((int) $user['id']);
        self::login($user);
        return $user;
    }

    /** @param array<string, mixed> $user */
    public static function login(array $user): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        unset($_SESSION['_csrf']);
        $_SESSION['user_id'] = (int) $user['id'];
        self::setUser($user);
    }

    public static function logout(): void
    {
        unset($_SESSION['user_id'], $_SESSION['_csrf']);
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        self::setUser(null);
    }

    /**
     * True when any key has MAX_FAILURES failures in the last BLOCK_SECONDS.
     *
     * @param array<string, string> $keys
     */
    public static function isBlocked(array $keys, ?int $time = null): bool
    {
        $since = self::stamp(($time ?? time()) - self::BLOCK_SECONDS);
        $attempts = new LoginAttempts();
        foreach ($keys as $key) {
            if ($attempts->countSince($key, $since) >= self::MAX_FAILURES) {
                return true;
            }
        }
        return false;
    }

    /** @return array{ip: string, user: string} hashed rate-limit keys */
    public static function attemptKeys(Request $request, string $username): array
    {
        return [
            'ip' => $request->ipHash(),
            'user' => hash_hmac('sha256', 'user:' . mb_strtolower(trim($username)), App::secret()),
        ];
    }

    private static function stamp(int $time): string
    {
        return gmdate('Y-m-d\TH:i:s\Z', $time);
    }
}
