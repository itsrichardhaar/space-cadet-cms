<?php
/**
 * Space Cadet CMS — Front-end Page Renderer
 *
 * Handles public-facing page requests by looking up the page by slug,
 * rendering it with its assigned template (if any), or wrapping the page
 * through the active theme layout, or falling back to a plain HTML shell.
 *
 * Invoked by test-site/router.php for any path that isn't the admin,
 * API, or a static file.
 *
 * URL format:  http://localhost:8888/{slug}
 * Home page:   http://localhost:8888/  → slug "home"
 *
 * Rendering priority:
 *   1. Theme layout (when an active theme directory exists)
 *   2. Legacy template (template_id — kept for backwards compat)
 *   3. Fallback plain HTML shell
 */

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Cache.php';
require_once __DIR__ . '/models/Page.php';
require_once __DIR__ . '/models/Template.php';
require_once __DIR__ . '/templates/Compiler.php';
require_once __DIR__ . '/templates/Sandbox.php';
require_once __DIR__ . '/templates/Engine.php';
require_once __DIR__ . '/theme/ThemeLoader.php';
require_once __DIR__ . '/theme/ThemeRenderer.php';

// ── Check installation ────────────────────────────────────────────────────────
if (!file_exists(SC_INSTALLED_LOCK)) {
    header('Location: /space-cadet/install.php');
    exit;
}

// ── Migration: pages.layout column ───────────────────────────────────────────
$pageCols = array_column(Database::query("PRAGMA table_info(pages)"), 'name');
if (!in_array('layout', $pageCols, true)) {
    Database::execute("ALTER TABLE pages ADD COLUMN layout TEXT");
}

// ── Parse slug from URL ───────────────────────────────────────────────────────
$uri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$slug = trim(strtolower($uri), '/');
if ($slug === '') $slug = 'home';

// Only allow slug-safe characters (letters, digits, hyphens, slashes for nested paths)
if (!preg_match('/^[a-z0-9][a-z0-9\-\/]*$/', $slug)) {
    send404($slug);
}

// ── Look up page ──────────────────────────────────────────────────────────────
$page = Page::findBySlug($slug);

if (!$page || $page['status'] !== 'published') {
    send404($slug);
}

// ── Build template context ────────────────────────────────────────────────────
$fields  = $page['fields'] ?? [];
$context = array_merge($fields, [
    'title'      => $page['title'],
    'slug'       => $page['slug'],
    'meta_title' => $page['meta_title'] ?: $page['title'],
    'meta_desc'  => $page['meta_desc']  ?? '',
    'page'       => $page,
]);

// ── Render: Theme layout → Legacy template → Fallback shell ──────────────────

// 1. Try the active theme
$themeName = ThemeLoader::activeThemeName();
$loader    = ThemeLoader::forActiveTheme();

if ($loader) {
    $renderer = new ThemeRenderer($loader, $themeName);
    $html     = $renderer->render($page);

    if ($html !== '') {
        echo $html;
        exit;
    }
}

// 2. Legacy template (template_id) — kept for backwards compatibility
if (!empty($page['template_id'])) {
    $tpl = Template::findById((int) $page['template_id']);
    if ($tpl) {
        $engine = new Engine();
        echo $engine->render($tpl['slug'], $context);
        exit;
    }
}

// 3. No theme and no template — render a plain shell so the page is still previewable
echo fallbackHtml($context);

// ── Helpers ───────────────────────────────────────────────────────────────────

function fallbackHtml(array $ctx): string
{
    $title  = e($ctx['meta_title'] ?: $ctx['title']);
    $h1     = e($ctx['title']);
    $desc   = e($ctx['meta_desc'] ?? '');
    $slug   = e($ctx['slug']);

    // Render field values as a simple table
    $fieldRows = '';
    foreach ($ctx['page']['fields'] ?? [] as $k => $v) {
        $key = e($k);
        $val = e(is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : (string) $v);
        $fieldRows .= "<tr><th>{$key}</th><td>{$val}</td></tr>\n";
    }

    $fieldsSection = $fieldRows
        ? "<h2 class=\"section-label\">Fields</h2><table class=\"fields-table\">{$fieldRows}</table>"
        : '<p class="hint">No custom fields defined for this page.</p>';

    $descMeta  = $desc ? "<meta name=\"description\" content=\"{$desc}\">" : '';

    return <<<HTML
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>{$title}</title>
  {$descMeta}
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; max-width: 720px; margin: 60px auto; padding: 0 24px; color: #1a1a1a; background: #fff; }
    h1 { font-size: 32px; font-weight: 700; margin: 0 0 6px; }
    .meta { font-size: 13px; color: #888; margin: 0 0 32px; }
    .section-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #aaa; margin: 32px 0 10px; }
    .fields-table { width: 100%; border-collapse: collapse; background: #f9f9f7; border-radius: 8px; overflow: hidden; }
    .fields-table th { text-align: left; padding: 8px 14px; font-size: 12px; font-weight: 600; color: #888; border-bottom: 1px solid #eee; width: 160px; }
    .fields-table td { padding: 8px 14px; font-size: 13px; border-bottom: 1px solid #eee; word-break: break-word; }
    .fields-table tr:last-child th, .fields-table tr:last-child td { border-bottom: none; }
    .notice { margin-top: 40px; padding: 14px 18px; background: #fffbf0; border: 1px solid #ffe082; border-radius: 8px; font-size: 13px; color: #7a6000; }
    .notice a { color: #7a6000; font-weight: 600; }
    .hint { font-size: 13px; color: #aaa; }
  </style>
</head>
<body>
  <h1>{$h1}</h1>
  <p class="meta">/{$slug}</p>

  {$fieldsSection}

  <div class="notice">
    No template assigned — this is a preview shell.
    <a href="/admin/pages">Assign a template in the admin →</a>
  </div>
</body>
</html>
HTML;
}

function send404(string $slug = ''): never
{
    http_response_code(404);
    $s = e($slug);
    echo <<<HTML
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>404 — Not Found</title>
  <style>body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;max-width:480px;margin:80px auto;padding:0 24px;color:#555}h1{color:#1a1a1a;font-size:24px}code{background:#f5f5f5;padding:2px 6px;border-radius:4px;font-size:13px}a{color:#555}</style>
</head>
<body>
  <h1>404 — Not Found</h1>
  <p>No published page at <code>/{$s}</code>.</p>
  <p><a href="/admin/pages">Manage pages →</a></p>
</body>
</html>
HTML;
    exit;
}

function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
