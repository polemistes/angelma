<?php

declare(strict_types=1);

namespace Angelma;

/**
 * A small wrapper around the current HTTP request.
 */
final class Request
{
    /** @var array<string, mixed>|null the logged-in user, set by App */
    public ?array $user = null;

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<string, mixed> $files
     * @param array<string, mixed> $server
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query = [],
        public readonly array $post = [],
        public readonly array $files = [],
        public readonly array $server = [],
    ) {
    }

    public static function fromGlobals(): self
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);

        return new self($method, self::normalizePath(rawurldecode($path)), $_GET, $_POST, $_FILES, $_SERVER);
    }

    public static function normalizePath(string $path): string
    {
        $path = '/' . trim($path, '/');
        return preg_replace('#/+#', '/', $path) ?? '/';
    }

    /** Value from the query string, as a trimmed string. */
    public function query(string $key, string $default = ''): string
    {
        $value = $this->query[$key] ?? $default;
        return is_string($value) ? trim($value) : $default;
    }

    /** Value from the POST body, as a string (not trimmed, so Markdown keeps its spacing). */
    public function input(string $key, string $default = ''): string
    {
        $value = $this->post[$key] ?? $default;
        return is_string($value) ? str_replace("\r\n", "\n", $value) : $default;
    }

    /** Trimmed POST value. */
    public function str(string $key, string $default = ''): string
    {
        return trim($this->input($key, $default));
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->post[$key] ?? $this->query[$key] ?? null;
        if (is_string($value) && preg_match('/^-?\d+$/', trim($value))) {
            return (int) trim($value);
        }
        return $default;
    }

    public function bool(string $key): bool
    {
        $value = $this->post[$key] ?? null;
        return $value === '1' || $value === 'on';
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function isHttps(): bool
    {
        $https = strtolower((string) ($this->server['HTTPS'] ?? ''));
        if ($https !== '' && $https !== 'off') {
            return true;
        }
        return strtolower((string) ($this->server['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }

    public function ip(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    /** Keyed hash of the client IP address, so raw addresses are never stored. */
    public function ipHash(): string
    {
        return hash_hmac('sha256', 'ip:' . $this->ip(), App::secret());
    }

    public function header(string $name): string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return (string) ($this->server[$key] ?? '');
    }

    /** Path plus query string, used as a return address after login. */
    public function fullPath(): string
    {
        $qs = http_build_query($this->query);
        return $this->path . ($qs !== '' ? '?' . $qs : '');
    }

    /** Scheme and host, e.g. https://example.com, for absolute links in the feed. */
    public function origin(): string
    {
        $host = (string) ($this->server['HTTP_HOST'] ?? 'localhost');
        if (!preg_match('/^[A-Za-z0-9.\-:\[\]]+$/', $host)) {
            $host = 'localhost';
        }
        return ($this->isHttps() ? 'https' : 'http') . '://' . $host;
    }
}
