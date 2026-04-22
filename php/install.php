<?php
/**
 * Space Cadet CMS — First-Run Installer
 *
 * Creates the SQLite database, applies the full schema, seeds
 * a Super Admin user, and writes a local config + INSTALLED lock file.
 *
 * Disable after first run by the existence of storage/INSTALLED.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';

// Already installed?
if (file_exists(SC_INSTALLED_LOCK)) {
    http_response_code(403);
    echo '<!doctype html><html><head><title>Already installed</title></head><body>'
       . '<h1>Space Cadet CMS is already installed.</h1>'
       . '<p><a href="/admin/">Go to admin</a></p>'
       . '</body></html>';
    exit;
}

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $name     = trim($_POST['name']     ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm  = trim($_POST['confirm']  ?? '');
    $siteUrl  = rtrim(trim($_POST['site_url'] ?? ''), '/');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email required.';
    }
    if (strlen($name) < 2) {
        $errors[] = 'Name must be at least 2 characters.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        try {
            install($email, $name, $password, $siteUrl);
            $success = true;
        } catch (Throwable $e) {
            $errors[] = 'Installation failed: ' . $e->getMessage();
        }
    }
}

function install(string $email, string $name, string $password, string $siteUrl): void {
    // Ensure storage dirs exist
    foreach ([SC_UPLOADS, SC_THUMBS, SC_CACHE, dirname(SC_DB_PATH)] as $dir) {
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            throw new RuntimeException("Cannot create directory: {$dir}");
        }
    }

    // Create / connect to SQLite
    $pdo = new PDO('sqlite:' . SC_DB_PATH, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec("PRAGMA journal_mode = WAL");
    $pdo->exec("PRAGMA foreign_keys = ON");

    // Apply schema
    $pdo->exec(schema());

    // Seed Super Admin
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $now  = time();
    $pdo->prepare(
        "INSERT INTO users (email, password_hash, display_name, role, status, created_at, updated_at)
         VALUES (?, ?, ?, 'super_admin', 'active', ?, ?)"
    )->execute([$email, $hash, $name, $now, $now]);

    // Default settings
    $settings = [
        'site_name'       => 'My Site',
        'site_url'        => $siteUrl,
        'timezone'        => 'UTC',
        'date_format'     => 'Y-m-d',
        'items_per_page'  => '20',
        'schema_version'  => '1',
        'webp_convert'    => '1',
    ];
    $stmt = $pdo->prepare("INSERT INTO settings (key, value, updated_at) VALUES (?, ?, ?)");
    foreach ($settings as $k => $v) {
        $stmt->execute([$k, $v, $now]);
    }

    // Write local config with generated secret
    $secret    = bin2hex(random_bytes(32));
    $localConf = <<<PHP
<?php
define('SC_SECRET', '{$secret}');
PHP;
    file_put_contents(SC_ROOT . '/config/local.php', $localConf);

    // Write INSTALLED lock
    file_put_contents(SC_INSTALLED_LOCK, date('c'));
}

function schema(): string {
    return <<<SQL
-- ============================================================
-- USERS & AUTH
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    email           TEXT    NOT NULL UNIQUE,
    password_hash   TEXT    NOT NULL,
    display_name    TEXT    NOT NULL,
    role            TEXT    NOT NULL DEFAULT 'editor'
                    CHECK(role IN ('super_admin','admin','developer','editor','free_member','paid_member')),
    status          TEXT    NOT NULL DEFAULT 'active'
                    CHECK(status IN ('active','suspended','pending')),
    avatar_media_id INTEGER REFERENCES media(id) ON DELETE SET NULL,
    meta            TEXT    DEFAULT '{}',
    last_login_at   INTEGER,
    created_at      INTEGER NOT NULL,
    updated_at      INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_users_email  ON users(email);
CREATE INDEX IF NOT EXISTS idx_users_role   ON users(role);
CREATE INDEX IF NOT EXISTS idx_users_status ON users(status);

CREATE TABLE IF NOT EXISTS sessions (
    id         TEXT    PRIMARY KEY,
    user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    ip_address TEXT,
    user_agent TEXT,
    expires_at INTEGER NOT NULL,
    created_at INTEGER NOT NULL DEFAULT (unixepoch())
);
CREATE INDEX IF NOT EXISTS idx_sessions_user    ON sessions(user_id);
CREATE INDEX IF NOT EXISTS idx_sessions_expires ON sessions(expires_at);

CREATE TABLE IF NOT EXISTS api_keys (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id      INTEGER REFERENCES users(id) ON DELETE SET NULL,
    name         TEXT    NOT NULL,
    key_hash     TEXT    NOT NULL UNIQUE,
    key_prefix   TEXT    NOT NULL,
    scopes       TEXT    NOT NULL DEFAULT '["read"]',
    last_used_at INTEGER,
    expires_at   INTEGER,
    created_at   INTEGER NOT NULL DEFAULT (unixepoch())
);
CREATE INDEX IF NOT EXISTS idx_api_keys_prefix ON api_keys(key_prefix);

CREATE TABLE IF NOT EXISTS rate_limit_log (
    id     INTEGER PRIMARY KEY AUTOINCREMENT,
    bucket TEXT    NOT NULL,
    action TEXT    NOT NULL,
    hit_at INTEGER NOT NULL DEFAULT (unixepoch())
);
CREATE INDEX IF NOT EXISTS idx_rate_limit ON rate_limit_log(bucket, action, hit_at);

-- ============================================================
-- FOLDERS & LABELS
-- ============================================================
CREATE TABLE IF NOT EXISTS folders (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    name       TEXT    NOT NULL,
    parent_id  INTEGER REFERENCES folders(id) ON DELETE SET NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at INTEGER NOT NULL DEFAULT (unixepoch())
);
CREATE INDEX IF NOT EXISTS idx_folders_parent ON folders(parent_id);

CREATE TABLE IF NOT EXISTS labels (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    name       TEXT    NOT NULL,
    slug       TEXT    NOT NULL UNIQUE,
    color      TEXT    DEFAULT '#7c6af7',
    created_at INTEGER NOT NULL DEFAULT (unixepoch())
);

-- ============================================================
-- MEDIA
-- ============================================================
CREATE TABLE IF NOT EXISTS media (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    filename      TEXT    NOT NULL,
    original_name TEXT    NOT NULL,
    mime_type     TEXT    NOT NULL,
    size_bytes    INTEGER NOT NULL,
    width         INTEGER,
    height        INTEGER,
    folder_id     INTEGER REFERENCES folders(id) ON DELETE SET NULL,
    alt_text      TEXT,
    caption       TEXT,
    webp_path     TEXT,
    thumb_path    TEXT,
    uploaded_by   INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at    INTEGER NOT NULL DEFAULT (unixepoch()),
    updated_at    INTEGER NOT NULL DEFAULT (unixepoch())
);
CREATE INDEX IF NOT EXISTS idx_media_folder  ON media(folder_id);
CREATE INDEX IF NOT EXISTS idx_media_mime    ON media(mime_type);
CREATE INDEX IF NOT EXISTS idx_media_created ON media(created_at DESC);

-- ============================================================
-- COLLECTIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS collections (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    name            TEXT    NOT NULL,
    slug            TEXT    NOT NULL UNIQUE,
    description     TEXT,
    icon            TEXT    DEFAULT 'folder',
    supports_status INTEGER NOT NULL DEFAULT 1,
    supports_author INTEGER NOT NULL DEFAULT 1,
    supports_dates  INTEGER NOT NULL DEFAULT 1,
    sort_field      TEXT    DEFAULT 'created_at',
    sort_direction  TEXT    DEFAULT 'desc' CHECK(sort_direction IN ('asc','desc')),
    is_singleton    INTEGER NOT NULL DEFAULT 0,
    created_at      INTEGER NOT NULL DEFAULT (unixepoch()),
    updated_at      INTEGER NOT NULL DEFAULT (unixepoch())
);

CREATE TABLE IF NOT EXISTS collection_fields (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    collection_id INTEGER NOT NULL REFERENCES collections(id) ON DELETE CASCADE,
    name          TEXT    NOT NULL,
    key           TEXT    NOT NULL,
    type          TEXT    NOT NULL
                  CHECK(type IN ('text','textarea','richtext','number','toggle',
                                 'date','select','checkbox','media','relation',
                                 'color','code','repeater','flexible')),
    options       TEXT    DEFAULT '{}',
    required      INTEGER NOT NULL DEFAULT 0,
    sort_order    INTEGER NOT NULL DEFAULT 0,
    created_at    INTEGER NOT NULL DEFAULT (unixepoch())
);
CREATE INDEX IF NOT EXISTS idx_cf_collection ON collection_fields(collection_id, sort_order);
CREATE UNIQUE INDEX IF NOT EXISTS idx_cf_key ON collection_fields(collection_id, key);

CREATE TABLE IF NOT EXISTS collection_items (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    collection_id INTEGER NOT NULL REFERENCES collections(id) ON DELETE CASCADE,
    title         TEXT    NOT NULL,
    slug          TEXT    NOT NULL,
    status        TEXT    NOT NULL DEFAULT 'draft'
                  CHECK(status IN ('draft','published','archived')),
    author_id     INTEGER REFERENCES users(id) ON DELETE SET NULL,
    folder_id     INTEGER REFERENCES folders(id) ON DELETE SET NULL,
    sort_order    INTEGER NOT NULL DEFAULT 0,
    published_at  INTEGER,
    created_at    INTEGER NOT NULL DEFAULT (unixepoch()),
    updated_at    INTEGER NOT NULL DEFAULT (unixepoch())
);
CREATE UNIQUE INDEX IF NOT EXISTS idx_ci_slug   ON collection_items(collection_id, slug);
CREATE        INDEX IF NOT EXISTS idx_ci_coll   ON collection_items(collection_id, status, published_at DESC);
CREATE        INDEX IF NOT EXISTS idx_ci_folder ON collection_items(folder_id);
CREATE        INDEX IF NOT EXISTS idx_ci_author ON collection_items(author_id);

CREATE TABLE IF NOT EXISTS collection_item_fields (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    item_id    INTEGER NOT NULL REFERENCES collection_items(id) ON DELETE CASCADE,
    field_key  TEXT    NOT NULL,
    value_text TEXT,
    value_int  INTEGER,
    value_real REAL,
    value_json TEXT
);
CREATE UNIQUE INDEX IF NOT EXISTS idx_cif_key ON collection_item_fields(item_id, field_key);

CREATE TABLE IF NOT EXISTS collection_item_labels (
    item_id  INTEGER NOT NULL REFERENCES collection_items(id) ON DELETE CASCADE,
    label_id INTEGER NOT NULL REFERENCES labels(id) ON DELETE CASCADE,
    PRIMARY KEY (item_id, label_id)
);

-- ============================================================
-- TEMPLATES
-- ============================================================
CREATE TABLE IF NOT EXISTS templates (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    name          TEXT    NOT NULL,
    slug          TEXT    NOT NULL UNIQUE,
    source        TEXT    NOT NULL DEFAULT '',
    compiled_path TEXT,
    compiled_hash TEXT,
    type          TEXT    NOT NULL DEFAULT 'page'
                  CHECK(type IN ('page','partial','layout')),
    created_at    INTEGER NOT NULL DEFAULT (unixepoch()),
    updated_at    INTEGER NOT NULL DEFAULT (unixepoch())
);

-- ============================================================
-- PAGES
-- ============================================================
CREATE TABLE IF NOT EXISTS pages (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    title       TEXT    NOT NULL,
    slug        TEXT    NOT NULL UNIQUE,
    parent_id   INTEGER REFERENCES pages(id) ON DELETE SET NULL,
    status      TEXT    NOT NULL DEFAULT 'draft'
                CHECK(status IN ('draft','published','archived')),
    template_id INTEGER REFERENCES templates(id) ON DELETE SET NULL,
    author_id   INTEGER REFERENCES users(id) ON DELETE SET NULL,
    sort_order  INTEGER NOT NULL DEFAULT 0,
    meta_title  TEXT,
    meta_desc   TEXT,
    published_at INTEGER,
    created_at  INTEGER NOT NULL DEFAULT (unixepoch()),
    updated_at  INTEGER NOT NULL DEFAULT (unixepoch())
);
CREATE INDEX IF NOT EXISTS idx_pages_parent ON pages(parent_id, sort_order);
CREATE INDEX IF NOT EXISTS idx_pages_status ON pages(status);

CREATE TABLE IF NOT EXISTS page_fields (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    page_id    INTEGER NOT NULL REFERENCES pages(id) ON DELETE CASCADE,
    field_key  TEXT    NOT NULL,
    value_text TEXT,
    value_int  INTEGER,
    value_real REAL,
    value_json TEXT
);
CREATE UNIQUE INDEX IF NOT EXISTS idx_pf_key ON page_fields(page_id, field_key);

CREATE TABLE IF NOT EXISTS page_field_defs (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    page_id    INTEGER NOT NULL REFERENCES pages(id) ON DELETE CASCADE,
    name       TEXT    NOT NULL,
    key        TEXT    NOT NULL,
    type       TEXT    NOT NULL,
    options    TEXT    DEFAULT '{}',
    required   INTEGER NOT NULL DEFAULT 0,
    sort_order INTEGER NOT NULL DEFAULT 0
);
CREATE UNIQUE INDEX IF NOT EXISTS idx_pfd_key ON page_field_defs(page_id, key);

-- ============================================================
-- GLOBALS
-- ============================================================
CREATE TABLE IF NOT EXISTS global_groups (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    name        TEXT    NOT NULL,
    slug        TEXT    NOT NULL UNIQUE,
    description TEXT,
    created_at  INTEGER NOT NULL DEFAULT (unixepoch()),
    updated_at  INTEGER NOT NULL DEFAULT (unixepoch())
);

CREATE TABLE IF NOT EXISTS global_fields (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    group_id   INTEGER NOT NULL REFERENCES global_groups(id) ON DELETE CASCADE,
    name       TEXT    NOT NULL,
    key        TEXT    NOT NULL,
    type       TEXT    NOT NULL,
    options    TEXT    DEFAULT '{}',
    sort_order INTEGER NOT NULL DEFAULT 0
);
CREATE UNIQUE INDEX IF NOT EXISTS idx_gf_key ON global_fields(group_id, key);

CREATE TABLE IF NOT EXISTS global_values (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    group_id   INTEGER NOT NULL REFERENCES global_groups(id) ON DELETE CASCADE,
    field_key  TEXT    NOT NULL,
    value_text TEXT,
    value_int  INTEGER,
    value_real REAL,
    value_json TEXT,
    updated_at INTEGER NOT NULL DEFAULT (unixepoch())
);
CREATE UNIQUE INDEX IF NOT EXISTS idx_gv_key ON global_values(group_id, field_key);

-- ============================================================
-- MENUS
-- ============================================================
CREATE TABLE IF NOT EXISTS menus (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    name       TEXT    NOT NULL,
    slug       TEXT    NOT NULL UNIQUE,
    created_at INTEGER NOT NULL DEFAULT (unixepoch()),
    updated_at INTEGER NOT NULL DEFAULT (unixepoch())
);

CREATE TABLE IF NOT EXISTS menu_items (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    menu_id    INTEGER NOT NULL REFERENCES menus(id) ON DELETE CASCADE,
    parent_id  INTEGER REFERENCES menu_items(id) ON DELETE CASCADE,
    label      TEXT    NOT NULL,
    url        TEXT,
    target     TEXT    DEFAULT '_self',
    rel        TEXT,
    icon       TEXT,
    link_type  TEXT    DEFAULT 'custom'
               CHECK(link_type IN ('custom','page','collection_item','url')),
    linked_id  INTEGER,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at INTEGER NOT NULL DEFAULT (unixepoch())
);
CREATE INDEX IF NOT EXISTS idx_mi_menu   ON menu_items(menu_id, sort_order);
CREATE INDEX IF NOT EXISTS idx_mi_parent ON menu_items(parent_id);

-- ============================================================
-- FORMS
-- ============================================================
CREATE TABLE IF NOT EXISTS forms (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    name            TEXT    NOT NULL,
    slug            TEXT    NOT NULL UNIQUE,
    description     TEXT,
    success_message TEXT    DEFAULT 'Thank you!',
    notify_emails   TEXT    DEFAULT '[]',
    honeypot_field  TEXT    DEFAULT 'website',
    rate_limit_max  INTEGER DEFAULT 5,
    created_at      INTEGER NOT NULL DEFAULT (unixepoch()),
    updated_at      INTEGER NOT NULL DEFAULT (unixepoch())
);

CREATE TABLE IF NOT EXISTS form_fields (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    form_id     INTEGER NOT NULL REFERENCES forms(id) ON DELETE CASCADE,
    name        TEXT    NOT NULL,
    key         TEXT    NOT NULL,
    type        TEXT    NOT NULL
                CHECK(type IN ('text','email','textarea','select','checkbox',
                               'radio','number','file','hidden')),
    placeholder TEXT,
    required    INTEGER NOT NULL DEFAULT 0,
    options     TEXT    DEFAULT '{}',
    sort_order  INTEGER NOT NULL DEFAULT 0
);
CREATE UNIQUE INDEX IF NOT EXISTS idx_ff_key ON form_fields(form_id, key);

CREATE TABLE IF NOT EXISTS form_submissions (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    form_id    INTEGER NOT NULL REFERENCES forms(id) ON DELETE CASCADE,
    data       TEXT    NOT NULL DEFAULT '{}',
    ip_address TEXT,
    user_agent TEXT,
    referrer   TEXT,
    is_spam    INTEGER NOT NULL DEFAULT 0,
    is_read    INTEGER NOT NULL DEFAULT 0,
    created_at INTEGER NOT NULL DEFAULT (unixepoch())
);
CREATE INDEX IF NOT EXISTS idx_fs_form  ON form_submissions(form_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_fs_spam  ON form_submissions(is_spam);
CREATE INDEX IF NOT EXISTS idx_fs_read  ON form_submissions(is_read);

-- ============================================================
-- WEBHOOKS
-- ============================================================
CREATE TABLE IF NOT EXISTS webhooks (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    name          TEXT    NOT NULL,
    url           TEXT    NOT NULL,
    secret        TEXT    NOT NULL,
    events        TEXT    NOT NULL DEFAULT '[]',
    is_active     INTEGER NOT NULL DEFAULT 1,
    last_fired_at INTEGER,
    last_status   INTEGER,
    created_at    INTEGER NOT NULL DEFAULT (unixepoch()),
    updated_at    INTEGER NOT NULL DEFAULT (unixepoch())
);

CREATE TABLE IF NOT EXISTS webhook_deliveries (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    webhook_id  INTEGER NOT NULL REFERENCES webhooks(id) ON DELETE CASCADE,
    event       TEXT    NOT NULL,
    payload     TEXT    NOT NULL,
    signature   TEXT    NOT NULL,
    status_code INTEGER,
    response    TEXT,
    duration_ms INTEGER,
    fired_at    INTEGER NOT NULL DEFAULT (unixepoch())
);
CREATE INDEX IF NOT EXISTS idx_wd_webhook ON webhook_deliveries(webhook_id, fired_at DESC);

-- ============================================================
-- SEARCH (FTS5)
-- ============================================================
CREATE VIRTUAL TABLE IF NOT EXISTS search_index USING fts5(
    entity_type,
    entity_id UNINDEXED,
    title,
    body,
    meta,
    tokenize = 'porter unicode61'
);

-- ============================================================
-- SETTINGS & AUDIT
-- ============================================================
CREATE TABLE IF NOT EXISTS settings (
    key        TEXT    PRIMARY KEY,
    value      TEXT    NOT NULL,
    updated_at INTEGER NOT NULL DEFAULT (unixepoch())
);

CREATE TABLE IF NOT EXISTS audit_log (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id     INTEGER REFERENCES users(id) ON DELETE SET NULL,
    action      TEXT    NOT NULL,
    entity_type TEXT,
    entity_id   INTEGER,
    diff        TEXT,
    ip_address  TEXT,
    created_at  INTEGER NOT NULL DEFAULT (unixepoch())
);
CREATE INDEX IF NOT EXISTS idx_audit_user    ON audit_log(user_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_audit_entity  ON audit_log(entity_type, entity_id);
CREATE INDEX IF NOT EXISTS idx_audit_created ON audit_log(created_at DESC);

-- ============================================================
-- SMART FORGE
-- ============================================================
CREATE TABLE IF NOT EXISTS forge_jobs (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id      INTEGER REFERENCES users(id) ON DELETE SET NULL,
    provider     TEXT    NOT NULL CHECK(provider IN ('claude','openai','gemini')),
    input_html   TEXT    NOT NULL,
    prompt_used  TEXT,
    result_json  TEXT,
    status       TEXT    NOT NULL DEFAULT 'pending'
                 CHECK(status IN ('pending','processing','done','failed')),
    error        TEXT,
    created_at   INTEGER NOT NULL DEFAULT (unixepoch()),
    completed_at INTEGER
);
SQL;
}

// ── HTML ────────────────────────────────────────────────────────────────────

$pageTitle  = $success ? 'Installation Complete' : 'Install Space Cadet CMS';
$formAction = htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES);

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $pageTitle ?></title>
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
           background: #0f0f13; color: #e8e8f0; display: flex; justify-content: center;
           align-items: flex-start; min-height: 100vh; padding: 40px 16px; }
    .card { background: #1a1a24; border: 1px solid #2e2e3e; border-radius: 10px;
            padding: 40px; width: 100%; max-width: 480px; }
    h1 { margin: 0 0 8px; font-size: 22px; font-weight: 700; }
    p.sub { margin: 0 0 32px; color: #888899; font-size: 14px; }
    label { display: block; margin-bottom: 16px; }
    label span { display: block; font-size: 13px; font-weight: 500; margin-bottom: 6px; }
    input[type=text], input[type=email], input[type=password], input[type=url] {
      width: 100%; padding: 10px 12px; background: #22222f; border: 1px solid #2e2e3e;
      border-radius: 6px; color: #e8e8f0; font-size: 14px; outline: none; }
    input:focus { border-color: #7c6af7; }
    button { width: 100%; padding: 12px; background: #7c6af7; color: #fff; border: none;
             border-radius: 6px; font-size: 15px; font-weight: 600; cursor: pointer; margin-top: 8px; }
    button:hover { background: #9585ff; }
    .error { background: rgba(248,113,113,.1); border: 1px solid #f87171; border-radius: 6px;
             padding: 12px 16px; margin-bottom: 20px; font-size: 13px; color: #f87171; }
    .error ul { margin: 4px 0 0; padding-left: 18px; }
    .success { text-align: center; }
    .success .icon { font-size: 48px; margin-bottom: 16px; }
    .success a { display: inline-block; margin-top: 24px; padding: 12px 32px;
                 background: #7c6af7; color: #fff; border-radius: 6px; font-weight: 600;
                 text-decoration: none; }
    .success a:hover { background: #9585ff; }
  </style>
</head>
<body>
<div class="card">
<?php if ($success): ?>
  <div class="success">
    <div class="icon">🚀</div>
    <h1>Installation Complete!</h1>
    <p>Space Cadet CMS is ready. Log in with the credentials you just set.</p>
    <a href="/admin/">Go to Admin</a>
  </div>
<?php else: ?>
  <h1>Space Cadet CMS</h1>
  <p class="sub">Let's get your CMS set up. This only takes a minute.</p>

  <?php if (!empty($errors)): ?>
  <div class="error">
    <strong>Please fix the following:</strong>
    <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
  </div>
  <?php endif; ?>

  <form method="post" action="<?= $formAction ?>">
    <label>
      <span>Your Name</span>
      <input type="text" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required placeholder="Jane Smith">
    </label>
    <label>
      <span>Email Address</span>
      <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required placeholder="jane@example.com">
    </label>
    <label>
      <span>Password</span>
      <input type="password" name="password" required placeholder="At least 8 characters">
    </label>
    <label>
      <span>Confirm Password</span>
      <input type="password" name="confirm" required>
    </label>
    <label>
      <span>Site URL <small style="color:#888899">(optional)</small></span>
      <input type="url" name="site_url" value="<?= htmlspecialchars($_POST['site_url'] ?? '') ?>" placeholder="https://example.com">
    </label>
    <button type="submit">Install Space Cadet CMS</button>
  </form>
<?php endif; ?>
</div>
</body>
</html>
