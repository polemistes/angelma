<?php

declare(strict_types=1);

namespace Angelma;

/**
 * Application state and the request cycle: configuration, database, settings,
 * session, error handling and dispatching.
 */
final class App
{
    public const CSP = "default-src 'self'; img-src 'self' data: https:; "
        . 'frame-src https://www.youtube.com https://player.vimeo.com https://www.google.com '
        . "https://www.openstreetmap.org; style-src 'self' 'unsafe-inline'; script-src 'self'";

    /** Settings used when the settings table has no value (PLAN.md section 4). */
    public const DEFAULT_SETTINGS = [
        'site_title' => 'Angelma',
        'site_tagline' => '',
        'logo_image_id' => '',
        'page_width' => '960',
        'scheme' => 'paper',
        'custom_scheme' => '',
        'footer_markdown' => '',
        'home_page_id' => '',
        'moderate_anonymous' => '1',
        'timezone' => 'Europe/Oslo',
        'date_format' => 'j F Y',
    ];

    public static string $root = '';

    /** @var array<string, mixed> contents of data/config.php */
    public static array $config = [];

    public static ?Request $request = null;

    private static ?Db $db = null;

    /** @var array<string, string>|null */
    private static ?array $settings = null;

    public static function boot(string $root): void
    {
        self::$root = $root;
        $configFile = $root . '/data/config.php';
        if (is_file($configFile)) {
            $config = require $configFile;
            self::$config = is_array($config) ? $config : [];
        }

        date_default_timezone_set('UTC');
        mb_internal_encoding('UTF-8');

        error_reporting(E_ALL);
        ini_set('display_errors', self::debug() ? '1' : '0');
        ini_set('log_errors', '1');
        if (is_dir($root . '/data') && is_writable($root . '/data')) {
            ini_set('error_log', $root . '/data/error.log');
        }
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });
        set_exception_handler(static function (\Throwable $e): void {
            error_log((string) $e);
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: text/plain; charset=utf-8');
            }
            echo self::debug() ? (string) $e : t('error_500_title') . "\n";
        });
    }

    public static function debug(): bool
    {
        return (bool) (self::$config['debug'] ?? false);
    }

    public static function installed(): bool
    {
        return is_file(self::$root . '/data/installed.lock');
    }

    /** Secret from data/config.php, used for keyed hashes. */
    public static function secret(): string
    {
        $secret = self::$config['secret'] ?? '';
        return is_string($secret) && $secret !== '' ? $secret : 'angelma-not-installed:' . self::$root;
    }

    public static function db(): Db
    {
        if (self::$db === null) {
            $path = (string) (self::$config['db_path'] ?? 'data/angelma.sqlite');
            if (!str_starts_with($path, '/')) {
                $path = self::$root . '/' . $path;
            }
            self::$db = Db::sqlite($path);
        }
        return self::$db;
    }

    /** Replace the database, used by tests and the installer. */
    public static function setDb(?Db $db): void
    {
        self::$db = $db;
        self::$settings = null;
    }

    /** @return array<string, string> */
    public static function settings(): array
    {
        if (self::$settings === null) {
            self::$settings = self::$db !== null || self::installed()
                ? (new Repos\Settings(self::db()))->all()
                : [];
        }
        return self::$settings;
    }

    public static function setting(string $key, string $default = ''): string
    {
        return self::settings()[$key] ?? self::DEFAULT_SETTINGS[$key] ?? $default;
    }

    public static function forgetSettings(): void
    {
        self::$settings = null;
    }

    public static function startSession(Request $request): void
    {
        if (session_status() === PHP_SESSION_ACTIVE || PHP_SAPI === 'cli') {
            return;
        }
        $dir = self::$root . '/data/sessions';
        if (is_dir($dir) && is_writable($dir)) {
            session_save_path($dir);
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '100');
        ini_set('session.gc_maxlifetime', '86400');
        session_name('angelma');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $request->isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public static function router(): Router
    {
        $router = new Router(static fn (Request $r): bool => Auth::verifyCsrf($r));
        (require self::$root . '/app/routes.php')($router);
        return $router;
    }

    public static function handle(Request $request): Response
    {
        self::$request = $request;
        try {
            $response = self::dispatch($request);
        } catch (HttpException $e) {
            $response = $e->response ?? self::errorResponse($e->status);
        } catch (\Throwable $e) {
            error_log((string) $e);
            $response = self::errorResponse(500, $e);
        }
        $response->headers['Content-Security-Policy'] ??= self::CSP;
        if (session_status() === PHP_SESSION_ACTIVE) {
            $response->headers['Cache-Control'] ??= 'no-store';
        }
        return $response;
    }

    private static function dispatch(Request $request): Response
    {
        if (!self::installed()) {
            if (!preg_match('#^/(install|assets)(/|$)#', $request->path)) {
                return Response::redirect('/install/', 302);
            }
        } else {
            self::startSession($request);
            $request->user = Auth::user();
        }
        // A POST larger than post_max_size arrives empty: say so instead of failing the CSRF check.
        if ($request->method === 'POST' && $request->post === [] && $request->files === []
            && (int) ($request->server['CONTENT_LENGTH'] ?? 0) > Images::iniBytes((string) ini_get('post_max_size'))) {
            throw new HttpException(413);
        }
        return self::router()->dispatch($request);
    }

    public static function errorResponse(int $status, ?\Throwable $error = null): Response
    {
        if ($status === 500 && $error !== null && self::debug()) {
            return new Response(
                '<!doctype html><meta charset="utf-8"><title>Error</title><pre>' . e((string) $error) . '</pre>',
                500,
            );
        }
        try {
            if ($status === 404) {
                return view('site/404', ['title' => t('error_404_title')], 404);
            }
            $key = in_array($status, [400, 403, 405, 413], true) ? (string) $status : '500';
            return view('site/error', [
                'title' => t('error_' . $key . '_title'),
                'message' => t('error_' . $key . '_text'),
            ], $status);
        } catch (\Throwable $e) {
            error_log((string) $e);
            return Response::text($status . ' ' . t($status === 404 ? 'error_404_title' : 'error_500_title') . "\n", $status);
        }
    }
}
