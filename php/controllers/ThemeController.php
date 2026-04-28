<?php
/**
 * Space Cadet CMS — Theme Controller
 *
 * Provides read-only API endpoints for the active theme metadata used
 * by the visual builder (block schemas, layout list).
 */

class ThemeController
{
    /**
     * GET api.php?action=theme
     *
     * Returns active theme name and available layouts.
     */
    public function show(Request $req): void
    {
        Auth::requireRole('editor');

        $themeName = ThemeLoader::activeThemeName();
        $loader    = ThemeLoader::forActiveTheme();

        if (!$loader) {
            Response::success([
                'theme'   => $themeName,
                'layouts' => [],
                'blocks'  => [],
            ]);
            return;
        }

        $blocksDir = $loader->themeDir() . '/blocks';
        $blocks    = BlockRegistry::scan($blocksDir);

        // Strip template source from API output — not needed by the builder
        $blocks = array_map(static function (array $block): array {
            unset($block['template']);
            return $block;
        }, $blocks);

        Response::success([
            'theme'   => $themeName,
            'layouts' => $loader->layouts(),
            'blocks'  => $blocks,
        ]);
    }

    /**
     * GET api.php?action=theme/blocks
     *
     * Returns the full block schema list for the active theme.
     * Used by the builder sidebar to render field editors.
     */
    public function blocks(Request $req): void
    {
        Auth::requireRole('editor');

        $loader = ThemeLoader::forActiveTheme();

        if (!$loader) {
            Response::success([]);
            return;
        }

        $blocksDir = $loader->themeDir() . '/blocks';
        $blocks    = BlockRegistry::scan($blocksDir);

        // Strip template source — builder only needs schema (name, type, fields)
        $blocks = array_map(static function (array $block): array {
            unset($block['template']);
            return $block;
        }, $blocks);

        Response::success($blocks);
    }
}
