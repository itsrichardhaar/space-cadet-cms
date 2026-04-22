<?php
/**
 * Space Cadet CMS — PHP Built-in Server Router
 *
 * Used with: php -S localhost:8000 router.php -t php/
 *
 * Maps incoming requests to the correct file — mirrors what
 * the Apache .htaccess rules do, since the built-in server
 * does not process .htaccess.
 */

$uri  = $_SERVER['REQUEST_URI'] ?? '/';
$path = strtok($uri, '?');           // strip query string
$root = __DIR__;

// ── Security: block sensitive paths ──────────────────────────
$blocked = [
    '/storage/db/',
    '/storage/cache/',
    '/storage/INSTALLED',
    '/config/local.php',
    '/models/',
    '/controllers/',
    '/graphql/',
    '/core/',
    '/forge/',
    '/media/',
];
foreach ($blocked as $prefix) {
    if (str_starts_with($path, $prefix)) {
        http_response_code(403);
        exit('Forbidden');
    }
}

// Block install.php once installed
if ($path === '/install.php' && file_exists($root . '/storage/INSTALLED')) {
    http_response_code(403);
    exit('Forbidden');
}

// ── SPA assets: /admin/_app/* → dist/_app/* ──────────────────
if (str_starts_with($path, '/admin/_app/')) {
    $asset = $root . '/dist/_app/' . substr($path, strlen('/admin/_app/'));
    if (file_exists($asset)) {
        $mime = match (pathinfo($asset, PATHINFO_EXTENSION)) {
            'js'   => 'application/javascript',
            'css'  => 'text/css',
            'map'  => 'application/json',
            default => 'application/octet-stream',
        };
        header("Content-Type: $mime");
        readfile($asset);
        exit;
    }
    http_response_code(404);
    exit;
}

if ($path === '/admin/favicon.ico') {
    $asset = $root . '/dist/favicon.ico';
    if (file_exists($asset)) {
        header('Content-Type: image/x-icon');
        readfile($asset);
        exit;
    }
    http_response_code(404);
    exit;
}

// ── Admin SPA shell: /admin or /admin/* → admin.php ──────────
if ($path === '/admin' || str_starts_with($path, '/admin/')) {
    require $root . '/admin.php';
    exit;
}

// ── Static files: serve directly if they exist ───────────────
$file = $root . $path;
if ($path !== '/' && file_exists($file) && is_file($file)) {
    return false; // let built-in server serve it
}

// ── Everything else → api.php (front-end page routing) ───────
require $root . '/api.php';
