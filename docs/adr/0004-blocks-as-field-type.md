# ADR 0004 — Blocks as a Field Type, Not a Collection Setting

**Status:** Accepted

## Context

Some collection items need flexible, composed layouts (e.g. a Case Study with a unique
arrangement of sections). Three options were considered:
- Fields-only collections with a fixed template (no block composition on items)
- A collection-level "enable blocks" toggle
- A `blocks` field type in the schema editor

## Decision

Block composition on collection items is enabled by adding a field of `type: blocks` to
the collection's schema. This field behaves like any other field — it appears in the item
editor, is referenced in the template as `{{ fields.body }}`, and is stored as JSON in the
item's `data` column alongside all other fields.

## Consequences

- The mental model is consistent: blocks is just another field type, like `richtext` or `media`.
- Opt-in per collection with no special collection-level setting to discover.
- Developers reference it in templates the same way as any other field.
- A collection can have multiple blocks fields if needed (e.g. a `sidebar` blocks field
  alongside a `body` blocks field).
- The block composition UI in the item editor mirrors the page builder exactly.
