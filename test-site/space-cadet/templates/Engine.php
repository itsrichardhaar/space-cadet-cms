<?php
/**
 * Space Cadet CMS — Template Engine
 */

class Engine {
    public function render(string $slug, array $context = []): string {
        $template = Template::findBySlug($slug);
        if (!$template) {
            return "<!-- Template not found: {$slug} -->";
        }

        // Recompile if source changed or cache missing
        if (Template::needsRecompile($template)) {
            $compiled = Compiler::compile($template['source']);
            $hash     = hash('sha256', $template['source']);
            $path     = Cache::put($hash, $compiled);

            // Update compiled_hash in DB
            Database::execute(
                "UPDATE templates SET compiled_hash = ?, compiled_path = ?, updated_at = ? WHERE id = ?",
                [$hash, $path, time(), $template['id']]
            );
        } else {
            $path = Cache::get($template['compiled_hash']);
        }

        if (!$path || !file_exists($path)) {
            return "<!-- Template compile error for: {$slug} -->";
        }

        return Sandbox::run($path, $context);
    }
}
