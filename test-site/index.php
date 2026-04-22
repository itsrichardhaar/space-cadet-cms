<?php
/**
 * Space Cadet CMS — Test Site Front Controller
 * Routes all requests to the CMS or serves the frontend.
 *
 * In this test-site setup:
 *   - The CMS lives at /space-cadet/
 *   - The public site template lives at the root
 *   - Direct to admin: http://localhost:8888/space-cadet/admin.php
 */

// Check if this is an API or admin request
$uri = $_SERVER['REQUEST_URI'] ?? '/';

// Strip query string for routing
$path = strtok($uri, '?');

// Pass API/admin/install requests to the CMS
if (str_starts_with($path, '/space-cadet/')) {
    // Let Apache/Nginx handle — this file only handles root requests
    require_once __DIR__ . '/space-cadet/api.php';
    exit;
}

// Default: serve the test site homepage
$cmsRoot = __DIR__ . '/space-cadet';
$installed = file_exists($cmsRoot . '/storage/INSTALLED');

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Space Cadet CMS — Test Site</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f9f9f8;
            color: #1a1a1a;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .container {
            max-width: 520px;
            width: 100%;
            text-align: center;
        }
        .logo {
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #888;
            margin-bottom: 2.5rem;
        }
        h1 {
            font-size: 2rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            margin-bottom: 0.75rem;
            color: #111;
        }
        p {
            font-size: 1rem;
            color: #555;
            line-height: 1.6;
            margin-bottom: 2rem;
        }
        .links {
            display: flex;
            gap: 0.75rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            padding: 0.6rem 1.25rem;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            transition: background 0.15s, color 0.15s;
        }
        .btn-primary {
            background: #111;
            color: #fff;
        }
        .btn-primary:hover { background: #333; }
        .btn-secondary {
            background: #efefee;
            color: #333;
        }
        .btn-secondary:hover { background: #e2e2e0; }
        .status {
            margin-top: 3rem;
            padding: 1rem 1.25rem;
            border-radius: 8px;
            background: #fff;
            border: 1px solid #eee;
            font-size: 0.8125rem;
            color: #666;
            text-align: left;
        }
        .status strong { color: #111; }
        .status-row {
            display: flex;
            justify-content: space-between;
            padding: 0.2rem 0;
        }
        .dot {
            display: inline-block;
            width: 7px;
            height: 7px;
            border-radius: 50%;
            margin-right: 6px;
            vertical-align: middle;
        }
        .dot-green { background: #22c55e; }
        .dot-yellow { background: #f59e0b; }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">Space Cadet CMS</div>
        <h1>Test Site</h1>
        <p>This is the local development test environment for Space Cadet CMS. Use the links below to access the CMS admin panel or run the installer.</p>

        <div class="links">
            <?php if ($installed): ?>
                <a href="/space-cadet/admin.php" class="btn btn-primary">Open Admin</a>
                <a href="/space-cadet/api.php?action=ping" class="btn btn-secondary">API Ping</a>
            <?php else: ?>
                <a href="/space-cadet/install.php" class="btn btn-primary">Run Installer</a>
            <?php endif; ?>
            <a href="https://github.com/itsrichardhaar/space-cadet-cms" class="btn btn-secondary">GitHub</a>
        </div>

        <div class="status">
            <div class="status-row">
                <span><strong>CMS Status</strong></span>
                <span>
                    <?php if ($installed): ?>
                        <span class="dot dot-green"></span>Installed
                    <?php else: ?>
                        <span class="dot dot-yellow"></span>Not installed
                    <?php endif; ?>
                </span>
            </div>
            <div class="status-row">
                <span>PHP Version</span>
                <span><?= PHP_VERSION ?></span>
            </div>
            <div class="status-row">
                <span>SQLite</span>
                <span><?= extension_loaded('pdo_sqlite') ? 'Available' : 'Missing' ?></span>
            </div>
            <div class="status-row">
                <span>GD (images)</span>
                <span><?= extension_loaded('gd') ? 'Available' : 'Missing' ?></span>
            </div>
            <div class="status-row">
                <span>Database</span>
                <span><?= file_exists($cmsRoot . '/storage/db/space-cadet.sqlite') ? 'space-cadet.sqlite' : 'Not created yet' ?></span>
            </div>
            <div class="status-row">
                <span>CMS Version</span>
                <span><?php
                    $configFile = $cmsRoot . '/config/app.php';
                    if (file_exists($configFile)) {
                        preg_match("/define\('SC_VERSION',\s*'([^']+)'\)/", file_get_contents($configFile), $m);
                        echo $m[1] ?? 'unknown';
                    } else {
                        echo 'config not found';
                    }
                ?></span>
            </div>
        </div>
    </div>
</body>
</html>
