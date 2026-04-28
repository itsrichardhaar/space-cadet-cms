# ADR 0006 — No Container/Layout Blocks (Flat Block Model)

**Status:** Accepted

## Context

Visual builders like Webflow support container blocks (Section, Div, Grid) that hold other
blocks, enabling arbitrary nested layouts. The question was whether Space Cadet CMS should
support this pattern.

## Decision

Blocks are always flat — a page is an ordered list of blocks, never a tree. There are no
container, section, div, or wrapper block types. Each block manages its own internal layout
entirely through CSS.

## Consequences

- Page block composition is a flat JSON array — no recursive structures.
- The builder drag-and-drop is a simple sorted list, not a tree editor.
- The postMessage bridge never needs to track nesting context.
- The Liquid renderer walks a flat array — no recursive rendering logic.
- Developers who need a two-column layout write a block whose CSS handles columns.
  The builder has no knowledge of internal block layout.
- A developer wanting a layout not covered by starter blocks writes a custom block.
  This is the correct division: CMS manages content composition, CSS manages layout.
- Adding nesting later would be a significant breaking change to the data model and builder.
  This decision is intentionally hard to reverse — it keeps the system tractable.
