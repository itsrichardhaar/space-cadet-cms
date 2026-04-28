<?php
/**
 * Space Cadet CMS — PreviewMode Tests
 *
 * Verifies that ThemeRenderer:
 *   - In preview mode: injects data-block-index / data-block-type onto block
 *     wrapper elements and injects window.__SC_BLOCKS__ before </body>.
 *   - In production mode: does NOT add those attributes.
 */

require_once __DIR__ . '/../php/theme/ThemeLoader.php';
require_once __DIR__ . '/../php/theme/ThemeRenderer.php';
require_once __DIR__ . '/../php/theme/BlockRegistry.php';
require_once __DIR__ . '/../php/templates/Compiler.php';
require_once __DIR__ . '/../php/templates/Sandbox.php';

// ── Helpers ───────────────────────────────────────────────────────────────────

function pm_make_theme(array $blocks = []): string
{
    $dir = sys_get_temp_dir() . '/sc_pm_theme_' . uniqid('', true);
    mkdir($dir . '/layouts', 0755, true);
    mkdir($dir . '/blocks',  0755, true);
    mkdir($dir . '/assets',  0755, true);

    // Default layout — outputs {{{ blocks }}} (raw, unescaped) wrapped in a body
    file_put_contents($dir . '/layouts/default.html', <<<'HTML'
<!doctype html>
<html><head><title>{{ title }}</title></head>
<body>
{{{ blocks }}}
</body>
</html>
HTML);

    // Write block files
    foreach ($blocks as $type => $template) {
        file_put_contents($dir . '/blocks/' . $type . '.html', <<<HTML
---
name: {$type}
icon: box
fields:
  - { name: text, type: text, label: Text }
---
{$template}
HTML);
    }

    return $dir;
}

function pm_remove_dir(string $dir): void
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

// Define SC_CACHE for temp files if not already set
if (!defined('SC_CACHE')) {
    define('SC_CACHE', sys_get_temp_dir());
}

// ── Tests ─────────────────────────────────────────────────────────────────────

it('preview mode adds data-block-index and data-block-type to block wrappers', function () {
    $dir = pm_make_theme([
        'hero' => '<section class="block-hero"><h1>{{ text }}</h1></section>',
    ]);

    $loader   = new ThemeLoader($dir);
    $renderer = new ThemeRenderer($loader, 'test');

    $page = [
        'title'    => 'Test',
        'slug'     => 'test',
        'layout'   => 'default',
        'meta_title' => '',
        'meta_desc'  => '',
        'fields'   => [],
        'blocks'   => [
            ['type' => 'hero', 'data' => ['text' => 'Hello']],
        ],
    ];

    $html = $renderer->render($page, [], true);

    assert_contains('data-block-index="0"', $html);
    assert_contains('data-block-type="hero"', $html);

    pm_remove_dir($dir);
});

it('preview mode injects window.__SC_BLOCKS__ before </body>', function () {
    $dir = pm_make_theme([
        'cta' => '<div class="block-cta"><a>{{ text }}</a></div>',
    ]);

    $loader   = new ThemeLoader($dir);
    $renderer = new ThemeRenderer($loader, 'test');

    $page = [
        'title'    => 'Test',
        'slug'     => 'test',
        'layout'   => 'default',
        'meta_title' => '',
        'meta_desc'  => '',
        'fields'   => [],
        'blocks'   => [
            ['type' => 'cta', 'data' => ['text' => 'Click me']],
        ],
    ];

    $html = $renderer->render($page, [], true);

    assert_contains('window.__SC_PREVIEW__ = true', $html);
    assert_contains('window.__SC_BLOCKS__', $html);
    assert_contains('"type":"cta"', $html);

    pm_remove_dir($dir);
});

it('production mode does NOT add data-block-index to block wrappers', function () {
    $dir = pm_make_theme([
        'hero' => '<section class="block-hero"><h1>{{ text }}</h1></section>',
    ]);

    $loader   = new ThemeLoader($dir);
    $renderer = new ThemeRenderer($loader, 'test');

    $page = [
        'title'    => 'Test',
        'slug'     => 'test',
        'layout'   => 'default',
        'meta_title' => '',
        'meta_desc'  => '',
        'fields'   => [],
        'blocks'   => [
            ['type' => 'hero', 'data' => ['text' => 'Hello']],
        ],
    ];

    $html = $renderer->render($page, [], false);

    assert_false(str_contains($html, 'data-block-index='), 'Production output must not contain data-block-index');
    assert_false(str_contains($html, 'data-block-type='), 'Production output must not contain data-block-type');
    assert_false(str_contains($html, 'window.__SC_PREVIEW__'), 'Production output must not contain preview script');

    pm_remove_dir($dir);
});

it('preview mode with no blocks injects empty __SC_BLOCKS__ array', function () {
    $dir = pm_make_theme();

    $loader   = new ThemeLoader($dir);
    $renderer = new ThemeRenderer($loader, 'test');

    $page = [
        'title'    => 'Empty',
        'slug'     => 'empty',
        'layout'   => 'default',
        'meta_title' => '',
        'meta_desc'  => '',
        'fields'   => [],
        'blocks'   => [],
    ];

    $html = $renderer->render($page, [], true);

    assert_contains('window.__SC_BLOCKS__ = []', $html);

    pm_remove_dir($dir);
});

it('preview mode correctly indexes multiple blocks', function () {
    $dir = pm_make_theme([
        'hero'     => '<section class="block-hero"><h1>{{ text }}</h1></section>',
        'features' => '<section class="block-features"><p>{{ text }}</p></section>',
    ]);

    $loader   = new ThemeLoader($dir);
    $renderer = new ThemeRenderer($loader, 'test');

    $page = [
        'title'    => 'Multi',
        'slug'     => 'multi',
        'layout'   => 'default',
        'meta_title' => '',
        'meta_desc'  => '',
        'fields'   => [],
        'blocks'   => [
            ['type' => 'hero',     'data' => ['text' => 'Hello']],
            ['type' => 'features', 'data' => ['text' => 'World']],
        ],
    ];

    $html = $renderer->render($page, [], true);

    assert_contains('data-block-index="0"', $html);
    assert_contains('data-block-type="hero"', $html);
    assert_contains('data-block-index="1"', $html);
    assert_contains('data-block-type="features"', $html);

    pm_remove_dir($dir);
});
