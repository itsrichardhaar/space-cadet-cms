# Space Cadet CMS — Domain Context

A self-hosted, drop-in CMS that serves both a headless API and a rendered public frontend.
Developers build themes in code; non-technical editors use a visual live builder.

---

## Glossary

### Block
A named, reusable content section with editable fields. The atomic unit of page composition.
A block has a type (e.g. `hero`, `features`, `cta`) and a set of field values specific to its placement on a page.

Two kinds exist: **System Blocks** and **Custom Blocks**.

### System Block
A pre-built block shipped with Space Cadet CMS. Exposes a curated set of named style options
(e.g. background: light/dark, alignment: left/center) that map to modifier classes.

### Custom Block
A developer-authored block defined as a single HTML file with YAML frontmatter in the active
theme's `blocks/` directory. Auto-discovered — no registration step required.

### Block Instance
A placed block on a specific page, carrying its own field values. Stored as JSON in the page's
`blocks` column. Multiple instances of the same block type can exist on one page with different values.

### Theme
A named directory under `themes/` containing all templates, blocks, layouts, partials, and assets
for a site. The active theme is set in Settings. Themes are developer-owned — CMS core updates
never touch theme files. No child theme concept.

### Layout
An HTML wrapper template in the theme's `layouts/` directory that provides the outer shell
(DOCTYPE, head, nav, footer) for a page. Pages choose a named layout. `default.html` is used
when no layout is specified.

### Partial
A reusable template fragment in `layouts/partials/` included by layouts and blocks via
`{% include 'partials/nav.html' %}`.

### Design Token
A named style variable (color, font family, spacing) declared in `theme.json` and stored in
the settings table. Rendered as CSS custom properties injected into every page's `<head>`.
Editors can override tokens per-block instance.

### Modifier Class
A CSS class applied to a block wrapper to activate a named style option (e.g. `.block-hero--dark`).
Generated from the block's curated option selections. Predictable, targetable with CSS.

### Collection
A custom content type with a defined field schema. Each collection has one assigned template
and a configurable URL pattern (e.g. `/blog/{slug}`). Collections can optionally include a
**Blocks Field** for items that need flexible, composed layouts.

### Starter Block Library
The 14 system blocks shipped with Space Cadet CMS on a fresh install:

| Category | Blocks |
|---|---|
| Content | Hero, Rich Text, Image, Video, SVG, Custom HTML |
| Media | Image Gallery, Carousel |
| Layout | Text + Image, Grid, CTA Banner |
| Data & Interaction | Collection Loop, Contact Form, Accordion, Table |

`Custom HTML` is developer-role-only — hidden from the block picker for editor roles and below.

There are no container, section, div, or wrapper blocks. The block model is intentionally flat.
See ADR 0006.

### Blocks Field
A special field type (`type: blocks`) available in the collection schema editor. Adding it to
a collection gives each item a block-composed content area alongside its regular fields.
Developers reference it in templates as `{{ fields.body }}` (or whatever the field is named).
Opt-in per collection — simple collections (blog posts, team members) don't need it.

### URL Pattern
The configurable route template for a collection's public item URLs (e.g. `/blog/{slug}`,
`/work/{category}/{slug}`). Defined per collection in Settings.

### Page
A CMS-managed document with a slug, a chosen layout, and an ordered list of block instances.
Block composition is stored as a JSON array in the `blocks` column.

### Preview Mode
The rendering mode used in the live builder. The Liquid engine preserves `data-field` attributes
on output elements so the postMessage bridge can inject field value changes directly into the DOM
without a full page reload.

### Public Router
The PHP entry point that handles all URLs not matched by `/api.php` or `/admin/*`.
Resolves a URL by checking page slugs first (exact match), then collection URL patterns (regex).

### Menu Block
A system block that renders a named menu from the Menu Builder as a nav element.
Editors manage links in the Menu Builder; the Menu Block just displays them.
Used in layouts for simple navs. Not configurable for complex/mega menu layouts —
those are always developer-built partials.

### Mega Menu
A complex, brand-specific navigation layout. Always developer-built as a partial in
`layouts/partials/`. Link data is managed via the Menu Builder and referenced in Liquid.
Not configurable in the visual builder — by design.

> **Note:** There is no "Global Component" concept. Layouts + Globals + the Menu Block
> cover all recurring-element use cases. A block on a layout is not the same as a
> "global block instance" — it is simply part of the layout template.

### Live Builder
The visual editing interface in the admin. Shows a live preview of the rendered page alongside
a sidebar panel for editing block fields, adding/removing blocks, and adjusting style options.
Simple field edits update via postMessage DOM injection. Structural changes (add/remove/reorder blocks)
trigger a full preview re-render.

Top bar contains: page title (inline editable), status dropdown (Draft/Published), device
preview toggle (desktop/tablet/mobile), and a Page Settings button (slug, SEO meta, layout picker).

New pages show a blank canvas with a "Start from template →" prompt. Template picker is
skippable for experienced developers who want a blank canvas.

Block reordering is via drag handles in the sidebar block list (SortableJS).
Each block row has: duplicate, delete (with confirmation), and hide/show toggle actions.
Clicking a block in the preview selects it in the sidebar (postMessage). Hovering shows a
highlight outline.

### Block Schema Contract
Block field schemas are additive-only. Fields may be added freely. Fields must never be
renamed or removed while page instances exist — mark deprecated fields with `deprecated: true`
to hide them from the editor while preserving stored data. Breaking changes require a new
block type name.
