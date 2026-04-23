<?php
/**
 * Space Cadet CMS — API Entry Point
 *
 * All requests route through: api.php?action=<endpoint>&method=<VERB>
 */

declare(strict_types=1);

// Bootstrap
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/cors.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Request.php';
require_once __DIR__ . '/core/Response.php';
require_once __DIR__ . '/core/Auth.php';
require_once __DIR__ . '/core/RateLimit.php';
require_once __DIR__ . '/core/Router.php';
require_once __DIR__ . '/core/Validator.php';
require_once __DIR__ . '/core/EventEmitter.php';
require_once __DIR__ . '/core/Cache.php';

// Models
require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/models/Collection.php';
require_once __DIR__ . '/models/CollectionItem.php';
require_once __DIR__ . '/models/Page.php';
require_once __DIR__ . '/models/GlobalGroup.php';
require_once __DIR__ . '/models/Menu.php';
require_once __DIR__ . '/models/Media.php';
require_once __DIR__ . '/models/Form.php';
require_once __DIR__ . '/models/FormSubmission.php';
require_once __DIR__ . '/models/Webhook.php';
require_once __DIR__ . '/models/ApiKey.php';
require_once __DIR__ . '/models/Folder.php';
require_once __DIR__ . '/models/Label.php';
require_once __DIR__ . '/models/Template.php';
require_once __DIR__ . '/models/SearchIndex.php';
require_once __DIR__ . '/models/AuditLog.php';

// Controllers
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/UsersController.php';
require_once __DIR__ . '/controllers/CollectionsController.php';
require_once __DIR__ . '/controllers/CollectionItemsController.php';
require_once __DIR__ . '/controllers/PagesController.php';
require_once __DIR__ . '/controllers/GlobalsController.php';
require_once __DIR__ . '/controllers/MenusController.php';
require_once __DIR__ . '/controllers/MediaController.php';
require_once __DIR__ . '/controllers/FormsController.php';
require_once __DIR__ . '/controllers/WebhooksController.php';
require_once __DIR__ . '/controllers/SearchController.php';
require_once __DIR__ . '/controllers/TemplatesController.php';
require_once __DIR__ . '/controllers/ApiKeysController.php';
require_once __DIR__ . '/controllers/FoldersController.php';
require_once __DIR__ . '/controllers/LabelsController.php';
require_once __DIR__ . '/controllers/CompassController.php';
require_once __DIR__ . '/controllers/BlueprintController.php';
require_once __DIR__ . '/controllers/MembersController.php';
require_once __DIR__ . '/controllers/SettingsController.php';

// One-time migration: rename forge_jobs → blueprint_jobs (Blueprint AI rename)
if (Database::queryOne("SELECT name FROM sqlite_master WHERE type='table' AND name='forge_jobs'")) {
    Database::execute("ALTER TABLE forge_jobs RENAME TO blueprint_jobs");
}

// Determine if this is a public content API request
$action = $_GET['action'] ?? '';
$isContentApi = str_starts_with($action, 'content/') || str_starts_with($action, 'submit/');

// Send security / CORS headers
sc_send_headers($isContentApi);

// Check installation
if (!file_exists(SC_INSTALLED_LOCK) && $action !== 'ping') {
    Response::error('CMS not installed. Please visit /install.php', 503, 'NOT_INSTALLED');
}

$request = new Request();

// Initialise auth (sets global current user, doesn't abort on failure)
Auth::init($request);

// Dispatch
try {
    Router::dispatch($request);
} catch (Throwable $e) {
    $msg  = SC_DEV ? $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() : 'Internal server error';
    $code = SC_DEV ? 500 : 500;
    Response::error($msg, $code, 'SERVER_ERROR');
}
