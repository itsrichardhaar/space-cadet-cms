# ADR 0003 — Page Block Composition Stored as JSON Column

**Status:** Accepted

## Context

A page's block composition (ordered list of block instances + their field values) needs to be
persisted. Two approaches were considered: a JSON column on the `pages` table, or a separate
`page_blocks` table with one row per block instance.

## Decision

Block composition is stored as a single `blocks` JSON column on the `pages` table — an ordered
array of block instance objects:

```json
[
  { "type": "hero", "data": { "headline": "Welcome", "image": "hero.jpg" } },
  { "type": "features", "data": { "title": "Why us", "items": [...] } }
]
```

## Consequences

- Block composition is always loaded and saved as a unit — a single read gets the whole page.
- No joins, no separate queries, no ordering column to maintain.
- Individual block instances cannot be queried in isolation via SQL — acceptable since that
  use case does not exist.
- Aligns with the existing pattern of JSON columns used throughout Space Cadet CMS
  (collection item data, global fields, etc.).
