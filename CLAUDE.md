# Space Cadet CMS — Agent Instructions

## Documentation Rule
**Code first, read what was built, then write docs. Always.** Never write documentation in parallel with code — the docs agent must READ the finished code files before writing any documentation. The code is the source of truth. This prevents docs from describing features that don't exist or work differently than documented.

## Security Audit Rule
**Every feature MUST be audited before release.** After building any new feature, run a full code and security audit that reads the new/changed files and checks for: SQL injection, XSS, auth bypass, input validation, rate limiting, information disclosure, and code quality. Fix issues directly — do not just report them. No release ships without a passing audit.

## After Every Feature

When you complete a new feature, fix, or significant change:

1. **Update `FEATURES.md`** — add a concise entry under the appropriate section.
2. **Bump the version** — increment the patch version by 1 (e.g. `0.1.0` → `0.1.1`) in both:
   - `package.json` (`"version"` field)
   - `php/config/app.php` (`SC_VERSION` constant)
3. **Update `CHANGELOG.md`** — add the new version block at the top using Keep a Changelog format. Each entry is one concise line under `Added`, `Changed`, `Fixed`, or `Security`.
4. **Update `ROADMAP.md`** — mark the feature as shipped with strikethrough and **Shipped** label.
5. **Update `CHANNELS.md`** — if a new release was deployed to the test site, log it in the Deployment Log section.
6. **Always create a GitHub Release** — after committing and pushing, tag and publish a release. Run:
   ```bash
   npm run build
   git add -A
   git commit -m "v0.X.X — Description of changes"
   git push origin main
   git tag -a v0.X.X -m "v0.X.X — Description"
   git push origin v0.X.X
   gh release create v0.X.X --title "v0.X.X" --notes "Changelog entry"
   ```

## Tech Stack
- PHP 8.1+ + SQLite (PDO), no Composer dependencies
- Svelte 5 with runes syntax (`$state`, `$derived`, `$effect`, `$props`) — NOT Svelte 4
- Vite 6, SvelteKit static adapter, base path `/admin`
- Build output: `php/dist/`
- TipTap for richtext, CodeMirror 6 for code fields, SortableJS for drag reorder

## Svelte Rules
- Use Svelte 5 runes only. No `export let`, no `$:`, no `on:click`.
- Event modifiers are invalid — use `onclick={(e) => { e.stopPropagation(); }}` not `onclick|stopPropagation`
- Use `$state()`, `$derived()`, `$effect()`, `$props()` for reactivity
- Component props via `const { prop1, prop2 } = $props()`
- Bindable props: `let value = $bindable(defaultValue)` inside `$props()` destructure

## Design Principles
- Ghost/Linear minimal aesthetic — flat, modern SaaS
- Very few lines, very few colored backgrounds, minimal stroke
- Borderless inputs (border only on hover/focus)
- Tiny 11px uppercase muted labels, no card wrappers
- No colored background pills — use plain text links/toggles
- Content-first: large serif titles, clean whitespace

## Directory Structure
```
space-cadet-cms/
  php/                  ← all PHP source + deployable backend
    api.php             ← REST API front controller
    admin.php           ← serves the admin SPA
    install.php         ← first-run installer (locks after use)
    config/             ← app.php, database.php, cors.php, local.php
    controllers/        ← one file per resource
    models/             ← one file per model
    core/               ← Auth, Database, Router, etc.
    graphql/            ← hand-written GraphQL engine
    forge/              ← Smart Forge AI providers
    templates/          ← Liquid template compiler + engine
    media/              ← upload handler, image processor
    storage/            ← db/, uploads/, thumbnails/, cache/
    dist/               ← built Svelte SPA (gitignored)
  src/                  ← Svelte 5 admin SPA source
    routes/             ← SvelteKit file-based routes
    lib/                ← components, stores, API client
  test-site/            ← local test deployment (see below)
```

## Build & Deploy (Local Development)
- Build: `npm run build` (outputs to `php/dist/`)
- Start: `npm run start` (PHP on :8000 + Vite on :5173 concurrently)
- Dev: `npm run dev` (Vite HMR only; requires PHP server running separately)
- After build: copy `php/dist/*` to `test-site/space-cadet/dist/` for test-site verification

## API Pattern
- REST API at `api.php?action=<endpoint>` with session auth + CSRF
- GraphQL at `api.php?action=graphql` (POST, JSON body)
- Database migrations use `PRAGMA table_info` + `ALTER TABLE ADD COLUMN`
- JSON columns for flexible data (schema, data, blocks)
- Admin API requires `X-CSRF-Token` header on mutations

## Constants
- `SC_VERSION` — current version (in `php/config/app.php`)
- `SC_ROOT` — absolute path to `php/`
- `SC_STORAGE` — path to `php/storage/`
- `SC_DB_PATH` — SQLite database path

## Git & Release Workflow

**Repository:** `https://github.com/itsrichardhaar/space-cadet-cms.git` (branch: `main`)

### Development cycle
1. Make changes in `php/` (backend) and `src/` (Svelte admin SPA)
2. Test locally: `npm run start`
3. Build: `npm run build`
4. Deploy to test-site for final verification
5. After every feature/fix, follow the "After Every Feature" checklist above

### What goes in the repo (tracked)
- `php/` — all PHP source files (excluding `storage/` subdirs and `dist/`)
- `src/` — Svelte 5 source code
- `test-site/` — test deployment (minus data/db/uploads/cache)
- Config files: `package.json`, `vite.config.js`, `svelte.config.js`, `CLAUDE.md`
- Documentation: `README.md`, `CHANGELOG.md`, `FEATURES.md`, `ROADMAP.md`, `CHANNELS.md`

### What stays out of the repo (.gitignore)
- `node_modules/`, `php/dist/` — build artifacts
- `php/storage/db/`, `php/storage/uploads/`, `php/storage/cache/`, `php/storage/thumbnails/` — user data
- `php/config/local.php` — install-generated secrets
- `*.db`, `*.db-shm`, `*.db-wal` — database files
- `test-site/space-cadet/storage/` — test data

## Test Site

The `test-site/` directory is a local deployment used to verify Space Cadet CMS in a real server environment.

**Structure:**
```
test-site/
  index.php          ← front controller (same as php/index.php)
  .htaccess          ← Apache rewrite rules
  space-cadet/       ← the CMS backend (copy of php/)
    api.php
    admin.php
    install.php
    config/
    controllers/
    ...
    storage/         ← EXCLUDED from git; persists between deploys
      db/
      uploads/
      thumbnails/
      cache/
    dist/            ← built SPA; copy from php/dist/ after npm run build
```

**Deploying to test-site:**
```bash
# After npm run build
rsync -avz --exclude 'storage/' --exclude 'dist/' php/ test-site/space-cadet/
cp -r php/dist/ test-site/space-cadet/dist/
cp php/index.php test-site/index.php
```

Never overwrite `test-site/space-cadet/storage/` — it contains the test database and uploaded files.
