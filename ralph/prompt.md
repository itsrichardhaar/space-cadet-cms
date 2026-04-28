# ISSUES

GitHub issues are provided at start of context. Parse it to get open issues with their bodies and comments.

You will work on the AFK issues only, not the HITL ones.

You've also been passed a file containing the last few commits. Review these to understand what work has been done.

If all AFK tasks are complete, output <promise>NO MORE TASKS</promise>.

# TASK SELECTION

Pick the next task. Prioritize tasks in this order:

1. Critical bugfixes
2. Development infrastructure

Getting development infrastructure like tests and dev scripts ready is an important precursor to building features.

3. Tracer bullets for new features

Tracer bullets are small slices of functionality that go through all layers of the system, allowing you to test and validate your approach early. This helps in identifying potential issues and ensures that the overall architecture is sound before investing significant time in development.

TL;DR - build a tiny, end-to-end slice of the feature first, then expand it out.

4. Polish and quick wins
5. Refactors

# EXPLORATION

Explore the repo. Read CONTEXT.md and docs/adr/ before touching the theme system, block system, or page data model.

# IMPLEMENTATION

Where possible, use a red-green refactor loop:

## RED: Write a single failing test

Add a test to the appropriate file in `tests/`. Run `npm run test` — it must fail.

## GREEN: Write the minimal implementation

Write just enough PHP to make that test pass. Run `npm run test` — it must pass.

## RED: Write another failing test

Repeat until implementation is complete.

# STACK RULES

- **PHP**: PHP 8.1+, no Composer. All new classes go in `php/`. Follow the existing pattern: one class per file, loaded via `require_once`.
- **Svelte**: Svelte 5 runes only (`$state`, `$derived`, `$effect`, `$props`). No `export let`, no `$:`, no `on:click`. All `goto()` and `href` must include the `/admin` prefix.
- **SQLite**: Use `Database::query()`, `Database::queryOne()`, `Database::execute()` — never raw PDO.
- **Theme system**: Read ADR 0001–0006 in `docs/adr/` before modifying the block system, page schema, or renderer.

# FEEDBACK LOOPS

Before committing, run all three feedback loops and fix any failures:

```bash
# 1. PHP syntax check — catches parse errors across all PHP files
npm run check:php

# 2. PHP unit tests — runs tests/
npm run test

# 3. Svelte build — catches compilation errors in the admin SPA
npm run build
```

All three must pass before committing.

# COMMIT

Make a git commit. The commit message must:

1. Include key decisions made
2. Include files changed
3. Blockers or notes for next iteration

# THE ISSUE

If the task is complete, close the original GitHub issue.

If the task is not complete, leave a comment on the GitHub issue with what was done.

# FINAL RULES

ONLY WORK ON A SINGLE TASK.
