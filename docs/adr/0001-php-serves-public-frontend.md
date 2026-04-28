# ADR 0001 — PHP Serves the Public Frontend

**Status:** Accepted

## Context

Space Cadet CMS started as a purely headless CMS (REST + GraphQL API only). To support a
Webflow-style visual builder with a live preview, the CMS needs to render pages itself.
The alternative was to stay headless and bridge into a developer's separate frontend via iframe.

## Decision

PHP renders the public-facing website directly using the Liquid template engine. Visitors
receive plain HTML — no JavaScript framework required on the frontend. The headless API
(REST + GraphQL) remains fully intact for developers who choose to use it instead.

## Consequences

- The visual builder can own the full render pipeline — block composition, Liquid rendering,
  design token injection — without depending on an external frontend.
- Developers can choose: use the rendered frontend (theme-based), or consume the API headlessly.
  Both modes coexist on the same install.
- Space Cadet must maintain a Liquid template engine and a public router in addition to the API.
