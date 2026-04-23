# Channels — Space Cadet CMS

Two things live in this file:

1. **[Deployment Log](#deployment-log)** — a running record of every environment where Space Cadet CMS is deployed
2. **[Channels Feature](#channels-feature)** — design spec for the Channels external data source feature (planned)

---

## Deployment Log

A channel is any live deployment of Space Cadet CMS. Log it here when it goes up.

| Channel | Environment | URL | Version | Status | Deployed |
|---|---|---|---|---|---|
| test-site | Local dev | http://localhost:8888 | 0.1.3 | Active | 2026-04-23 |

### Adding a Channel

When you deploy to a new environment, append a row to the table above and add a detail block below:

```markdown
### [channel-name] — [Environment]
- **URL:** https://example.com
- **Version:** 0.1.0
- **PHP:** 8.2
- **Server:** Apache / Nginx / cPanel
- **Notes:** Any non-standard config, known issues, custom .htaccess, etc.
```

---

### test-site — Local Dev

- **URL:** http://localhost:8888
- **Version:** 0.1.3
- **PHP:** 8.1+ (built-in server via `npm run test-site`)
- **Server:** PHP built-in dev server
- **Location:** `test-site/` directory in project root
- **Notes:** Admin at http://localhost:8888/admin. Front-end pages at http://localhost:8888/{slug}. Database at `test-site/space-cadet/storage/db/space-cadet.sqlite`. Use for all local feature testing before GitHub push.

---

## Channels Feature

> **Status:** Planned — not yet implemented
> **Concept:** Channels bring external data into Space Cadet CMS using the same template syntax developers already know. If Collections are content you create, Channels are content that flows in from somewhere else.

---

### The Big Idea

Every CMS is a content island. Channels break that wall — any external data source becomes template-ready, no custom PHP required.

A marketing agency pulls client data from an API. A restaurant pulls their Toast menu. A developer pulls GitHub issues. Same syntax, same caching, same admin — it's just another loop in your template.

**Mental model:**
- **Collections** = content you own (you create it, you edit it, it lives in your database)
- **Channels** = content from elsewhere (it flows in, you display it, the source of truth is external)

---

### Channel Types

#### 1. REST API

Connect to any REST API endpoint.

- Endpoint URL with optional path variables (`/api/homes/{id}`)
- Auth: None, API Key, Bearer Token, Basic Auth, OAuth2 client credentials
- Custom headers and query parameters
- Response path — where in the JSON is the data array? (e.g., `data.listings`)
- Pagination: offset-based, cursor-based, link-header
- Rate limiting to protect external API quotas

**Template usage:**
```html
{% for home in channel.mls_listings %}
  <h2>{{ home.address }}</h2>
  <p class="price">{{ home.price }}</p>
{% endfor %}

{% single listing from channel.mls_listings where slug %}
  <h1>{{ listing.address }}</h1>
  <p>{{ listing.description | raw }}</p>
{% endsingle %}
```

#### 2. RSS / Atom Feeds

Pull any RSS or Atom feed as structured data.

- Feed URL + refresh interval + max items
- Built-in field mapping: `title`, `link`, `description`, `pubDate`, `author`, `content`, `thumbnail`

**Template usage:**
```html
{% for item in channel.industry_news %}
  <h3><a href="{{ item.link }}">{{ item.title }}</a></h3>
  <time>{{ item.pubDate }}</time>
{% endfor %}
```

#### 3. CSV / JSON File Sync

Upload or link to a CSV/JSON file.

- Upload a file, or poll a URL on schedule
- Auto-detected column mapping from headers
- Google Sheets export URLs, Airtable CSV, Excel exports

**Template usage:**
```html
{% for product in channel.price_list %}
  <td>{{ product.sku }}</td>
  <td>{{ product.price }}</td>
{% endfor %}
```

#### 4. Webhook Listener (Inbound)

Receive push data from external services.

- CMS generates a unique webhook URL per channel
- Optional HMAC signature validation
- Payload mapping — which fields from the payload to store
- Behavior: replace all / append / upsert by key field

**Use cases:** Stripe donor events → donor wall. Shopify inventory → in-stock products. GitHub push events → recent commits display.

---

### Database Schema

```sql
CREATE TABLE channels (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    slug TEXT UNIQUE NOT NULL,
    name TEXT NOT NULL,
    type TEXT NOT NULL,               -- 'api', 'rss', 'csv', 'webhook'
    config TEXT NOT NULL,              -- JSON: url, auth, headers, params, data_path
    field_map TEXT,                    -- JSON: field aliases and selections
    cache_ttl INTEGER DEFAULT 3600,
    url_pattern TEXT,                  -- e.g. '/listing/{slug}'
    sort_field TEXT,
    sort_direction TEXT DEFAULT 'desc',
    max_items INTEGER DEFAULT 100,
    status TEXT DEFAULT 'active',      -- 'active', 'paused', 'error'
    last_sync_at INTEGER,
    last_error TEXT,
    created_at INTEGER,
    updated_at INTEGER
);

CREATE TABLE channel_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    channel_id INTEGER NOT NULL,
    external_id TEXT,
    slug TEXT,
    data TEXT NOT NULL,                -- JSON: full item data
    sort_value TEXT,
    created_at INTEGER,
    updated_at INTEGER,
    FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE CASCADE
);

CREATE INDEX idx_channel_items_channel ON channel_items(channel_id);
CREATE INDEX idx_channel_items_slug ON channel_items(channel_id, slug);
CREATE INDEX idx_channel_items_sort ON channel_items(channel_id, sort_value);

CREATE TABLE channel_sync_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    channel_id INTEGER NOT NULL,
    status TEXT NOT NULL,              -- 'success', 'error', 'partial'
    items_synced INTEGER DEFAULT 0,
    items_added INTEGER DEFAULT 0,
    items_updated INTEGER DEFAULT 0,
    items_removed INTEGER DEFAULT 0,
    duration_ms INTEGER,
    error_message TEXT,
    synced_at INTEGER,
    FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE CASCADE
);
```

---

### Caching Strategy

All channel data is cached in SQLite. External APIs are **never** hit on a page request — only the cache is read.

- **Background sync** — a lightweight `channel-sync.php` runs via cron or triggered from admin
- **On-demand sync** — Admin clicks "Sync Now"
- **Stale-while-revalidate** — serve stale data + trigger async refresh if cache expired
- **Webhook-triggered** — for webhook channels, data updates immediately on receipt

---

### Template Engine Integration

Extend `php/templates/Engine.php` to recognize `channel.*` references alongside collection loops:

New engine functions:
- `cms_channel_list($slug, $options)` — loop over cached items
- `cms_channel_single($slug, $identifier)` — get one item by slug/id
- `cms_channel_count($slug)` — item count
- `cms_channel_meta($slug)` — last sync time, source URL, status

---

### Admin UI — Channel Builder

**Channel List** — name, type badge, status (active/paused/error), last sync time, item count, Sync Now button.

**Channel Configuration (step-by-step):**
1. Choose type (REST API / RSS / CSV / Webhook)
2. Connect — enter URL or upload file; **Test Connection** button
3. Discover Schema — introspect response, show JSON tree, pick fields, set data path
4. Configure — slug, cache TTL, pagination, item limit, sort, URL pattern
5. Preview — table of first N items + auto-generated template snippet

---

### Phased Rollout

| Phase | Scope | Status |
|---|---|---|
| 1 | REST API channels — CRUD, auth, schema discovery, SQLite cache, template tags, manual sync | Planned |
| 2 | RSS + CSV channels — feed parser, CSV/JSON file upload, Google Sheets URL support | Planned |
| 3 | Inbound Webhooks — unique URL per channel, HMAC validation, real-time updates | Planned |
| 4 | Outbound + Advanced — push on sync events, channel-as-read-only-API, filtering/pagination in templates | Planned |

---

### Security

- API credentials stored encrypted in `channels.config` JSON (AES-256 site key)
- Webhook HMAC validation when configured
- Channel admin restricted to admin/developer roles
- All channel data auto-escaped in templates by default
- Rate limiting on external API calls
- Input sanitization on all inbound webhook payloads

---

### Real-World Use Cases

- **Real estate** — MLS/IDX API → listings with search, filters, detail pages
- **Restaurant** — Toast/Square or Google Sheets → current menu with prices
- **Nonprofit** — Stripe webhook → recent donor wall
- **Portfolio** — GitHub API → open source projects with stars and descriptions
- **Job board** — Greenhouse/Lever/Workable → open positions
- **Events** — Eventbrite/Meetup → upcoming events list
- **News aggregation** — RSS feeds → curated industry news page
- **Data dashboard** — Google Sheets → live KPI display on internal site

---

### Open Questions

1. **Should channels support write-back?** Contact form submission pushes to external CRM — or is that a separate "Actions" concept?
2. **Channel + Collection hybrid?** Sync data into an actual collection, making it locally editable?
3. **Rate limiting UX** — how to communicate external API limits during setup?
4. **Error handling in templates** — if a channel is in error state, what renders? Empty loop? Stale data?
5. **Channel presets** — ship preset configs for popular APIs (Shopify, Stripe, GitHub, RSS)?
