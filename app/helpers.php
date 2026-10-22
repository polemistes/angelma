<?php

declare(strict_types=1);

use Angelma\App;
use Angelma\Auth;
use Angelma\HttpException;
use Angelma\Response;

/** Escape a value for HTML output. */
function e(mixed $value): string
{
    if ($value === null) {
        return '';
    }
    if (is_bool($value)) {
        $value = $value ? '1' : '0';
    }
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

/**
 * Output trusted HTML unchanged. Only for rendered Markdown and the administrator's raw
 * HTML embed.
 */
function raw(string $html): string
{
    return $html;
}

/**
 * Interface string from app/lang/en.php. Placeholders like {name} are replaced with the
 * given parameters (not escaped: escape the result with e() when printing).
 *
 * @param array<string, string|int> $params
 */
function t(string $key, array $params = []): string
{
    static $strings = null;
    if ($strings === null) {
        $strings = require __DIR__ . '/lang/en.php';
    }
    $text = $strings[$key] ?? $key;
    foreach ($params as $name => $value) {
        $text = str_replace('{' . $name . '}', (string) $value, $text);
    }
    return $text;
}

/**
 * URL of a path inside the site, with an optional query string. Paths in the code
 * are written from the site root ('/admin'); the subfolder, if any, is added here.
 *
 * @param array<string, string|int> $query
 */
function url(string $path = '/', array $query = []): string
{
    $path = App::$base . '/' . ltrim($path, '/');
    $query = array_filter($query, static fn ($v): bool => $v !== null && $v !== '');
    return $path . ($query !== [] ? '?' . http_build_query($query) : '');
}

/** Absolute URL, for feeds. */
function absolute_url(string $path = '/'): string
{
    $origin = App::$request?->origin() ?? 'http://localhost';
    return $origin . url($path);
}

function redirect(string $path, int $status = 303): Response
{
    return Response::redirect(str_contains($path, '://') ? $path : url($path), $status);
}

/** Only allow local return addresses, to avoid open redirects. */
function safe_return_path(string $path, string $fallback = '/'): string
{
    if ($path === '' || $path[0] !== '/' || str_starts_with($path, '//') || str_contains($path, '\\')
        || preg_match('/[\x00-\x1f]/', $path)) {
        return $fallback;
    }
    return $path;
}

/** Stop the request with an HTTP error status. */
function abort(int $status): never
{
    throw new HttpException($status);
}

/**
 * Render a template from app/Views to a string.
 *
 * @param array<string, mixed> $data
 */
function render(string $template, array $data = []): string
{
    $file = App::$root . '/app/Views/' . $template . '.php';
    if (!preg_match('#^[a-z0-9_/]+$#', $template) || !is_file($file)) {
        throw new RuntimeException('View not found: ' . $template);
    }
    $level = ob_get_level();
    ob_start();
    try {
        (static function (string $__file, array $__data): void {
            extract($__data, EXTR_SKIP);
            include $__file;
        })($file, $data);
        return (string) ob_get_clean();
    } catch (Throwable $e) {
        while (ob_get_level() > $level) {
            ob_end_clean();
        }
        throw $e;
    }
}

/**
 * Render a template inside a layout. Admin templates use the admin layout, everything
 * else the public site layout. Pass $layout = '' for no layout.
 *
 * @param array<string, mixed> $data
 */
function view(string $template, array $data = [], int $status = 200, ?string $layout = null): Response
{
    $layout ??= str_starts_with($template, 'admin/') ? 'admin' : 'site';
    $html = render($template, $data);
    if ($layout !== '') {
        // The layout's $content is the rendered screen, even if the data has a 'content' key.
        $html = render('layout/' . $layout, ['content' => $html] + $data);
    }
    return new Response($html, $status);
}

/** @param array<string, mixed> $data */
function partial(string $name, array $data = []): string
{
    return render('partials/' . $name, $data);
}

/**
 * Short plain-text description of a box for selects and messages: its type and a
 * glimpse of its content.
 *
 * @param array<string, mixed> $box
 */
function box_label(array $box): string
{
    $type = t('box_type_' . $box['type']);
    $content = is_array($box['content'] ?? null) ? $box['content'] : [];
    $text = match ((string) $box['type']) {
        'text' => (string) ($content['markdown'] ?? ''),
        'image' => (string) (($content['alt'] ?? '') !== '' ? $content['alt'] : ($content['caption'] ?? '')),
        'embed' => (string) ($content['url'] ?? ''),
        default => '',
    };
    $text = trim((string) preg_replace(['/^#+\s*/m', '/\s+/'], ['', ' '], $text));
    return $text === '' ? $type : $type . ': ' . mb_strimwidth($text, 0, 50, '…');
}

/** Store a message shown once on the next page. */
function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

/** @return list<array{type: string, message: string}> messages, removed from the session */
function take_flashes(): array
{
    $messages = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return is_array($messages) ? array_values($messages) : [];
}

/**
 * Turn text into a URL slug. When $taken is given, "-2", "-3" ... is appended until
 * $taken($slug) returns false.
 *
 * @param (callable(string): bool)|null $taken
 */
function slugify(string $text, ?callable $taken = null): string
{
    static $map = [
        'æ' => 'ae', 'ø' => 'o', 'å' => 'a', 'ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'ß' => 'ss',
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ā' => 'a', 'ą' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'ē' => 'e', 'ę' => 'e', 'ě' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'ī' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ō' => 'o', 'ő' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ū' => 'u', 'ů' => 'u', 'ű' => 'u',
        'ý' => 'y', 'ÿ' => 'y', 'ñ' => 'n', 'ń' => 'n', 'ň' => 'n', 'ç' => 'c', 'ć' => 'c',
        'č' => 'c', 'ð' => 'd', 'ď' => 'd', 'þ' => 'th', 'œ' => 'oe', 'ł' => 'l', 'ś' => 's',
        'š' => 's', 'ș' => 's', 'ş' => 's', 'ź' => 'z', 'ż' => 'z', 'ž' => 'z', 'ř' => 'r',
        'ť' => 't', 'ț' => 't', 'ğ' => 'g', 'ı' => 'i',
    ];
    $slug = strtr(mb_strtolower($text, 'UTF-8'), $map);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');
    if (strlen($slug) > 80) {
        $slug = rtrim(substr($slug, 0, 80), '-');
    }

    if ($taken !== null && $slug !== '' && $taken($slug)) {
        $n = 2;
        while ($taken($slug . '-' . $n)) {
            $n++;
        }
        $slug .= '-' . $n;
    }
    return $slug;
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Auth::csrfToken()) . '">';
}

/** Current time as UTC ISO 8601 text, the storage format for timestamps. */
function now(): string
{
    return gmdate('Y-m-d\TH:i:s\Z');
}

/** Display a stored UTC timestamp in the configured timezone and format. */
function format_date(?string $utc, ?string $format = null): string
{
    if ($utc === null || $utc === '') {
        return '';
    }
    try {
        $date = new DateTimeImmutable($utc, new DateTimeZone('UTC'));
        $tz = new DateTimeZone(App::setting('timezone', 'Europe/Oslo'));
    } catch (Exception) {
        return $utc;
    }
    return $date->setTimezone($tz)->format($format ?? App::setting('date_format', 'j F Y'));
}

/**
 * URL of an uploaded image in one of its sizes: 'l' (large, the stored file),
 * 'm' (medium, 1000 px wide) or 's' (small, 400 px wide).
 */
function image_url(string $filename, string $size = 'l'): string
{
    if ($size === 'm' || $size === 's') {
        $dot = strrpos($filename, '.');
        if ($dot !== false) {
            $filename = substr($filename, 0, $dot) . '-' . $size . substr($filename, $dot);
        }
    }
    return url('/uploads/' . $filename);
}

/** A slug is lowercase letters and digits in groups joined by single dashes. */
function valid_slug(string $slug): bool
{
    return strlen($slug) <= 80 && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) === 1;
}

/** A stored UTC timestamp as a datetime-local value (Y-m-d\TH:i) in the site's timezone. */
function utc_to_local_input(?string $utc): string
{
    return $utc === null || $utc === '' ? '' : format_date($utc, 'Y-m-d\TH:i');
}

/** A datetime-local value in the site's timezone as a UTC timestamp, or null if invalid. */
function local_input_to_utc(string $local): ?string
{
    try {
        $tz = new DateTimeZone(App::setting('timezone', 'Europe/Oslo'));
    } catch (Exception) {
        $tz = new DateTimeZone('UTC');
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $local, $tz)
        ?: DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s', $local, $tz);
    if ($date === false) {
        return null;
    }
    return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
}
