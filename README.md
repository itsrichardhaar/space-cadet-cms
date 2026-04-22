# Space Cadet CMS

A self-hosted, drop-and-run headless CMS. PHP 8 + SQLite on the backend, Svelte 5 + Vite 6 on the frontend. No Composer, no npm in production — just copy the `php/` directory to any server running PHP 8.1+.

---

## Requirements

| Requirement | Version |
|---|---|
| PHP | 8.1+ |
| PHP extensions | `pdo_sqlite`, `gd`, `fileinfo`, `curl` |
| Node.js | 18+ (build only — not needed in production) |
| Web server | Apache (mod_rewrite) or Nginx, or PHP built-in server for local dev |

---

## Local development

### 1. Install dependencies

```bash
npm install
```

### 2. Start both servers

```bash
npm run start
```

This runs concurrently:
- **PHP built-in server** on `http://localhost:8000` (API)
- **Vite dev server** on `http://localhost:5173` (admin UI with hot reload)

Vite proxies all `/api.php` and `/install.php` requests to the PHP server automatically.

### 3. Run the installer

Open **`http://localhost:5173/install.php`** and fill in:

- Site name
- Site URL (`http://localhost:5173` for local dev)
- Admin email + password

This creates the SQLite database at `php/storage/db/space-cadet.sqlite`, seeds the schema, and writes `php/config/local.php`. The installer is locked after first run.

### 4. Log in

Go to **`http://localhost:5173/admin/login`** and sign in with the credentials you just created.

---

## Building for production

```bash
npm run build
```

Output goes to `php/dist/`. The entire deployable package is the `php/` directory.

---

## Deployment

### Apache

1. Copy the `php/` directory to your web root (e.g. `/var/www/html/`).
2. Point your virtual host document root at that directory.
3. Ensure `mod_rewrite` is enabled — the included `.htaccess` handles routing.
4. Visit `https://yourdomain.com/install.php` to complete setup.

```apache
<VirtualHost *:443>
    DocumentRoot /var/www/html/php
    <Directory /var/www/html/php>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### Nginx

```nginx
server {
    root /var/www/html/php;
    index index.php admin.php;

    # Admin UI (SvelteKit static)
    location /admin {
        try_files $uri $uri/ /admin/index.html;
    }

    # API
    location /api.php {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # Static uploads
    location /storage/uploads/ { }
    location /storage/thumbnails/ { }

    # Block sensitive paths
    location ~* ^/(storage/db|storage/cache|config/local\.php) {
        deny all;
    }
}
```

### Permissions

```bash
chmod -R 755 php/
chmod -R 775 php/storage/
chown -R www-data:www-data php/storage/
```

---

## Features

### Content
- **Collections** — define custom content types with a drag-drop schema builder
- **Field types** — text, textarea, richtext (TipTap), number, toggle, date, select, checkbox, color, code (CodeMirror), media, relation, repeater
- **Pages** — hierarchical page tree with parent/child relationships and custom field support
- **Globals** — site-wide settings and shared content groups
- **Menus** — drag-drop nested menu builder with custom/page/collection-item link types

### Media
- Upload images, SVG, PDF, video, and other files
- Automatic WebP conversion and thumbnail generation (GD)
- SVG sanitization (strips scripts and event handlers)
- Folder organization and alt text / caption support

### Forms
- Drag-drop form builder (text, email, textarea, select, checkbox, radio)
- Submission inbox with read/spam filtering and CSV export
- Honeypot spam protection and per-form rate limiting

### Webhooks
- HMAC-SHA256 signed payloads (`X-SpaceCadet-Signature` header)
- Subscribe to 12 events (item.created, page.updated, form.submitted, etc.)
- Delivery log with status codes and response times

### GraphQL API
- Hand-written recursive descent parser (no library)
- Max depth: 10 · Max selections: 500 · Max query size: 32 KB
- Supports fragments, inline fragments, aliases, `@skip`/`@include` directives

```bash
# Fetch all collections
curl -s http://localhost:8000/api.php?action=graphql \
  -H 'Content-Type: application/json' \
  -d '{"query":"{ collections { id name slug item_count } }"}'

# Fetch a page by slug
curl -s http://localhost:8000/api.php?action=graphql \
  -H 'Content-Type: application/json' \
  -d '{"query":"{ page(slug: \"home\") { id title meta_title fields } }"}'

# Mutation (requires Bearer API key)
curl -s http://localhost:8000/api.php?action=graphql \
  -H 'Content-Type: application/json' \
  -H 'Authorization: Bearer sc_your_api_key' \
  -d '{"query":"mutation { createPage(title: \"About\", status: \"draft\") { id slug } }"}'
```

### REST API

All endpoints live at `/api.php?action=<resource>`.

| Resource | Endpoints |
|---|---|
| `auth` | `POST login`, `POST logout`, `GET me` |
| `collections` | CRUD + `schema`, `items` |
| `collections/{id}/items` | CRUD + bulk publish/archive/delete |
| `pages` | CRUD + reorder |
| `globals` | CRUD + field replacement |
| `menus` | CRUD + item replacement |
| `media` | Upload, list, update, delete |
| `forms` | CRUD + submissions, export |
| `webhooks` | CRUD + test + delivery log |
| `templates` | CRUD |
| `members` | CRUD |
| `api-keys` | Create, list, revoke |
| `settings` | GET + PUT (key/value) |
| `audit-log` | GET (paginated) |
| `search` | Full-text search across all content types |
| `forge/analyze` | AI content extraction |
| `graphql` | GraphQL endpoint |

### Smart Forge

AI-powered content extraction. Paste any webpage HTML → Smart Forge identifies editable regions and returns a suggested field schema + extracted values.

Supports **Claude** (Anthropic), **GPT-4o** (OpenAI), and **Gemini 1.5 Pro** (Google). Configure API keys in **Settings → AI Keys**.

### Compass

Smart filter panel for collection items. Click the **⊹ Filter** button on any collection list to open the sliding Compass panel. Filters adapt to the collection's field types:
- Dropdown for select/relation fields
- Checkbox group for checkbox fields
- Min/max range for number fields
- Text search for text/textarea fields

### Security

- Sessions: HttpOnly + Secure + SameSite=Strict cookies
- API keys: `sc_` prefix, bcrypt-hashed (cost 12), scoped permissions
- CSRF token enforcement on all write operations
- Rate limiting on public content API and login attempts
- RBAC: `super_admin` → `admin` → `developer` → `editor` → `free_member` / `paid_member`
- Configurable via **Settings → Security**

---

## Directory structure

```
space-cadet-cms/
├── php/                        # Deployable backend (copy this to your server)
│   ├── api.php                 # API entry point
│   ├── admin.php               # Admin SPA entry point
│   ├── install.php             # First-run installer (locked after setup)
│   ├── config/
│   │   ├── app.php             # Constants and bootstrap
│   │   ├── cors.php            # Security headers + CORS
│   │   └── local.php           # Generated by installer (not in git)
│   ├── core/                   # Framework: Router, Request, Response, Auth, etc.
│   ├── controllers/            # One controller per resource
│   ├── models/                 # Active-record style models (SQLite via PDO)
│   ├── graphql/                # Hand-written GraphQL engine
│   │   ├── Parser.php          # Recursive descent parser
│   │   ├── Validator.php       # Depth/size/auth validation
│   │   ├── Executor.php        # AST walker + field resolver
│   │   └── resolvers/          # Query + Mutation resolvers
│   ├── media/                  # Upload handling, WebP, SVG sanitizer
│   ├── forge/                  # Smart Forge AI providers
│   ├── templates/              # Template compiler + engine
│   ├── webhooks/               # HMAC dispatcher
│   ├── search/                 # Full-text search
│   ├── storage/                # Runtime data (not in git)
│   │   ├── db/                 # SQLite database
│   │   ├── uploads/            # Uploaded files
│   │   ├── thumbnails/         # Generated thumbnails
│   │   └── cache/              # Template cache
│   └── dist/                   # Built admin UI (generated by `npm run build`)
│
├── src/                        # Svelte 5 admin frontend
│   ├── lib/
│   │   ├── api.js              # Fetch wrapper
│   │   ├── graphql.js          # GraphQL client helper
│   │   ├── components/         # Shared UI components
│   │   └── stores/             # Svelte 5 rune-based stores
│   └── routes/                 # SvelteKit pages (one per admin section)
│
├── svelte.config.js
├── vite.config.js
└── package.json
```

---

## npm scripts

| Command | Description |
|---|---|
| `npm run start` | Start PHP server (`:8000`) + Vite dev server (`:5173`) |
| `npm run dev` | Vite dev server only |
| `npm run php:dev` | PHP built-in server only |
| `npm run build` | Build admin UI to `php/dist/` |
| `npm run preview` | Preview the production build |

---

## License

MIT
