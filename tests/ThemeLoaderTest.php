<?php
/**
 * Space Cadet CMS — ThemeLoader Tests
 */

require_once __DIR__ . '/../php/theme/ThemeLoader.php';

// ── helpers ───────────────────────────────────────────────────────────────────

function sc_make_theme_dir(): string
{
    $dir = sys_get_temp_dir() . '/sc_test_theme_' . uniqid('', true);
    mkdir($dir . '/layouts',  0755, true);
    mkdir($dir . '/partials', 0755, true);
    mkdir($dir . '/assets',   0755, true);
    return $dir;
}

function sc_remove_dir(string $dir): void
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

// ── ThemeLoader::layouts() ────────────────────────────────────────────────────

it('returns available layouts from theme directory', function () {
    $dir = sc_make_theme_dir();
    file_put_contents($dir . '/layouts/default.html', '<html>{{ title }}</html>');

    $loader  = new ThemeLoader($dir);
    $layouts = $loader->layouts();

    assert_equals(['default'], $layouts);

    sc_remove_dir($dir);
});

it('returns multiple layouts sorted alphabetically', function () {
    $dir = sc_make_theme_dir();
    file_put_contents($dir . '/layouts/wide.html',    '<html>wide</html>');
    file_put_contents($dir . '/layouts/default.html', '<html>default</html>');
    file_put_contents($dir . '/layouts/landing.html', '<html>landing</html>');

    $loader  = new ThemeLoader($dir);
    $layouts = $loader->layouts();

    assert_equals(['default', 'landing', 'wide'], $layouts);

    sc_remove_dir($dir);
});

it('returns empty array when layouts directory is missing', function () {
    $dir = sys_get_temp_dir() . '/sc_test_nolayouts_' . uniqid('', true);
    mkdir($dir, 0755, true);

    $loader  = new ThemeLoader($dir);
    $layouts = $loader->layouts();

    assert_equals([], $layouts);

    rmdir($dir);
});

it('returns empty array for theme directory with empty layouts dir', function () {
    $dir = sc_make_theme_dir();

    $loader  = new ThemeLoader($dir);
    $layouts = $loader->layouts();

    assert_equals([], $layouts);

    sc_remove_dir($dir);
});

// ── ThemeLoader::layoutPath() ─────────────────────────────────────────────────

it('returns the path to an existing layout', function () {
    $dir = sc_make_theme_dir();
    file_put_contents($dir . '/layouts/default.html', '<html>{{ title }}</html>');

    $loader = new ThemeLoader($dir);
    $path   = $loader->layoutPath('default');

    assert_not_null($path);
    assert_true(file_exists($path));

    sc_remove_dir($dir);
});

it('returns null for a missing layout', function () {
    $dir = sc_make_theme_dir();

    $loader = new ThemeLoader($dir);
    $path   = $loader->layoutPath('nonexistent');

    assert_null($path);

    sc_remove_dir($dir);
});

it('accepts layout name with .html suffix', function () {
    $dir = sc_make_theme_dir();
    file_put_contents($dir . '/layouts/default.html', '<html>{{ title }}</html>');

    $loader = new ThemeLoader($dir);
    $path   = $loader->layoutPath('default.html');

    assert_not_null($path);

    sc_remove_dir($dir);
});

// ── ThemeLoader::partial() ────────────────────────────────────────────────────

it('returns partial content when file exists', function () {
    $dir = sc_make_theme_dir();
    file_put_contents($dir . '/partials/nav.html', '<nav>My Nav</nav>');

    $loader  = new ThemeLoader($dir);
    $content = $loader->partial('partials/nav.html');

    assert_equals('<nav>My Nav</nav>', $content);

    sc_remove_dir($dir);
});

it('returns empty string for missing partial', function () {
    $dir = sc_make_theme_dir();

    $loader  = new ThemeLoader($dir);
    $content = $loader->partial('partials/missing.html');

    assert_equals('', $content);

    sc_remove_dir($dir);
});

it('blocks directory traversal in partial path', function () {
    $dir = sc_make_theme_dir();

    $loader  = new ThemeLoader($dir);
    $content = $loader->partial('../../../etc/passwd');

    assert_equals('', $content);

    sc_remove_dir($dir);
});

// ── ThemeLoader::assetUrl() ───────────────────────────────────────────────────

it('returns asset URL when file exists', function () {
    $dir = sc_make_theme_dir();
    file_put_contents($dir . '/assets/style.css', 'body { margin: 0; }');

    $loader = new ThemeLoader($dir);
    $url    = $loader->assetUrl('style.css', 'default');

    assert_equals('/themes/default/assets/style.css', $url);

    sc_remove_dir($dir);
});

it('returns null for missing asset', function () {
    $dir = sc_make_theme_dir();

    $loader = new ThemeLoader($dir);
    $url    = $loader->assetUrl('app.js', 'default');

    assert_null($url);

    sc_remove_dir($dir);
});
