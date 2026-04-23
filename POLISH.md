# Space Cadet CMS — Polish & Improvement Plan

Comparing against Outpost and reviewing our own code, here's everything organized by impact and urgency.

---

## Phase 1 — Bug Fixes (Do First)

These are broken right now.

1. **`auth.js` has wrong route names** — calls `api.get('auth/me')` and `api.post('auth/logout')` but the router defines them as `me` and `logout`. `auth.js` isn't imported anywhere currently so it's a silent bomb — fix before anything uses it.

2. **Blueprint AI model name is wrong** — `claude-opus-4-6` doesn't exist. Should be `claude-opus-4-5` (or `claude-sonnet-4-5` which is cheaper and faster).

3. **No CSRF validation in any controller** — `Auth::verifyCsrf()` exists and is well-implemented but zero controllers call it. Every mutation (POST/PUT/DELETE) should verify it.

4. **Dashboard shows only collection stats** — pulls only `collections`, no pages count, media count, members count. Very sparse for a CMS dashboard.

5. **Blueprint job ID tracking** — `submit()` in `blueprint/+page.svelte` doesn't capture `jobId` returned from the API, so the "Apply" button can't reference which job to apply.

6. **ROADMAP.md + FEATURES.md still say "Outpost CMS"** — both file titles and headers reference the old product name. Quick find-and-replace before any public-facing use.

7. **CHANNELS.md deployment log shows v0.1.0** — should be updated to v0.1.1 to match the current release.

---

## Phase 2 — UX & Polish (Most Impactful Visually)

Things users notice immediately.

8. **Toast / notification system** — No save confirmations exist anywhere. Every mutation silently succeeds or fails. Add a global toast (success / error / info) wired into the API client so every page gets it automatically without per-page handling.

9. **Unsaved changes warning** — Navigating away from a dirty editor loses work silently. A `beforeunload` guard + "You have unsaved changes — leave anyway?" modal should apply to all item, page, and template editors.

10. **Confirmation dialogs for destructive actions** — Delete buttons currently fire immediately. All deletes need a confirmation step — especially collection schema (deletes all items), members, and form submissions. One shared `<ConfirmDialog>` component used everywhere.

11. **Loading skeletons** — List pages flash "Loading…" text while fetching. Skeleton shimmer cards matching the real card shape look polished and prevent layout shift. Priority: dashboard, collections list, media grid.

12. **Pagination on all list pages** — Collection items, members, form submissions, and media all lack pagination. Any collection with >50 items will become slow and unusable. Simple prev/next offset pagination with page size 25 is enough.

13. **Bulk operations** — Checkbox-select multiple items → Delete Selected / Publish Selected / Unpublish Selected. Essential for any editorial workflow. Form submissions especially need bulk-delete.

14. **Duplicate item / page button** — No way to copy an existing item as a starting point. A "Duplicate" button on item and page editors is a quick win.

15. **Avatar / top-bar user menu** — The admin has no user context anywhere. Add a small avatar or initials in the top-right corner that opens a dropdown: Profile · Settings · Logout.

16. **Global keyboard shortcut (Cmd+K)** — Opens a search/command palette. Even a simple version (jump to any section) dramatically improves power-user UX.

17. **Richer dashboard** — Show stats for all entities: collections, items, pages, media files, members, forms. Outpost's dashboard is genuinely useful. Ours currently shows only collections.

18. **Consistent error boundaries** — Pages silently stay on "Loading…" if an API call throws outside try/catch. Add a shared `<ErrorBoundary>` component and audit every page.

19. **Empty states need CTAs** — Most empty state components have a generic message. They should guide the user to the next action (e.g., empty Collections → "Create your first collection" → opens the schema builder).

20. **Page editor UX** — The `pages/[id]` editor is missing a content body field. Pages only store title/slug/status/parent — there's no `blocks` or `body` field for actual page content.

21. **Collection item editor** — Verify the field renderer for every field type actually works end-to-end: RichText, Media picker, Repeater, Relation.

22. **Breadcrumbs** — `Breadcrumb.svelte` and `TopBar.svelte` exist in components but aren't used anywhere. Hook them into `AdminShell`.

23. **Named theme presets** — The hue/brightness/intensity sliders are built. Add 6–8 named presets above them (e.g., "Midnight", "Warm", "Cool", "Forest", "Sunset", "Default") that set all three values at once.

24. **Media grid / list toggle** — The media page should support both a thumbnail grid (images) and a list view (filename, size, date — better for PDFs/video). A simple toggle in the top bar.

---

## Phase 3 — Missing Features (Meaningful Additions)

Features Outpost has that we don't and that matter for a production CMS.

25. **Revision history** — Every time a collection item or page is saved, snapshot the previous version. Store diffs in a `revisions` table. Show a "History" tab in the item editor with before/after comparison and one-click restore.

26. **URL redirects** — A simple admin page to create 301/302 redirects (`/old-path` → `/new-path`) with regex support and hit tracking. Essential for any real site.

27. **Backup & restore** — One-click SQLite backup download, auto-backup schedule, and restore from uploaded file. The whole CMS is one SQLite file — this is trivially easy to implement.

28. **RSS feed generation** — Auto-generate RSS 2.0 for any collection at `/feed/{collection-slug}`. Headless CMS users expect it.

29. **WAF-lite security** — Add a thin request filter in `api.php` before routing: detect obvious SQL injection patterns, XSS payloads, path traversal attempts, and log/block them.

30. **Setup wizard** — On first login after install, a modal that walks through: site name/URL, create first collection, invite another user. Reduces "now what?" friction for new installs.

31. **Scheduled publishing** — Add a `published_at` datetime field to pages and collection items. Items with a future `published_at` stay draft and go live automatically when a lightweight check runs (cron or on-request).

32. **Collection items sort + filter** — The items list page has no sorting or filtering. Should sort by any field, filter by status (draft/published), and search by title.

33. **Site-wide content search** — A search bar in the top bar that searches across all collection items, pages, and globals by keyword. Returns grouped results. SQLite FTS makes this straightforward.

34. **Site settings page** — No place to configure site name, site URL, timezone, SMTP credentials for form email notifications, or default meta values. Settings > General is table stakes.

35. **Alt text on media uploads** — Media upload currently stores no alt text. Add an `alt` column to the media table and an editable field in the media detail panel.

36. **Media folder organization** — Simple folder/tag grouping for the media library. Without folders, any site with >100 uploads becomes unmanageable.

37. **Custom logo / favicon** — Upload a logo for the sidebar (instead of "Space Cadet") and a favicon for the browser tab. Stored in media, referenced in settings. Standard agency white-label feature.

38. **Contextual in-admin help** — Empty states and first-run screens should link to docs or show a short explanation of what each section is for. Even a tooltip on a "?" icon helps new users.

39. **Duplicate / export collection schema** — Copy a collection schema to a new collection, or export/import schemas as JSON. Saves time when creating similar collections.

---

## Phase 4 — Developer Experience

Makes Space Cadet nicer to work with and extend.

40. **GraphQL playground UI** — The GraphQL engine is built and working but there's no way to test it in the admin. Add a simple query editor page at `/admin/graphql`. Outpost has GraphiQL.

41. **Template editor live preview** — `templates/[id]/+page.svelte` uses CodeMirror but needs a live preview pane. Wire up a "Preview" button that POSTs the template body and shows rendered output.

42. **API key scopes** — Currently API keys are all-or-nothing. Add scope checkboxes: `read`, `write`, `media`, `graphql`.

43. **DB optimization / maintenance** — A "Maintenance" tab in Settings with buttons to: run `VACUUM`, purge expired sessions, purge old audit log entries (>90 days), purge old rate limit records.

44. **User profile page** — No way to change your own display name, email, or password. An Account page linked from the top-bar avatar menu with these fields is basic table stakes.

45. **Activity / audit log** — A log of "who changed what when" — item saved, page published, member created, setting changed. Stored in an `audit_log` table. Essential for multi-user installs.

46. **Webhook delivery log** — Webhooks exist but there's no way to see what was sent or debug failures. A delivery log (url, payload, response code, timestamp) with a "Redeliver" button in the webhook editor.

47. **Health check endpoint** — A public `GET /api.php?action=health` returning PHP version, SQLite version, disk space, storage writable, installed lock, and CMS version. Useful for uptime monitors.

48. **CSV export for collection items** — Export any collection's items as a CSV download from the admin. Agencies always need this for client handoffs.

49. **API documentation page** — Auto-generate a reference page at `/admin/api-docs` listing every route, method, required auth, and example. Also expose a machine-readable JSON schema at `/api.php?action=schema` for tooling.

50. **Custom CSS injection** — A textarea in Settings > Appearance for custom CSS injected into the admin SPA. Useful for white-labeling for client handoffs.

51. **Collection slug rename + auto-redirect** — When a collection or page slug changes, offer to automatically create a redirect from the old path. Ties into the URL redirect manager (item 26).

---

## Phase 5 — Longer-Term / Bigger Features

These require more work but would significantly differentiate Space Cadet.

52. **Editorial calendar** — Month-grid view of collection items by `published_at` date, with drag-to-reschedule.

53. **Comments on collection items** — A comments sidebar on the item editor for editorial notes and @mentions. Useful for teams.

54. **2FA (TOTP)** — Time-based one-time password with backup codes. No library dependencies needed — pure PHP HS256 implementation.

55. **Member portal** — Let `free_member`/`paid_member` roles create and edit their own collection items from a public-facing form. The role system is already built; this is a frontend + gating layer on top.

56. **Channels** — Pull external content (RSS, REST API, CSV) into Space Cadet on a schedule. The full spec is in `CHANNELS.md` with a Phase 1–4 rollout plan. Phase 1 (REST API) covers ~80% of real-world use cases. Key open question: should channels support write-back (form submissions pushing to external CRMs)?
