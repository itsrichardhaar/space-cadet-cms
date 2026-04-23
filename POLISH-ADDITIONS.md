# Space Cadet CMS — Polish Additions

Ideas surfaced from reviewing CHANNELS.md, CLAUDE.md, FEATURES.md, ROADMAP.md, and the POLISH.md phases. These are not yet covered in the existing 27-item plan. Organized loosely by theme — meant to be cherry-picked into POLISH.md phases or spawned into their own.

---

## UX & Interaction

**A. Toast / notification system**
No save confirmations exist. Every mutation — save, delete, publish — disappears silently. Add a lightweight global toast (success / error / info) wired into the API client so every page gets it automatically. Outpost had this from day one.

**B. Unsaved changes warning**
Navigating away from a dirty editor currently just loses work. A `beforeunload` guard and a "You have unsaved changes — leave anyway?" modal prevents this. Should apply to all item/page/template editors.

**C. Confirmation dialogs for destructive actions**
Delete buttons currently fire immediately. All deletes should require confirmation — especially collection schema (which deletes all items), members, and form submissions. A shared `<ConfirmDialog>` component used everywhere.

**D. Loading skeletons**
List pages flash "Loading…" text while fetching. Skeleton shimmer cards (matching the real card shape) look polished and avoid layout shift. Priority: dashboard, collections list, media grid.

**E. Pagination on all list pages**
Collection items, members, form submissions, and media all lack pagination. Any collection with >50 items will become slow/unusable. Even simple prev/next offset pagination with a page size of 25 is enough for v1.

**F. Bulk operations**
Checkbox-select multiple items in a list → Delete Selected / Publish Selected / Unpublish Selected. Essential for any real editorial workflow. Forms submissions especially need bulk-delete.

**G. Duplicate item / page button**
Right now there's no way to copy an existing item as a starting point. A "Duplicate" button on item/page editors is a fast win.

---

## Content & Editorial

**H. Scheduled publishing**
Add a `published_at` datetime field to pages and collection items. Items with a future `published_at` stay in draft state and go live automatically when a lightweight PHP check runs (cron or on-request). The ROADMAP calls this out explicitly under v5.2 Headless-First as a planned feature.

**I. Collection items sort + filter**
The collection items list page has no sorting or filtering. Should be able to sort by any field, filter by status (draft/published), and search by title. Makes managing large collections workable.

**J. Content search across admin**
A global search bar in the top bar (separate from Cmd+K command palette) that searches across all collection items, pages, and globals by keyword. Returns grouped results — "3 Pages · 12 Items · 1 Global". SQLite FTS makes this straightforward.

**K. Site-wide settings page**
Currently no place to configure: site name, site URL, timezone, SMTP credentials for form email notifications, or default meta values. A Settings > General page is table stakes for any CMS.

**L. Duplicate / export collection schema**
Ability to copy a collection schema to a new collection, or export/import schemas as JSON. Saves time when creating similar collections. Also enables sharing schemas between installs.

---

## Media

**M. Alt text on uploads**
Media upload currently stores no alt text. Add an `alt` column to the media table and an editable alt text field in the media detail panel. Every image should have accessible alt text.

**N. Media grid view toggle**
The media page should support both a grid view (thumbnails, good for images) and a list view (filename, size, date — good for PDFs/video). A simple toggle, similar to macOS Finder.

**O. Media folder organization**
Simple folder/tag grouping for the media library. Without folders, any site with >100 uploads becomes unmanageable. Even a flat tag-based system (no nested folders) is a major improvement over a flat list.

---

## Developer Experience

**P. API documentation page**
Auto-generate a simple reference page at `/admin/api-docs` that lists every route, method, required auth, and example request/response. Outpost had `llms.txt` for AI-assisted dev — Space Cadet could output a similar JSON schema at `/api.php?action=schema` for tooling.

**Q. Webhook delivery log**
The webhooks system exists but there's no way to see what was sent or debug failures. A delivery log table (url, payload, response code, response body, timestamp) with a "Redeliver" button in the webhook editor would make webhooks actually usable.

**R. Health check endpoint**
A public `GET /api.php?action=health` that returns PHP version, SQLite version, disk space, storage writable, installed lock present, and version. Useful for uptime monitors and deployment verification. Outpost had this.

**S. CSV export for collection items**
Export any collection's items as a CSV download from the admin. No import needed at this stage — just export. Agencies always ask for this when handing off to clients.

**T. Collection slug rename + auto-redirect**
When a collection slug changes (or a page slug changes), offer to automatically create a redirect from the old path. Ties into the URL redirect manager from POLISH.md item 14.

---

## Auth & Users

**U. User profile page**
No way to change your own display name, email, or password from within the admin. An Account page (linked from a user avatar/menu in the top bar) with these fields is basic table stakes.

**V. Avatar / top-bar user menu**
The sidebar currently has no user context. A small avatar or initials in the top-right corner that opens a dropdown: Profile · Settings · Logout. Outpost had this in v4.10. Makes the admin feel complete.

**W. Activity log**
A per-user or global log of "who changed what when" — collection item saved, page published, member created, setting changed. Stored in an `audit_log` table. Essential for multi-user installs and debugging.

---

## Appearance

**X. Named theme presets**
The hue/brightness/intensity sliders are built. Add a row of 6–8 named presets above them (e.g., "Midnight", "Warm", "Cool", "Forest", "Sunset", "Default") that each set all three values at once. Same approach as Ableton's Theme selector.

**Y. Custom CSS injection**
A textarea in Settings > Appearance where admins can paste custom CSS that gets injected into the admin SPA. Useful for white-labeling Space Cadet for client handoffs (custom logo color, etc.).

**Z. Custom logo / favicon**
Upload a logo displayed in the sidebar (instead of "Space Cadet") and a favicon used in the browser tab. Stored in media, referenced in settings. Standard white-label feature for agencies.

---

## Documentation / Housekeeping

**AA. Update CHANNELS.md deployment log to v0.1.1**
The deployment log still shows `version: 0.1.0` for the test-site channel. Should be bumped to 0.1.1 after the recent push.

**AB. ROADMAP.md and FEATURES.md title says "Outpost CMS"**
Both files still reference Outpost in their titles/headers. Should be updated to Space Cadet CMS.

**AC. Contextual in-admin help**
Empty states (no collections, no media, no members) should link to docs or show a short explanation of what each section is for. Even a tooltip on the "?" icon would help new users. The ROADMAP calls this out explicitly as important for new installs.

---

## Channels (from CHANNELS.md spec — already in POLISH.md item 27 but worth expanding)

The CHANNELS.md file has a detailed Phase 1–4 rollout plan. If/when Channels moves up in priority, the spec is ready. The most valuable entry point is:

- **Phase 1: REST API channels** — CRUD, auth (API key / Bearer / Basic), schema discovery, SQLite cache, template tags (`channel.slug`), manual sync button. This alone covers 80% of real-world use cases (MLS listings, Shopify products, GitHub issues, etc.).

The spec also flags an open question worth deciding before building: **Should channels support write-back?** (form submissions pushing to an external CRM). If yes, that overlaps with Webhooks and needs to be designed together.

---

*Items AA–AB are fast documentation fixes. Items A–Z are ranked loosely from smallest-to-largest effort within each section — not a strict priority order. Review against the existing POLISH.md phases to slot these in or keep them as a separate backlog.*
