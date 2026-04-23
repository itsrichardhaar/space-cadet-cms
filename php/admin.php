<?php
/**
 * Space Cadet CMS — Admin SPA Shell
 *
 * Serves the Svelte admin UI. Injects CSRF token + current user as a
 * <script> data island so the SPA can hydrate without an extra round-trip.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/cors.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Request.php';
require_once __DIR__ . '/core/Response.php';
require_once __DIR__ . '/core/Auth.php';
require_once __DIR__ . '/models/User.php';

// Security headers appropriate for an HTML page serving a JS SPA.
// Do NOT use sc_send_headers() here — its default-src 'none' CSP is for
// API (JSON) responses only and would block all scripts and styles.
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self' data:; connect-src 'self'; frame-ancestors 'none'");

// Not installed yet — send to installer
if (!file_exists(SC_INSTALLED_LOCK)) {
    header('Location: /install.php');
    exit;
}

// In development, redirect to Vite dev server when dist is not built
if (SC_DEV && !file_exists(__DIR__ . '/dist/index.html')) {
    header('Location: http://localhost:5173/admin/');
    exit;
}

$distIndex = __DIR__ . '/dist/index.html';
if (!file_exists($distIndex)) {
    http_response_code(503);
    echo '<h1>Admin UI not built</h1><p>Run <code>npm run build</code> to compile the admin interface.</p>';
    exit;
}

$request = new Request();

// Auth init may fail if DB is unavailable — gracefully degrade
$csrf     = 'null';
$userJson = 'null';
try {
    Auth::init($request);
    // Generate CSRF token (returns JSON-encoded string)
    $csrf = Auth::csrfToken();
    // Build user island (null if not logged in)
    $user = Auth::user();
    if ($user) {
        $userJson = json_encode([
            'id'          => (int) $user['uid'],
            'email'       => $user['email'],
            'displayName' => $user['display_name'],
            'role'        => $user['role'],
        ], JSON_UNESCAPED_UNICODE);
    }
} catch (Throwable) {
    // DB not ready — SPA will redirect to login which will call /api.php
}

$version = SC_VERSION;

// Read built SPA and inject data island before </head>
$html   = file_get_contents($distIndex);
$island = <<<HTML
<script>
  window.__SC__ = {
    csrf: {$csrf},
    user: {$userJson},
    version: '{$version}'
  };
</script>
HTML;

$html = str_replace('</head>', $island . '</head>', $html);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
echo $html;
