<?php
/**
 * Space Cadet CMS — Template Compiled-File Cache
 *
 * Stores compiled PHP template files keyed by a content hash.
 * Used exclusively by the template engine — not a general cache.
 */

class Cache {

    /**
     * Check whether a compiled file exists for the given hash.
     * Returns the full path if valid, null if cache miss.
     */
    public static function get(string $hash): ?string {
        $path = self::path($hash);
        return file_exists($path) ? $path : null;
    }

    /**
     * Write compiled PHP source to the cache and return the file path.
     */
    public static function put(string $hash, string $compiledPhp): string {
        $path = self::path($hash);
        if (!is_dir(SC_CACHE)) {
            mkdir(SC_CACHE, 0755, true);
        }
        file_put_contents($path, $compiledPhp, LOCK_EX);
        return $path;
    }

    /**
     * Delete a single cache file.
     */
    public static function invalidate(string $hash): void {
        $path = self::path($hash);
        if (file_exists($path)) {
            unlink($path);
        }
    }

    /**
     * Remove all compiled template files from the cache directory.
     */
    public static function flush(): int {
        $count = 0;
        if (!is_dir(SC_CACHE)) return 0;
        foreach (glob(SC_CACHE . '/*.php') as $file) {
            unlink($file);
            $count++;
        }
        return $count;
    }

    /**
     * Return the full filesystem path for a given hash.
     */
    private static function path(string $hash): string {
        return SC_CACHE . '/' . preg_replace('/[^a-f0-9]/', '', $hash) . '.php';
    }
}
