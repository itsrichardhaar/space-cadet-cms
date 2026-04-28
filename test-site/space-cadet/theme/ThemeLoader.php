<?php
/**
 * Space Cadet CMS — Theme Loader
 *
 * Resolves paths within a theme directory and provides helpers for
 * listing layouts, partials, and asset files.
 *
 * Directory contract (all relative to the theme root):
 *   layouts/          ← *.html layout templates
 *   partials/         ← *.html partial fragments
 *   assets/           ← style.css, app.js, and other static files
 */

declare(strict_types=1);

class ThemeLoader
{
    private string $themeDir;

    public function __construct(string $themeDir)
    {
        // Resolve symlinks so that realpath() comparisons in partial() work correctly
        // on macOS where /var/folders → /private/var/folders, etc.
        $resolved = realpath($themeDir);
        $this->themeDir = rtrim($resolved !== false ? $resolved : $themeDir, '/');
    }

    // ── Factory ───────────────────────────────────────────────────────────────

    /**
     * Build a ThemeLoader for the currently active theme.
     *
     * Reads `active_theme` from the settings table (defaults to "default").
     * Returns null if the theme directory does not exist.
     */
    public static function forActiveTheme(): ?self
    {
        $themeName = self::activeThemeName();
        $dir       = SC_ROOT . '/../themes/' . $themeName;

        if (!is_dir($dir)) {
            return null;
        }

        return new self($dir);
    }

    /**
     * Return the active theme name from settings, falling back to "default".
     */
    public static function activeThemeName(): string
    {
        try {
            $row = Database::queryOne(
                "SELECT value FROM settings WHERE key = 'active_theme'"
            );
            return ($row && $row['value'] !== '') ? $row['value'] : 'default';
        } catch (Throwable) {
            return 'default';
        }
    }

    // ── Layouts ───────────────────────────────────────────────────────────────

    /**
     * Return a list of layout names (basename without .html extension).
     *
     * @return string[]
     */
    public function layouts(): array
    {
        $dir = $this->themeDir . '/layouts';

        if (!is_dir($dir)) {
            return [];
        }

        $names = [];
        foreach (glob($dir . '/*.html') ?: [] as $path) {
            $names[] = basename($path, '.html');
        }

        sort($names);
        return $names;
    }

    /**
     * Return the absolute path to a layout file, or null if it does not exist.
     */
    public function layoutPath(string $name): ?string
    {
        // Strip .html suffix if caller included it
        $name = basename($name, '.html');
        $path = $this->themeDir . '/layouts/' . $name . '.html';
        return file_exists($path) ? $path : null;
    }

    // ── Partials ──────────────────────────────────────────────────────────────

    /**
     * Return the content of a partial file (e.g. "partials/nav.html"),
     * or an empty string if it does not exist.
     */
    public function partial(string $relativePath): string
    {
        $path = $this->themeDir . '/' . ltrim($relativePath, '/');

        // Safety: prevent directory traversal
        $resolved = realpath($path);
        if (!$resolved || !str_starts_with($resolved, $this->themeDir)) {
            return '';
        }

        return file_exists($resolved) ? (string) file_get_contents($resolved) : '';
    }

    // ── Assets ────────────────────────────────────────────────────────────────

    /**
     * Return the public URL path for an asset file, or null if it does not exist.
     *
     * @param string $filename  e.g. "style.css" or "app.js"
     */
    public function assetUrl(string $filename, string $themeName): ?string
    {
        $path = $this->themeDir . '/assets/' . $filename;
        return file_exists($path) ? '/themes/' . $themeName . '/assets/' . $filename : null;
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    public function themeDir(): string
    {
        return $this->themeDir;
    }
}
