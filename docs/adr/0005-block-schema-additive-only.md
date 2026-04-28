# ADR 0005 — Block Schemas Are Additive-Only

**Status:** Accepted

## Context

When a developer changes a block's field schema after instances of that block already exist
on pages, stored JSON data can become mismatched with the current schema. Three options:
graceful degradation with silent orphaned data, schema versioning with explicit migrations,
or an additive-only convention with a deprecation flag.

## Decision

Block schemas follow an additive-only contract:
- Fields may be **added** freely at any time.
- Fields must never be **renamed** or **removed** while instances exist.
- A field that is no longer needed is marked `deprecated: true` in frontmatter.
  Deprecated fields are hidden in the builder panel but their stored data is preserved.
- Breaking changes (rename, structural overhaul) require creating a **new block type**
  with a new name. The old block type remains available until pages are migrated manually.

## Consequences

- Zero runtime complexity — no version checks, no migration engine.
- Old block instances always render correctly — missing fields are empty strings,
  deprecated fields are ignored by the template.
- Developers must treat block field names as a public API once instances exist in production.
- Documented clearly as a constraint in the theme developer guide.
- Same contract as CSS class names: add freely, never remove without a deprecation period.
