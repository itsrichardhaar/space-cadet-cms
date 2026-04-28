<?php
/**
 * Space Cadet CMS — BlockRegistry Tests
 */

require_once __DIR__ . '/../php/theme/BlockRegistry.php';

// ── BlockRegistry::parse() ────────────────────────────────────────────────────

it('parses valid frontmatter and returns block definition', function () {
    $content = <<<'HTML'
---
name: Hero
icon: layout
fields:
  - { name: headline, type: text, label: Headline }
  - { name: image, type: media, label: Image, deprecated: true }
---
<section class="block-hero"><h1>{{ headline }}</h1></section>
HTML;
    $result = BlockRegistry::parse($content);
    assert_not_null($result);
    assert_equals('Hero', $result['name']);
    assert_equals('layout', $result['icon']);
    assert_count(1, $result['fields']); // deprecated field excluded
    assert_equals('headline', $result['fields'][0]['name']);
    assert_contains('block-hero', $result['template']);
});

it('excludes deprecated fields from the schema', function () {
    $content = <<<'HTML'
---
name: Features
icon: grid
fields:
  - { name: title, type: text, label: Title }
  - { name: old_field, type: text, label: Old, deprecated: true }
  - { name: subtitle, type: text, label: Subtitle }
---
<section class="block-features">{{ title }}</section>
HTML;
    $result = BlockRegistry::parse($content);
    assert_not_null($result);
    assert_count(2, $result['fields']);
    assert_equals('title', $result['fields'][0]['name']);
    assert_equals('subtitle', $result['fields'][1]['name']);
});

it('returns null for content with no frontmatter', function () {
    $result = BlockRegistry::parse('no frontmatter here');
    assert_null($result);
});

it('returns null for content with only opening delimiter', function () {
    $result = BlockRegistry::parse("---\nname: Test\n");
    assert_null($result);
});

it('returns empty fields array when frontmatter has no fields key', function () {
    $content = <<<'HTML'
---
name: Simple
icon: box
---
<div class="block-simple">Hello</div>
HTML;
    $result = BlockRegistry::parse($content);
    assert_not_null($result);
    assert_equals('Simple', $result['name']);
    assert_count(0, $result['fields']);
});

it('preserves the Liquid template after the closing delimiter', function () {
    $content = <<<'HTML'
---
name: CTA
icon: arrow-right
fields:
  - { name: label, type: text, label: Label }
---
<div class="block-cta"><a href="#">{{ label }}</a></div>
HTML;
    $result = BlockRegistry::parse($content);
    assert_not_null($result);
    assert_contains('block-cta', $result['template']);
    assert_contains('{{ label }}', $result['template']);
});

it('parses name and icon as strings', function () {
    $content = <<<'HTML'
---
name: Rich Text
icon: type
fields:
  - { name: body, type: richtext, label: Body }
---
<div class="block-rich-text">{{{ body }}}</div>
HTML;
    $result = BlockRegistry::parse($content);
    assert_not_null($result);
    assert_equals('Rich Text', $result['name']);
    assert_equals('type', $result['icon']);
});

// ── BlockRegistry::scan() ─────────────────────────────────────────────────────

it('returns empty array for empty blocks directory', function () {
    $dir = sys_get_temp_dir() . '/sc_blocks_' . uniqid('', true);
    mkdir($dir, 0755, true);
    $result = BlockRegistry::scan($dir);
    assert_count(0, $result);
    rmdir($dir);
});

it('returns empty array for non-existent directory', function () {
    $result = BlockRegistry::scan('/tmp/sc_blocks_does_not_exist_' . uniqid());
    assert_count(0, $result);
});

it('scans and returns all valid block definitions from directory', function () {
    $dir = sys_get_temp_dir() . '/sc_blocks_' . uniqid('', true);
    mkdir($dir, 0755, true);

    file_put_contents($dir . '/hero.html', <<<'HTML'
---
name: Hero
icon: layout
fields:
  - { name: headline, type: text, label: Headline }
---
<section class="block-hero">{{ headline }}</section>
HTML);

    file_put_contents($dir . '/cta.html', <<<'HTML'
---
name: CTA
icon: arrow-right
fields:
  - { name: label, type: text, label: Label }
---
<div class="block-cta">{{ label }}</div>
HTML);

    $result = BlockRegistry::scan($dir);
    assert_count(2, $result);

    // sorted by type name: cta, hero
    assert_equals('cta', $result[0]['type']);
    assert_equals('CTA', $result[0]['name']);
    assert_equals('hero', $result[1]['type']);
    assert_equals('Hero', $result[1]['name']);

    unlink($dir . '/hero.html');
    unlink($dir . '/cta.html');
    rmdir($dir);
});

it('silently skips files with malformed frontmatter', function () {
    $dir = sys_get_temp_dir() . '/sc_blocks_' . uniqid('', true);
    mkdir($dir, 0755, true);

    file_put_contents($dir . '/valid.html', <<<'HTML'
---
name: Valid
icon: check
fields:
  - { name: text, type: text, label: Text }
---
<div class="block-valid">{{ text }}</div>
HTML);

    // Malformed — no closing ---
    file_put_contents($dir . '/broken.html', "---\nname: Broken\n");

    // No frontmatter at all
    file_put_contents($dir . '/plain.html', '<div>Just HTML</div>');

    $result = BlockRegistry::scan($dir);
    assert_count(1, $result);
    assert_equals('valid', $result[0]['type']);

    unlink($dir . '/valid.html');
    unlink($dir . '/broken.html');
    unlink($dir . '/plain.html');
    rmdir($dir);
});

it('includes the type key derived from filename', function () {
    $dir = sys_get_temp_dir() . '/sc_blocks_' . uniqid('', true);
    mkdir($dir, 0755, true);

    file_put_contents($dir . '/text-image.html', <<<'HTML'
---
name: Text + Image
icon: columns
fields:
  - { name: heading, type: text, label: Heading }
---
<div class="block-text-image">{{ heading }}</div>
HTML);

    $result = BlockRegistry::scan($dir);
    assert_count(1, $result);
    assert_equals('text-image', $result[0]['type']);
    assert_equals('Text + Image', $result[0]['name']);

    unlink($dir . '/text-image.html');
    rmdir($dir);
});

// ── BlockRegistry::get() ──────────────────────────────────────────────────────

it('returns a single block definition by type', function () {
    $dir = sys_get_temp_dir() . '/sc_blocks_' . uniqid('', true);
    mkdir($dir, 0755, true);

    file_put_contents($dir . '/hero.html', <<<'HTML'
---
name: Hero
icon: layout
fields:
  - { name: headline, type: text, label: Headline }
---
<section class="block-hero">{{ headline }}</section>
HTML);

    $result = BlockRegistry::get('hero', $dir);
    assert_not_null($result);
    assert_equals('hero', $result['type']);
    assert_equals('Hero', $result['name']);

    unlink($dir . '/hero.html');
    rmdir($dir);
});

it('returns null for a type not found in directory', function () {
    $dir = sys_get_temp_dir() . '/sc_blocks_' . uniqid('', true);
    mkdir($dir, 0755, true);

    $result = BlockRegistry::get('nonexistent', $dir);
    assert_null($result);

    rmdir($dir);
});

it('returns null for type with unsafe characters', function () {
    $dir = sys_get_temp_dir() . '/sc_blocks_' . uniqid('', true);
    mkdir($dir, 0755, true);

    $result = BlockRegistry::get('../etc/passwd', $dir);
    assert_null($result);

    rmdir($dir);
});
