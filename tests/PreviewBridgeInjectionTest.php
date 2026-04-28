<?php
/**
 * Space Cadet CMS — PreviewBridge Injection Tests
 *
 * Verifies that ThemeRenderer:
 *   - In preview mode: injects window.__SC_PREVIEW__ script and preview bridge JS
 *   - In preview mode: output contains data-block-index attributes on block wrappers
 *   - In production mode: output does NOT contain data-block-index or preview bridge
 */

require_once __DIR__ . '/../php/theme/ThemeLoader.php';
require_once __DIR__ . '/../php/theme/ThemeRenderer.php';
require_once __DIR__ . '/../php/theme/BlockRegistry.php';
require_once __DIR__ . '/../php/templates/Compiler.php';
require_once __DIR__ . '/../php/templates/Sandbox.php';

// ── Helpers ───────────────────────────────────────────────────────────────────

function pb_make_theme(array $blocks = []): string
{
    $dir = sys_get_temp_dir() . '/sc_pb_theme_' . uniqid('', true);
    mkdir($dir . '/layouts', 0755, true);
    mkdir($dir . '/blocks',  0755, true);
    mkdir($dir . '/assets',  0755, true);

    file_put_contents($dir . '/layouts/default.html', <<<'HTML'
<!doctype html>
<html><head><title>{{ title }}</title></head>
<body>
{{{ blocks }}}
</body>
</html>
HTML);

    foreach ($blocks as $type => $template) {
        file_put_contents($dir . '/blocks/' . $type . '.html', <<<HTML
---
name: {$type}
icon: box
fields:
  - { name: headline, type: text, label: Headline }
---
{$template}
HTML);
    }

    return $dir;
}

function pb_remove_dir(string $dir): void
{
    if (!is_dir($dir)) return;
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($dir);
}

if (!defined('SC_CACHE')) {
    define('SC_CACHE', sys_get_temp_dir());
}

// ── Tests ─────────────────────────────────────────────────────────────────────

it('preview mode output contains window.__SC_PREVIEW__', function () {
    $dir = pb_make_theme([
        'hero' => '<section class="block-hero"><h1>{{ headline }}</h1></section>',
    ]);

    $loader   = new ThemeLoader($dir);
    $renderer = new ThemeRenderer($loader, 'test');

    $page = [
        'title'      => 'Test',
        'slug'       => 'test',
        'layout'     => 'default',
        'meta_title' => '',
        'meta_desc'  => '',
        'fields'     => [],
        'blocks'     => [
            ['type' => 'hero', 'data' => ['headline' => 'Hello']],
        ],
    ];

    $html = $renderer->render($page, [], true);

    assert_contains('window.__SC_PREVIEW__', $html, 'Preview mode must inject window.__SC_PREVIEW__');

    pb_remove_dir($dir);
});

it('preview mode output contains data-block-index on block wrapper', function () {
    $dir = pb_make_theme([
        'hero' => '<section class="block-hero"><h1>{{ headline }}</h1></section>',
    ]);

    $loader   = new ThemeLoader($dir);
    $renderer = new ThemeRenderer($loader, 'test');

    $page = [
        'title'      => 'Test',
        'slug'       => 'test',
        'layout'     => 'default',
        'meta_title' => '',
        'meta_desc'  => '',
        'fields'     => [],
        'blocks'     => [
            ['type' => 'hero', 'data' => ['headline' => 'Hello']],
        ],
    ];

    $html = $renderer->render($page, [], true);

    assert_contains('data-block-index="0"', $html, 'Preview mode must add data-block-index to block wrappers');

    pb_remove_dir($dir);
});

it('preview mode output contains preview bridge script (window.__SC_PREVIEW__ guard)', function () {
    $dir = pb_make_theme([
        'cta' => '<div class="block-cta"><p>{{ headline }}</p></div>',
    ]);

    $loader   = new ThemeLoader($dir);
    $renderer = new ThemeRenderer($loader, 'test');

    $page = [
        'title'      => 'Test',
        'slug'       => 'test',
        'layout'     => 'default',
        'meta_title' => '',
        'meta_desc'  => '',
        'fields'     => [],
        'blocks'     => [
            ['type' => 'cta', 'data' => ['headline' => 'Click me']],
        ],
    ];

    $html = $renderer->render($page, [], true);

    // The bridge JS is injected inline and contains the guard check
    assert_contains('window.__SC_PREVIEW__', $html, 'Preview mode must inject preview bridge guard');
    // The bridge postMessage logic is present
    assert_contains('postMessage', $html, 'Preview bridge must include postMessage calls');
    // The bridge handles field:update messages
    assert_contains('field:update', $html, 'Preview bridge must handle field:update messages');

    pb_remove_dir($dir);
});

it('production mode output does NOT contain data-block-index', function () {
    $dir = pb_make_theme([
        'hero' => '<section class="block-hero"><h1>{{ headline }}</h1></section>',
    ]);

    $loader   = new ThemeLoader($dir);
    $renderer = new ThemeRenderer($loader, 'test');

    $page = [
        'title'      => 'Test',
        'slug'       => 'test',
        'layout'     => 'default',
        'meta_title' => '',
        'meta_desc'  => '',
        'fields'     => [],
        'blocks'     => [
            ['type' => 'hero', 'data' => ['headline' => 'Hello']],
        ],
    ];

    $html = $renderer->render($page, [], false);

    assert_false(
        str_contains($html, 'data-block-index='),
        'Production mode must not contain data-block-index'
    );
    assert_false(
        str_contains($html, 'window.__SC_PREVIEW__'),
        'Production mode must not contain window.__SC_PREVIEW__'
    );
    assert_false(
        str_contains($html, 'preview-bridge'),
        'Production mode must not contain preview bridge reference'
    );

    pb_remove_dir($dir);
});
