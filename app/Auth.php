<?php

declare(strict_types=1);

namespace Angelma;

/**
 * Login state, roles, CSRF tokens and login rate limiting.
 */
final class Auth
{
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

    /** @return array<string, mixed>|null the logged-in user */
    public static function user(): ?array
    {
        return null;
    }
}
