<?php

declare(strict_types=1);

/*
 * Router for the PHP built-in server. Reproduces the rules of the .htaccess files
 * so local behaviour matches Apache. Start with dev/server.sh. To serve an extracted
 * release zip instead of this repository, set ANGELMA_ROOT to its folder; to serve the
 * site from a subfolder, set ANGELMA_BASE, e.g. ANGELMA_BASE=/site.
 */

// ANGELMA_ROOT lets the router serve another copy, e.g. an extracted release zip.
$root = getenv('ANGELMA_ROOT') ?: dirname(__DIR__);
$path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
$path = '/' . ltrim(str_replace('\\', '/', $path), '/');

// ANGELMA_BASE=/folder serves the site from a subfolder, as when it is extracted into
// www/folder/ on the host. Everything outside the folder is 404.
$base = rtrim((string) getenv('ANGELMA_BASE'), '/');
if ($base !== '') {
    if ($path === $base) {
        header('Location: ' . $base . '/', true, 301);
        exit;
    }
    if (!str_starts_with($path, $base . '/')) {
        http_response_code(404);
        echo "404 Not Found (the site is in $base/)\n";
        exit;
    }
    $path = substr($path, strlen($base));
}

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

// Root .htaccess: blocked folders, hidden files and folders except .well-known, and file types.
if (preg_match('#^/(app|data|vendor|dev|tests)(/|$)#', $path)
    || preg_match('#/\.(?!well-known(/|$))#', $path)
    || preg_match('#(^|/)composer\.[^/]*$#', $path)
    || preg_match('#\.(sqlite|lock|md|json|sh|xml|dist)$#', $path)) {
    $forbidden();
}

// uploads/.htaccess: no scripts.
if (preg_match('#^/uploads/#', $path) && preg_match('#\.(php|phtml|phar|pl|py|cgi|sh)$#i', $path)) {
    $forbidden();
}

// Served directly: existing files inside a folder (assets/, uploads/, install/ and folders
// of your own), existing folders with an index file, and robots.txt. Angelma's own
// addresses always go to the front controller, even when a folder of that name exists.
$file = $root . $path;
$direct = false;
if (preg_match('#^/(admin|blog|login|logout)(/|$)#', $path) !== 1) {
    if ($path !== '/' && is_dir($file)) {
        foreach (['index.php', 'index.html'] as $name) {
            if (is_file(rtrim($file, '/') . '/' . $name)) {
                if (!str_ends_with($path, '/')) {
                    header('Location: ' . $base . $path . '/', true, 301);
                    exit;
                }
                $file .= $name;
                $direct = true;
                break;
            }
        }
    } elseif (is_file($file)) {
        $direct = preg_match('#^/[^/]+/.#', $path) === 1 || $path === '/robots.txt';
    }
}

if ($direct) {
    $securityHeaders();
    if (str_ends_with($file, '.php')) {
        $_SERVER['SCRIPT_NAME'] = $base . substr($file, strlen($root));
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
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'pdf' => 'application/pdf',
        'mp3' => 'audio/mpeg',
        'mp4' => 'video/mp4',
    ];
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
    // The same file types as the FilesMatch for the CSP in .htaccess.
    if (preg_match('/^(css|js|png|jpe?g|gif|webp|svg|ico|txt)$/', $ext) === 1) {
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https:; frame-src https://www.youtube.com "
            . "https://player.vimeo.com https://www.google.com https://www.openstreetmap.org; style-src 'self' 'unsafe-inline'; "
            . "script-src 'self'");
    }
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
$_SERVER['SCRIPT_NAME'] = $base . '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $root . '/index.php';
chdir($root);
require $root . '/index.php';
return true;
