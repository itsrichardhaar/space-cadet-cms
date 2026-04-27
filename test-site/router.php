<?php
/**
 * Space Cadet CMS — Test Site Router
 *
 * Used with: php -S localhost:8888 router.php -t test-site/
 *   (or just: npm run test-site)
 *
 * Routing:
 *   /                             → test-site/index.php (welcome page)
 *   /space-cadet/admin or /admin  → space-cadet/admin.php (SPA shell)
 *   /admin/_app/*                 → space-cadet/dist/_app/* (SPA assets)
 *   /space-cadet/api.php          → space-cadet/api.php (REST/GraphQL)
 *   /space-cadet/install.php      → space-cadet/install.php
 *   everything else               → space-cadet/frontend.php (front-end page renderer)
 */

$uri  = $_SERVER['REQUEST_URI'] ?? '/';
$path = strtok($uri, '?');
$root = __DIR__;
$cms  = $root . '/space-cadet';

// ── Security: block sensitive CMS internals ──────────────────
$blockedPrefixes = [
    '/space-cadet/storage/db/',
    '/space-cadet/storage/cache/',
    '/space-cadet/storage/INSTALLED',
    '/space-cadet/config/local.php',
    '/space-cadet/models/',
    '/space-cadet/controllers/',
    '/space-cadet/graphql/',
    '/space-cadet/core/',
    '/space-cadet/forge/',
    '/space-cadet/media/',
];
foreach ($blockedPrefixes as $prefix) {
    if (str_starts_with($path, $prefix)) {
        http_response_code(403);
        exit('Forbidden');
    }
}

// Block install.php once installed
if (
    (str_contains($path, '/install.php')) &&
    file_exists($cms . '/storage/INSTALLED')
) {
    http_response_code(403);
    exit('Forbidden');
}

// ── SPA assets: /admin/_app/* → space-cadet/dist/_app/* ──────
if (str_starts_with($path, '/admin/_app/')) {
    $asset = $cms . '/dist/_app/' . substr($path, strlen('/admin/_app/'));
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
    $asset = $cms . '/dist/favicon.ico';
    if (file_exists($asset)) {
        header('Content-Type: image/x-icon');
        readfile($asset);
    } else {
        http_response_code(404);
    }
    exit;
}

// ── Admin SPA: /admin, /admin/*, /admin.php, /space-cadet/admin.php ──
if (
    $path === '/admin' ||
    $path === '/admin.php' ||
    str_starts_with($path, '/admin/') ||
    $path === '/space-cadet/admin.php'
) {
    // Set SERVER variables so admin.php paths resolve correctly
    $_SERVER['DOCUMENT_ROOT'] = $cms;
    chdir($cms);
    require $cms . '/admin.php';
    exit;
}

// ── CMS API: /api.php, /space-cadet/api.php ──────────────────
if ($path === '/api.php' || $path === '/space-cadet/api.php') {
    chdir($cms);
    require $cms . '/api.php';
    exit;
}

// ── Installer: /install.php, /space-cadet/install.php ────────
if ($path === '/install.php' || $path === '/space-cadet/install.php') {
    chdir($cms);
    require $cms . '/install.php';
    exit;
}

// ── Static files ──────────────────────────────────────────────
$file = $root . $path;
if ($path !== '/' && file_exists($file) && is_file($file)) {
    return false; // let built-in server handle
}

// ── Site assets: /assets/{file.css|js} ───────────────────────
if (str_starts_with($path, '/assets/')) {
    $filename = basename($path); // basename prevents path traversal
    if (preg_match('/^[a-z0-9][a-z0-9\-]*\.(css|js)$/', $filename)) {
        $file = $cms . '/storage/assets/' . $filename;
        if (file_exists($file)) {
            $mime = str_ends_with($filename, '.css') ? 'text/css' : 'application/javascript';
            header("Content-Type: $mime; charset=utf-8");
            header("Cache-Control: public, max-age=3600");
            readfile($file);
            exit;
        }
    }
    http_response_code(404);
    exit;
}

// ── RSS Feeds: /feed/{id} → feeds/{id} API action ────────────
if (preg_match('#^/feed/(\d+)(?:\.xml)?$#', $path, $m)) {
    $_GET['action'] = 'feeds/' . $m[1];
    chdir($cms);
    require $cms . '/api.php';
    exit;
}

// ── Front-end page routing → page renderer ───────────────────
// Includes "/" which frontend.php maps to slug "home"
chdir($cms);
require $cms . '/frontend.php';
