<?php

declare(strict_types=1);

/*
 * Router for the PHP built-in server. Reproduces the rules of the .htaccess files
 * so local behaviour matches Apache. Start with dev/server.sh. To serve an extracted
 * release zip instead of this repository, set ANGELMA_ROOT to its folder.
 */

// ANGELMA_ROOT lets the router serve another copy, e.g. an extracted release zip.
$root = getenv('ANGELMA_ROOT') ?: dirname(__DIR__);
$path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
$path = '/' . ltrim(str_replace('\\', '/', $path), '/');

$securityHeaders = static function (): void {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
};

$forbidden = static function () use ($securityHeaders): never {
    http_response_code(403);
    $securityHeaders();
    header('Content-Type: text/plain; charset=utf-8');
    echo "403 Forbidden\n";
    exit;
};

// Never allow path traversal.
if (str_contains($path, '/../') || str_ends_with($path, '/..') || str_contains($path, "\0")) {
    $forbidden();
}

// Root .htaccess: blocked folders and file types.
if (preg_match('#^/(app|data|vendor|dev|tests)(/|$)#', $path)
    || preg_match('#(^|/)composer\.[^/]*$#', $path)
    || preg_match('#\.(sqlite|lock|md|json|sh|xml|dist)$#', $path)
    || preg_match('#(^|/)\.ht#', $path)) {
    $forbidden();
}

// uploads/.htaccess: no scripts.
if (preg_match('#^/uploads/#', $path) && preg_match('#\.(php|phtml|phar|pl|py|cgi|sh)$#i', $path)) {
    $forbidden();
}

$file = $root . $path;
$direct = preg_match('#^/(assets|uploads|install)(/|$)#', $path) === 1 || $path === '/robots.txt';

if ($direct && is_dir($file)) {
    if (!str_ends_with($path, '/')) {
        header('Location: ' . $path . '/', true, 301);
        exit;
    }
    $file .= 'index.php';
    if (!is_file($file)) {
        $file = substr($file, 0, -strlen('index.php')) . 'index.html';
    }
}

if ($direct && is_file($file)) {
    $securityHeaders();
    if (str_ends_with($file, '.php')) {
        $_SERVER['SCRIPT_NAME'] = substr($file, strlen($root));
        $_SERVER['SCRIPT_FILENAME'] = $file;
        chdir(dirname($file));
        require $file;
        return true;
    }
    $types = [
        'css' => 'text/css; charset=utf-8',
        'js' => 'text/javascript; charset=utf-8',
        'html' => 'text/html; charset=utf-8',
        'txt' => 'text/plain; charset=utf-8',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'ico' => 'image/x-icon',
        'woff2' => 'font/woff2',
    ];
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
    header('Content-Length: ' . filesize($file));
    if (str_starts_with($path, '/uploads/') && in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
        header('Content-Disposition: inline');
        header('Cache-Control: public, max-age=31536000, immutable');
    }
    readfile($file);
    return true;
}

// Everything else goes to the front controller.
$securityHeaders();
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $root . '/index.php';
chdir($root);
require $root . '/index.php';
return true;
