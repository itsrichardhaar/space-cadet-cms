<?php
/**
 * Space Cadet CMS — Application Configuration
 */

define('SC_VERSION', '0.2.3');
define('SC_ROOT', dirname(__DIR__));
define('SC_STORAGE', SC_ROOT . '/storage');
define('SC_UPLOADS', SC_STORAGE . '/uploads');
define('SC_THUMBS', SC_STORAGE . '/thumbnails');
define('SC_CACHE', SC_STORAGE . '/cache');
define('SC_DB_PATH', SC_STORAGE . '/db/space-cadet.sqlite');
define('SC_INSTALLED_LOCK', SC_ROOT . '/storage/INSTALLED');

// Environment detection
define('SC_DEV', (
    isset($_SERVER['REMOTE_ADDR']) &&
    in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'], true)
));

// Session config
define('SC_SESSION_NAME', 'sc_session');
define('SC_SESSION_TTL', 86400); // 24 hours

// Rate limits
define('SC_RATE_CONTENT', 120);      // per 60 seconds (content API)
define('SC_RATE_WINDOW_CONTENT', 60);
define('SC_RATE_ADMIN', 60);         // per 60 seconds (admin mutations)
define('SC_RATE_WINDOW_ADMIN', 60);
define('SC_RATE_LOGIN', 5);          // failed login attempts per 60 seconds
define('SC_RATE_WINDOW_LOGIN', 60);

// Media
define('SC_MAX_UPLOAD_BYTES', 10 * 1024 * 1024); // 10 MB
define('SC_ALLOWED_MIME', [
    'image/jpeg', 'image/png', 'image/gif', 'image/webp',
    'image/avif', 'image/svg+xml',
    'application/pdf',
    'video/mp4', 'video/webm',
]);
define('SC_THUMB_WIDTH', 400);
define('SC_THUMB_HEIGHT', 300);
define('SC_MAX_IMAGE_DIM', 2400);
define('SC_WEBP_QUALITY', 85);

// GraphQL limits
define('SC_GQL_MAX_DEPTH', 10);
define('SC_GQL_MAX_SELECTIONS', 500);
define('SC_GQL_MAX_QUERY_SIZE', 32768); // 32 KB

// Load local override first — install.php writes SC_SECRET here
$localConfig = SC_ROOT . '/config/local.php';
if (file_exists($localConfig)) {
    require_once $localConfig;
}

// Fallback secret if not yet installed
if (!defined('SC_SECRET')) {
    define('SC_SECRET', 'change-me-run-install');
}
