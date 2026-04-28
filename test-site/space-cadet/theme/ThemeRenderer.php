<?php
/**
 * Space Cadet CMS — Theme Renderer
 *
 * Renders a CMS page through its chosen theme layout using the existing
 * Liquid Compiler + Sandbox pipeline.
 *
 * Rendering pipeline:
 *   1. Resolve the layout file (page.layout → default.html → fallback shell)
 *   2. Pre-process {% include 'partials/...' %} tags by inlining partial content
 *   3. Render block instances from page.blocks JSON (via BlockRegistry)
 *   4. Compile the resulting source through Compiler::compile()
 *   5. Inject auto-detected asset tags into <head>
 *   6. Run the compiled PHP through Sandbox::run()
 */

declare(strict_types=1);

class ThemeRenderer
{
    private ThemeLoader $loader;
    private string      $themeName;

    public function __construct(ThemeLoader $loader, string $themeName)
    {
        $this->loader    = $loader;
        $this->themeName = $themeName;
    }

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Render a page array through its layout and return the HTML string.
     *
     * @param  array $page     Row from Page::findBySlug / Page::findById (includes 'fields')
     * @param  array $extra    Additional Liquid context variables (optional)
     * @return string          Rendered HTML
     */
    public function render(array $page, array $extra = []): string
    {
        // ── 1. Resolve layout ─────────────────────────────────────────────────
        $layoutName = $page['layout'] ?? 'default';
        if (empty($layoutName)) {
            $layoutName = 'default';
        }

        $layoutPath = $this->loader->layoutPath($layoutName);

        // Try "default" as a final fallback if the requested layout is missing
        if (!$layoutPath && $layoutName !== 'default') {
            $layoutPath = $this->loader->layoutPath('default');
        }

        if (!$layoutPath) {
            return '';   // Caller falls back to plain shell
        }

        // ── 2. Build Liquid context ───────────────────────────────────────────
        $fields  = $page['fields'] ?? [];

        // Render block instances into an HTML string for use as {{ blocks }} in layouts
        $blocksHtml = $this->renderBlocks($page['blocks'] ?? []);

        $context = array_merge($fields, $extra, [
            'title'      => $page['title']               ?? '',
            'slug'       => $page['slug']                ?? '',
            'meta_title' => $page['meta_title'] ?: ($page['title'] ?? ''),
            'meta_desc'  => $page['meta_desc']           ?? '',
            'layout'     => $layoutName,
            'blocks'     => $blocksHtml,
            'page'       => $page,
        ]);

        // ── 3. Read & pre-process layout source ───────────────────────────────
        $source = (string) file_get_contents($layoutPath);
        $source = $this->inlinePartials($source);

        // ── 4. Inject asset tags ──────────────────────────────────────────────
        $source = $this->injectAssets($source);

        // ── 5. Compile + run ──────────────────────────────────────────────────
        $compiled     = Compiler::compile($source);
        $compiledPath = $this->writeTempCompiled($compiled);

        $html = Sandbox::run($compiledPath, $context);

        // Clean up temp file
        @unlink($compiledPath);

        return $html;
    }

    /**
     * Render an ordered array of block instances to an HTML string.
     *
     * Each instance has the shape: { "type": "hero", "data": { ... } }
     *
     * For each instance:
     *   1. Look up the block definition in the theme's blocks/ directory
     *   2. Pass instance data as Liquid context variables
     *   3. Inline any {% include 'partials/...' %} in the block template
     *   4. Compile and run through the Sandbox
     *
     * Missing block types emit a <!-- block: {type} not found --> comment
     * rather than crashing.
     *
     * @param  array $blocks  Decoded blocks array from page.blocks JSON
     * @return string         Concatenated rendered HTML for all block instances
     */
    public function renderBlocks(array $blocks): string
    {
        if (empty($blocks)) {
            return '';
        }

        $blocksDir = $this->loader->themeDir() . '/blocks';
        $html      = '';

        foreach ($blocks as $instance) {
            $type = (string) ($instance['type'] ?? '');
            $data = (array)  ($instance['data']  ?? []);

            if ($type === '') {
                continue;
            }

            $def = BlockRegistry::get($type, $blocksDir);

            if ($def === null) {
                $html .= '<!-- block: ' . htmlspecialchars($type, ENT_QUOTES) . ' not found -->' . "\n";
                continue;
            }

            // Build context from instance data (field values)
            $blockContext = $data;

            // Inline partials inside the block template
            $templateSource = $this->inlinePartials($def['template']);

            // Compile and run
            $compiled     = Compiler::compile($templateSource);
            $compiledPath = $this->writeTempCompiled($compiled);

            $blockHtml = Sandbox::run($compiledPath, $blockContext);
            @unlink($compiledPath);

            $html .= $blockHtml;
        }

        return $html;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Inline all {% include 'partials/...' %} tags in the source by replacing
     * them with the actual partial file content before compilation.
     *
     * This makes partial content part of the same Liquid compilation pass,
     * so Liquid variables in partials resolve correctly.
     */
    private function inlinePartials(string $source): string
    {
        return preg_replace_callback(
            '/\{%[-\s]*include\s+["\']([^"\']+)["\']\s*[-\s]*%\}/',
            function (array $m) {
                $relativePath = $m[1];
                $content      = $this->loader->partial($relativePath);

                // If the partial was found, inline it recursively (partials can include partials)
                if ($content !== '') {
                    return $this->inlinePartials($content);
                }

                // Partial not found — leave a comment so the developer can diagnose
                return '<!-- partial not found: ' . htmlspecialchars($relativePath, ENT_QUOTES) . ' -->';
            },
            $source
        ) ?? $source;
    }

    /**
     * Auto-inject style.css and app.js asset tags before </head> when those
     * files exist in the theme's assets/ directory.
     */
    private function injectAssets(string $source): string
    {
        $tags = '';

        $cssUrl = $this->loader->assetUrl('style.css', $this->themeName);
        if ($cssUrl) {
            $cssUrl = htmlspecialchars($cssUrl, ENT_QUOTES);
            $tags  .= "  <link rel=\"stylesheet\" href=\"{$cssUrl}\">\n";
        }

        $jsUrl = $this->loader->assetUrl('app.js', $this->themeName);
        if ($jsUrl) {
            $jsUrl = htmlspecialchars($jsUrl, ENT_QUOTES);
            $tags .= "  <script src=\"{$jsUrl}\" defer></script>\n";
        }

        if ($tags === '') {
            return $source;
        }

        // Insert just before </head>
        if (stripos($source, '</head>') !== false) {
            $source = str_ireplace('</head>', $tags . '</head>', $source);
        } else {
            // No </head> — prepend tags at top
            $source = $tags . $source;
        }

        return $source;
    }

    /**
     * Write compiled PHP to a unique temp file and return its path.
     * The caller is responsible for deleting it after use.
     */
    private function writeTempCompiled(string $compiled): string
    {
        $path = SC_CACHE . '/theme_' . uniqid('', true) . '.php';
        file_put_contents($path, $compiled);
        return $path;
    }
}
