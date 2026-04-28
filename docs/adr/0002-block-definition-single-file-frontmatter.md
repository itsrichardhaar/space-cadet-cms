# ADR 0002 — Block Definition: Single File with YAML Frontmatter

**Status:** Accepted

## Context

Custom blocks need to declare both a field schema (what editors can change) and a template
(how the block renders). Two approaches were considered: a two-file approach (separate `.json`
schema + `.html` template) or a single file with YAML frontmatter.

## Decision

Each block is a single HTML file with YAML frontmatter declaring the block's name, icon, and
field schema, followed by the Liquid template.

```html
---
name: Hero
icon: layout
fields:
  - { name: headline, type: text, label: Headline }
  - { name: image, type: media, label: Background Image }
---
<section class="block-hero">
  <h1 class="block-hero__headline">{{ headline }}</h1>
</section>
```

## Consequences

- One file to create, edit, and version-control per block.
- Schema and template are co-located — easier to understand a block at a glance.
- Auto-discovery by scanning `blocks/` is simple — one file = one block, no manifest needed.
- Parsing YAML frontmatter adds a small dependency on the template engine bootstrap.
