<?php
/**
 * Space Cadet CMS — Action/Method Router
 *
 * Maps api.php?action=<action>&method=<VERB> to controller methods.
 * Action segments are slash-delimited; numeric segments become {id}.
 *
 * Pattern examples:
 *   action=collections            method=GET  → CollectionsController::list
 *   action=collections            method=POST → CollectionsController::create
 *   action=collections/5          method=GET  → CollectionsController::show($id=5)
 *   action=collections/5/items    method=GET  → CollectionItemsController::list($id=5)
 *   action=content/collections    method=GET  → CollectionsController::publicList
 */

class Router {

    public static function dispatch(Request $req): void {
        $action = $req->action();
        $method = $req->method();

        // Ping / health check
        if ($action === 'ping') {
            Response::json(['ok' => true, 'version' => SC_VERSION]);
        }

        // GraphQL — hand off entirely
        if ($action === 'graphql') {
            self::loadGraphQL($req);
            return;
        }

        // CSRF — required for all session-auth mutations; skip for API key auth,
        // login (no session yet), and public form submission.
        if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'], true) && !Auth::isApiKey()) {
            $skipCsrf = ($action === 'login') || str_starts_with($action, 'submit/');
            if (!$skipCsrf && !Auth::verifyCsrf($req)) {
                Response::error('Invalid CSRF token', 403, 'CSRF_INVALID');
            }
        }

        // Parse action into segments, detect IDs
        $segments = array_values(array_filter(explode('/', $action)));
        $ids      = [];
        $parts    = [];

        foreach ($segments as $seg) {
            if (ctype_digit($seg)) {
                $ids[]   = (int) $seg;
                $parts[] = '{id}';
            } else {
                $parts[] = $seg;
            }
        }

        $pattern = implode('/', $parts);

        [$controller, $controllerMethod, $argKeys] = self::match($pattern, $method);

        if ($controller === null) {
            Response::notFound("Unknown action: {$action}");
        }

        // Build ordered args from parsed IDs
        $args = [];
        foreach ($argKeys as $i => $_) {
            $args[] = $ids[$i] ?? null;
        }

        $handler = [new $controller(), $controllerMethod];
        $handler($req, ...$args);
    }

    /**
     * Returns [controllerClass, method, argPositions].
     * argPositions is an array where each entry corresponds to one {id} placeholder.
     */
    private static function match(string $pattern, string $method): array {
        $routes = self::routes();
        $key    = "{$pattern}:{$method}";

        if (isset($routes[$key])) {
            return $routes[$key];
        }

        // Method not allowed check
        foreach (['GET','POST','PUT','DELETE','PATCH'] as $m) {
            if ($m !== $method && isset($routes["{$pattern}:{$m}"])) {
                http_response_code(405);
                header("Allow: {$m}");
                Response::error('Method not allowed', 405, 'METHOD_NOT_ALLOWED');
            }
        }

        return [null, null, []];
    }

    private static function routes(): array {
        return [
            // ── Auth ────────────────────────────────────────────────────
            'login:POST'                     => [AuthController::class,           'login',              []],
            'logout:POST'                    => [AuthController::class,           'logout',             []],
            'me:GET'                         => [AuthController::class,           'me',                 []],
            'me/password:POST'               => [AuthController::class,           'changePassword',     []],
            'me/refresh:POST'                => [AuthController::class,           'refresh',            []],

            // ── Users ────────────────────────────────────────────────────
            'users:GET'                      => [UsersController::class,          'list',               []],
            'users:POST'                     => [UsersController::class,          'create',             []],
            'users/{id}:GET'                 => [UsersController::class,          'show',               [0]],
            'users/{id}:PUT'                 => [UsersController::class,          'update',             [0]],
            'users/{id}:DELETE'              => [UsersController::class,          'delete',             [0]],

            // ── Collections ──────────────────────────────────────────────
            'collections:GET'                => [CollectionsController::class,    'list',               []],
            'collections:POST'               => [CollectionsController::class,    'create',             []],
            'collections/{id}:GET'           => [CollectionsController::class,    'show',               [0]],
            'collections/{id}:PUT'           => [CollectionsController::class,    'update',             [0]],
            'collections/{id}:DELETE'        => [CollectionsController::class,    'delete',             [0]],
            'collections/{id}/fields:GET'    => [CollectionsController::class,    'fields',             [0]],
            'collections/{id}/fields:PUT'    => [CollectionsController::class,    'replaceFields',      [0]],

            // ── Collection Items ─────────────────────────────────────────
            'collections/{id}/items:GET'     => [CollectionItemsController::class,'list',               [0]],
            'collections/{id}/items:POST'    => [CollectionItemsController::class,'create',             [0]],
            'collections/{id}/items/bulk:POST'              => [CollectionItemsController::class,'bulk',            [0]],
            'collections/{id}/items/{id}:GET'               => [CollectionItemsController::class,'show',           [0,1]],
            'collections/{id}/items/{id}:PUT'               => [CollectionItemsController::class,'update',         [0,1]],
            'collections/{id}/items/{id}:DELETE'            => [CollectionItemsController::class,'delete',         [0,1]],
            'collections/{id}/items/{id}/duplicate:POST'    => [CollectionItemsController::class,'duplicate',      [0,1]],

            // ── Pages ────────────────────────────────────────────────────
            'pages:GET'                      => [PagesController::class,          'list',               []],
            'pages:POST'                     => [PagesController::class,          'create',             []],
            'pages/reorder:PUT'              => [PagesController::class,          'reorder',            []],
            'pages/{id}:GET'                 => [PagesController::class,          'show',               [0]],
            'pages/{id}:PUT'                 => [PagesController::class,          'update',             [0]],
            'pages/{id}:DELETE'              => [PagesController::class,          'delete',             [0]],
            'pages/{id}/duplicate:POST'      => [PagesController::class,          'duplicate',          [0]],

            // ── Globals ──────────────────────────────────────────────────
            'globals:GET'                    => [GlobalsController::class,        'list',               []],
            'globals:POST'                   => [GlobalsController::class,        'create',             []],
            'globals/{id}:GET'               => [GlobalsController::class,        'show',               [0]],
            'globals/{id}:PUT'               => [GlobalsController::class,        'update',             [0]],
            'globals/{id}:DELETE'            => [GlobalsController::class,        'delete',             [0]],
            'globals/{id}/fields:PUT'        => [GlobalsController::class,        'replaceFields',      [0]],

            // ── Media ────────────────────────────────────────────────────
            'media:GET'                      => [MediaController::class,          'list',               []],
            'media:POST'                     => [MediaController::class,          'upload',             []],
            'media/{id}:GET'                 => [MediaController::class,          'show',               [0]],
            'media/{id}:PUT'                 => [MediaController::class,          'update',             [0]],
            'media/{id}:DELETE'              => [MediaController::class,          'delete',             [0]],

            // ── Menus ────────────────────────────────────────────────────
            'menus:GET'                      => [MenusController::class,          'list',               []],
            'menus:POST'                     => [MenusController::class,          'create',             []],
            'menus/{id}:GET'                 => [MenusController::class,          'show',               [0]],
            'menus/{id}:PUT'                 => [MenusController::class,          'update',             [0]],
            'menus/{id}:DELETE'              => [MenusController::class,          'delete',             [0]],
            'menus/{id}/items:PUT'           => [MenusController::class,          'replaceItems',       [0]],

            // ── Forms ────────────────────────────────────────────────────
            'forms:GET'                      => [FormsController::class,          'list',               []],
            'forms:POST'                     => [FormsController::class,          'create',             []],
            'forms/{id}:GET'                 => [FormsController::class,          'show',               [0]],
            'forms/{id}:PUT'                 => [FormsController::class,          'update',             [0]],
            'forms/{id}:DELETE'              => [FormsController::class,          'delete',             [0]],
            'forms/{id}/fields:PUT'          => [FormsController::class,          'replaceFields',      [0]],
            'forms/{id}/submissions:GET'     => [FormsController::class,          'submissions',        [0]],
            'forms/{id}/submissions/{id}:GET'    => [FormsController::class,      'showSubmission',     [0,1]],
            'forms/{id}/submissions/{id}:PUT'    => [FormsController::class,      'updateSubmission',   [0,1]],
            'forms/{id}/submissions/{id}:DELETE' => [FormsController::class,      'deleteSubmission',   [0,1]],
            'forms/{id}/submissions/export:GET'  => [FormsController::class,      'exportSubmissions',  [0]],

            // ── Webhooks ─────────────────────────────────────────────────
            'webhooks:GET'                   => [WebhooksController::class,       'list',               []],
            'webhooks:POST'                  => [WebhooksController::class,       'create',             []],
            'webhooks/{id}:GET'              => [WebhooksController::class,       'show',               [0]],
            'webhooks/{id}:PUT'              => [WebhooksController::class,       'update',             [0]],
            'webhooks/{id}:DELETE'           => [WebhooksController::class,       'delete',             [0]],
            'webhooks/{id}/test:POST'        => [WebhooksController::class,       'test',               [0]],
            'webhooks/{id}/deliveries:GET'   => [WebhooksController::class,       'deliveries',         [0]],

            // ── Templates ────────────────────────────────────────────────
            'templates:GET'                  => [TemplatesController::class,      'list',               []],
            'templates:POST'                 => [TemplatesController::class,      'create',             []],
            'templates/{id}:GET'             => [TemplatesController::class,      'show',               [0]],
            'templates/{id}:PUT'             => [TemplatesController::class,      'update',             [0]],
            'templates/{id}:DELETE'          => [TemplatesController::class,      'delete',             [0]],

            // ── API Keys ─────────────────────────────────────────────────
            'api-keys:GET'                   => [ApiKeysController::class,        'list',               []],
            'api-keys:POST'                  => [ApiKeysController::class,        'create',             []],
            'api-keys/{id}:DELETE'           => [ApiKeysController::class,        'delete',             [0]],

            // ── Folders ──────────────────────────────────────────────────
            'folders:GET'                    => [FoldersController::class,        'list',               []],
            'folders:POST'                   => [FoldersController::class,        'create',             []],
            'folders/{id}:PUT'               => [FoldersController::class,        'update',             [0]],
            'folders/{id}:DELETE'            => [FoldersController::class,        'delete',             [0]],

            // ── Labels ───────────────────────────────────────────────────
            'labels:GET'                     => [LabelsController::class,         'list',               []],
            'labels:POST'                    => [LabelsController::class,         'create',             []],
            'labels/{id}:PUT'                => [LabelsController::class,         'update',             [0]],
            'labels/{id}:DELETE'             => [LabelsController::class,         'delete',             [0]],

            // ── Search ───────────────────────────────────────────────────
            'search:GET'                     => [SearchController::class,         'search',             []],

            // ── Blueprint AI ─────────────────────────────────────────────
            'blueprint/analyze:POST'             => [BlueprintController::class,  'analyze',            []],
            'blueprint/jobs/{id}:GET'            => [BlueprintController::class,  'jobStatus',          [0]],
            'blueprint/jobs/{id}/apply:POST'     => [BlueprintController::class,  'apply',              [0]],

            // ── Compass ──────────────────────────────────────────────────
            'compass/{id}/schema:GET'        => [CompassController::class,        'schema',             [0]],

            // ── Members (admin management) ───────────────────────────────
            'members:GET'                    => [MembersController::class,        'list',               []],
            'members:POST'                   => [MembersController::class,        'create',             []],
            'members/bulk:POST'              => [MembersController::class,        'bulk',               []],
            'members/{id}:GET'               => [MembersController::class,        'show',               [0]],
            'members/{id}:PUT'               => [MembersController::class,        'update',             [0]],
            'members/{id}:DELETE'            => [MembersController::class,        'delete',             [0]],

            // ── Settings ─────────────────────────────────────────────────
            'settings:GET'                   => [SettingsController::class,       'list',               []],
            'settings:PUT'                   => [SettingsController::class,       'update',             []],

            // ── Stats ─────────────────────────────────────────────────────
            'stats:GET'                      => [SettingsController::class,       'stats',              []],

            // ── Audit log ────────────────────────────────────────────────
            'audit-log:GET'                  => [SettingsController::class,       'auditLog',           []],

            // ── Content API (Public, read-only) ──────────────────────────
            'content/collections:GET'        => [CollectionsController::class,    'publicList',         []],
            'content/collections/{id}:GET'   => [CollectionsController::class,    'publicShow',         [0]],
            'content/collections/{id}/items:GET'      => [CollectionItemsController::class,'publicList', [0]],
            'content/collections/{id}/items/{id}:GET' => [CollectionItemsController::class,'publicShow', [0,1]],
            'content/pages:GET'              => [PagesController::class,          'publicList',         []],
            'content/pages/{id}:GET'         => [PagesController::class,          'publicShow',         [0]],
            'content/globals/{id}:GET'       => [GlobalsController::class,        'publicShow',         [0]],
            'content/menus/{id}:GET'         => [MenusController::class,          'publicShow',         [0]],
            'content/search:GET'             => [SearchController::class,         'publicSearch',       []],
            'submit/{id}:POST'               => [FormsController::class,          'publicSubmit',       [0]],
        ];
    }

    private static function loadGraphQL(Request $req): void {
        require_once __DIR__ . '/../graphql/GraphQL.php';
        GraphQL::handle($req);
    }
}
