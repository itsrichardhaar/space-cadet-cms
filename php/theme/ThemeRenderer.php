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
     * @param  array $page        Row from Page::findBySlug / Page::findById (includes 'fields')
     * @param  array $extra       Additional Liquid context variables (optional)
     * @param  bool  $previewMode When true, injects data-block-* attributes and window.__SC_BLOCKS__ script
     * @return string             Rendered HTML
     */
    public function render(array $page, array $extra = [], bool $previewMode = false): string
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
        $blocksHtml = $this->renderBlocks($page['blocks'] ?? [], $previewMode);

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

        // ── 6. Preview-mode post-processing ──────────────────────────────────
        if ($previewMode) {
            $html = $this->injectPreviewData($html, $page['blocks'] ?? []);
            $html = $this->injectPreviewBridge($html);
        }

        return $html;
    }

    /**
     * Inject preview-mode metadata into the rendered HTML:
     * - Adds data-block-index and data-block-type to outermost block wrapper elements
     * - Injects window.__SC_PREVIEW__ and window.__SC_BLOCKS__ before </body>
     *
     * Block wrappers are identified by the class "block-{type}" on the outermost element.
     */
    private function injectPreviewData(string $html, array $blocks): string
    {
        // Add data-block-index and data-block-type to each block's outermost element.
        // We match the first opening tag that has class="block-{type}" (or class containing it).
        foreach ($blocks as $index => $instance) {
            $type = (string) ($instance['type'] ?? '');
            if ($type === '') continue;

            $safeType  = preg_quote($type, '/');
            $safeIndex = (int) $index;

            // Match the first opening tag that has class containing "block-{type}" and inject data attrs.
            // Limit to 1 replacement so only the outermost/first wrapper for this block is annotated.
            $safeTypeHtml = htmlspecialchars($type, ENT_QUOTES);
            $html = preg_replace_callback(
                '/<([a-zA-Z][a-zA-Z0-9]*)\s([^>]*class="[^"]*\bblock-' . $safeType . '\b[^"]*"[^>]*)>/m',
                static function (array $m) use ($safeIndex, $safeTypeHtml): string {
                    $tag   = $m[1];
                    $attrs = $m[2];
                    return "<{$tag} {$attrs} data-block-index=\"{$safeIndex}\" data-block-type=\"{$safeTypeHtml}\">";
                },
                $html,
                1 // Limit to first match only — annotates the outermost wrapper element
            ) ?? $html;
        }

        // Inject preview script before </body>
        $blocksJson   = json_encode($blocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $previewScript = "\n<script>window.__SC_PREVIEW__ = true; window.__SC_BLOCKS__ = {$blocksJson};</script>";
        $html          = str_ireplace('</body>', $previewScript . "\n</body>", $html);

        return $html;
    }

    /**
     * Inject the PreviewBridge script inline before </body>.
     *
     * Reads preview-bridge.js from the same directory as this class file
     * and embeds it as an inline <script> tag so no external HTTP request
     * is needed and no path configuration is required.
     */
    private function injectPreviewBridge(string $html): string
    {
        $bridgePath = __DIR__ . '/preview-bridge.js';

        if (!file_exists($bridgePath)) {
            return $html;
        }

        $js     = (string) file_get_contents($bridgePath);
        $script = "\n<script>\n{$js}\n</script>";
        $html   = str_ireplace('</body>', $script . "\n</body>", $html);

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
     * @param  array $blocks       Decoded blocks array from page.blocks JSON
     * @param  bool  $previewMode  Unused here (preview data added in post-process pass)
     * @return string              Concatenated rendered HTML for all block instances
     */
    public function renderBlocks(array $blocks, bool $previewMode = false): string
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
