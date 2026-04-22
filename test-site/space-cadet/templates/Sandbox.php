<?php
/**
 * Space Cadet CMS — Template Execution Sandbox
 * Runs compiled PHP in an isolated closure; captures output.
 */

class Sandbox {
    public static function run(string $compiledPath, array $context): string {
        $runner = static function (string $_sc_path, array $_ctx) {
            ob_start();
            try {
                include $_sc_path;
            } catch (Throwable $e) {
                ob_end_clean();
                return '<!-- Template Error: ' . htmlspecialchars($e->getMessage()) . ' -->';
            }
            return (string) ob_get_clean();
        };

        // Bind to anonymous class so template cannot access $this
        $bound = Closure::bind($runner, null, null);
        return $bound($compiledPath, $context);
    }
}
