# Space Cadet CMS — Polish & Improvement Plan

Comparing against Outpost and reviewing our own code, here's everything organized by impact and urgency.

---

## Phase 1 — Bug Fixes (Do First)

These are broken right now.

1. **`auth.js` has wrong route names** — calls `api.get('auth/me')` and `api.post('auth/logout')` but the router defines them as `me` and `logout`. `auth.js` isn't imported anywhere currently so it's a silent bomb — fix before anything uses it.

2. **Smart Forge model name is wrong** — `claude-opus-4-6` doesn't exist. Should be `claude-opus-4-5` (or `claude-sonnet-4-5` which is cheaper and faster).

3. **No CSRF validation in any controller** — `Auth::verifyCsrf()` exists and is well-implemented but zero controllers call it. Every mutation (POST/PUT/DELETE) should verify it.

4. **Dashboard shows only collection stats** — pulls only `collections`, no pages count, media count, members count. Very sparse for a CMS dashboard.

5. **`forge/+page.svelte` job ID tracking** — `submit()` doesn't capture `jobId` returned from the API, so the "Apply" button can't reference which job to apply. Needs consolidating.

---

## Phase 2 — UX & Polish (Most Impactful Visually)

Things users notice immediately.

6. **Global keyboard shortcut (Cmd/K)** — Outpost has this. Opens a search/command palette. Even a simple version (jump to any section) dramatically improves power-user UX.

7. **Richer dashboard** — Show stats for all entities: collections, items, pages, media files, members, forms. Outpost's dashboard is genuinely useful. Ours currently only shows collections.

8. **Consistent error boundaries** — Pages silently stay on "Loading…" if an API call throws outside try/catch. Add a shared `<ErrorBoundary>` component and audit every page.

9. **Empty states need CTAs** — Most empty state components have a generic message. They should guide the user to the next action (e.g., empty Collections → "Create your first collection → opens the schema builder").

10. **Page editor UX** — The `pages/[id]` editor is missing a content body field. Pages only store title/slug/status/parent — there's no `blocks` or `body` field for actual page content. Either add a rich text body or explain the template system.

11. **Collection item editor** — `collections/[slug]/[id]/+page.svelte` — verify the field renderer for every field type actually works end-to-end (RichText, Media picker, Repeater, Relation).

12. **Breadcrumbs** — `Breadcrumb.svelte` and `TopBar.svelte` exist in components but aren't used anywhere. Hook them into `AdminShell`.

---

## Phase 3 — Missing Features (Meaningful Additions)

Features Outpost has that we don't and that matter for a production CMS.

13. **Revision history** — Every time a collection item or page is saved, snapshot the previous version. Store diffs in a `revisions` table. Show a "History" tab in the item editor with before/after comparison and one-click restore.

14. **URL redirects** — A simple admin page to create 301/302 redirects (`/old-path` → `/new-path`) with regex support and hit tracking. Essential for any real site.

15. **Backup & restore** — One-click SQLite backup download, auto-backup schedule, and restore from uploaded file. Since the whole CMS is one SQLite file this is trivially easy to implement.

16. **RSS feed generation** — Auto-generate RSS 2.0 for any collection at `/feed/{collection-slug}`. Headless CMS users expect it.

17. **WAF-lite security** — Add a thin request filter in `api.php` before routing: detect obvious SQL injection patterns, XSS payloads, path traversal attempts, and log/block them. Doesn't need to be exhaustive — even basic pattern matching stops script kiddies.

18. **Setup wizard** — On first login after install, a modal that walks through: site name/URL, create first collection, invite another user. Reduces "now what?" friction for new installs.

---

## Phase 4 — Developer Experience

Makes Space Cadet nicer to work with and extend.

19. **GraphQL playground UI** — The GraphQL engine is built and working but there's no way to test it in the admin. Add a simple query editor page (even just a textarea + send button) at `/admin/graphql`. Outpost has GraphiQL.

20. **Template editor** — `templates/[id]/+page.svelte` uses CodeMirror but needs a live preview pane. The template engine exists — wire up a "Preview" button that POSTs the template body and shows rendered output.

21. **API keys scopes** — Currently API keys are all-or-nothing. Add scope checkboxes: `read`, `write`, `media`, `graphql`. The schema likely already has a `scopes` column.

22. **DB optimization endpoint** — Add a "Maintenance" tab in Settings with buttons to: run `VACUUM`, purge expired sessions, purge old audit log entries (>90 days), purge old rate limit records.

---

## Phase 5 — Longer-Term / Bigger Features

These require more work but would significantly differentiate Space Cadet.

23. **Editorial calendar** — Month-grid view of collection items by `published_at` date, with drag-to-reschedule.

24. **Comments on collection items** — A comments sidebar on the item editor for editorial notes and @mentions. Useful for teams.

25. **2FA (TOTP)** — Time-based one-time password with backup codes. No library dependencies needed — pure PHP HS256 implementation.

26. **Member portal** — Let `free_member`/`paid_member` roles create and edit their own collection items from a public-facing form. The role system is already built; this is a frontend + gating layer on top.

27. **Channels** — Pull external content (RSS, REST API, CSV) into a collection on a schedule. Useful for aggregating external content.
