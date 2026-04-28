<?php
/**
 * Space Cadet CMS — Block Registry
 *
 * Scans a theme's blocks/ directory for custom block HTML files (YAML frontmatter
 * + Liquid template) and provides lookup by block type (filename without .html).
 *
 * Block file format (ADR 0002):
 *   ---
 *   name: Hero
 *   icon: layout
 *   fields:
 *     - { name: headline, type: text, label: Headline }
 *     - { name: image, type: media, label: Image, deprecated: true }
 *   ---
 *   <section class="block-hero">...</section>
 *
 * Deprecated fields (deprecated: true) are excluded from the returned schema
 * but their stored data is preserved for rendering.
 */

declare(strict_types=1);

class BlockRegistry
{
    /**
     * Scan a blocks directory and return all valid block definitions.
     *
     * Each definition array contains:
     *   - type     string   filename without .html (e.g. "hero")
     *   - name     string   human-readable name from frontmatter
     *   - icon     string   icon identifier from frontmatter
     *   - fields   array    non-deprecated field definitions
     *   - template string   the raw Liquid template portion
     *
     * Files with malformed frontmatter are silently skipped.
     *
     * @param  string $blocksDir  Absolute path to the blocks/ directory
     * @return array<int, array>
     */
    public static function scan(string $blocksDir): array
    {
        if (!is_dir($blocksDir)) {
            return [];
        }

        $blocks = [];

        foreach (glob($blocksDir . '/*.html') ?: [] as $path) {
            $type    = basename($path, '.html');
            $content = (string) file_get_contents($path);
            $def     = self::parse($content);

            if ($def === null) {
                continue;
            }

            $def['type'] = $type;
            $blocks[]    = $def;
        }

        // Sort by type name for deterministic ordering
        usort($blocks, fn($a, $b) => strcmp($a['type'], $b['type']));

        return $blocks;
    }

    /**
     * Parse a single block file's content (frontmatter + template).
     *
     * Returns null if the file does not have valid --- frontmatter delimiters.
     * Deprecated fields (deprecated: true) are excluded from the fields array.
     *
     * @param  string $fileContent  Raw content of a block .html file
     * @return array|null
     */
    public static function parse(string $fileContent): ?array
    {
        // Require opening --- delimiter at the very start of the file
        if (!str_starts_with(ltrim($fileContent, "\r\n"), '---')) {
            return null;
        }

        // Split on the closing --- delimiter
        // Pattern: optional leading whitespace, then ---, then rest is template
        if (!preg_match('/^---\r?\n(.*?)\r?\n---\r?\n?(.*)/s', ltrim($fileContent, "\r\n"), $m)) {
            return null;
        }

        $yamlBody = $m[1];
        $template = $m[2];

        $frontmatter = self::parseYaml($yamlBody);

        if ($frontmatter === null) {
            return null;
        }

        // Filter out deprecated fields
        $fields = [];
        foreach ($frontmatter['fields'] ?? [] as $field) {
            if (isset($field['deprecated']) && $field['deprecated'] === true) {
                continue;
            }
            $fields[] = $field;
        }

        return [
            'name'     => (string) ($frontmatter['name'] ?? ''),
            'icon'     => (string) ($frontmatter['icon'] ?? ''),
            'fields'   => $fields,
            'template' => $template,
        ];
    }

    /**
     * Get a single block definition by type (filename without .html).
     *
     * Returns null if the type is not found in the given blocks directory.
     *
     * @param  string $type       Block type (e.g. "hero")
     * @param  string $blocksDir  Absolute path to the blocks/ directory
     * @return array|null
     */
    public static function get(string $type, string $blocksDir): ?array
    {
        // Sanitize: type must be a safe filename segment
        if (!preg_match('/^[a-z0-9_-]+$/i', $type)) {
            return null;
        }

        $path = $blocksDir . '/' . $type . '.html';

        if (!file_exists($path)) {
            return null;
        }

        $content = (string) file_get_contents($path);
        $def     = self::parse($content);

        if ($def === null) {
            return null;
        }

        $def['type'] = $type;
        return $def;
    }

    // ── Minimal YAML parser ────────────────────────────────────────────────────

    /**
     * Parse the minimal YAML subset used in block frontmatter:
     *   - Top-level scalar key: value pairs
     *   - Sequences (block list) under a key, where each item is:
     *       - An inline mapping: { key: val, key2: val2 }
     *
     * Returns null on any parse error.
     *
     * @param  string $yaml
     * @return array|null
     */
    private static function parseYaml(string $yaml): ?array
    {
        $result = [];
        $lines  = explode("\n", str_replace("\r\n", "\n", $yaml));
        $i      = 0;
        $total  = count($lines);

        while ($i < $total) {
            $line = $lines[$i];

            // Skip blank lines and comment lines
            if (trim($line) === '' || str_starts_with(ltrim($line), '#')) {
                $i++;
                continue;
            }

            // Top-level key: value  (no leading indent)
            if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*):\s*(.*)$/', $line, $m)) {
                $key   = $m[1];
                $value = rtrim($m[2]);

                if ($value === '' || $value === null) {
                    // Value is on following lines — collect sequence items
                    $items = [];
                    $i++;
                    while ($i < $total) {
                        $seqLine = $lines[$i];
                        $trimmed = ltrim($seqLine);

                        // End of sequence when we hit an unindented non-empty non-comment line
                        // that looks like a new key
                        if ($trimmed !== ''
                            && !str_starts_with($trimmed, '#')
                            && !str_starts_with($trimmed, '-')
                            && preg_match('/^[a-zA-Z_]/', $seqLine)
                        ) {
                            break;
                        }

                        if (str_starts_with($trimmed, '- ')) {
                            $itemStr = substr($trimmed, 2);
                            $parsed  = self::parseInlineMapping($itemStr);
                            if ($parsed !== null) {
                                $items[] = $parsed;
                            }
                        }

                        $i++;
                    }
                    $result[$key] = $items;
                    continue;
                } else {
                    // Inline scalar value
                    $result[$key] = self::parseScalar($value);
                    $i++;
                    continue;
                }
            }

            $i++;
        }

        return $result;
    }

    /**
     * Parse an inline YAML mapping: { key: val, key2: val2, ... }
     * or a plain string if no braces are found.
     *
     * @return array|string|null
     */
    private static function parseInlineMapping(string $str): array|string|null
    {
        $str = trim($str);

        if (str_starts_with($str, '{') && str_ends_with($str, '}')) {
            $inner  = substr($str, 1, -1);
            $result = [];

            // Split on commas not inside quotes or nested braces
            $pairs = self::splitInlineMapping($inner);

            foreach ($pairs as $pair) {
                $pair = trim($pair);
                if ($pair === '') continue;

                $colonPos = strpos($pair, ':');
                if ($colonPos === false) {
                    continue;
                }

                $k = trim(substr($pair, 0, $colonPos));
                $v = trim(substr($pair, $colonPos + 1));
                $result[$k] = self::parseScalar($v);
            }

            return $result;
        }

        // Plain string item
        return $str;
    }

    /**
     * Split an inline mapping's interior string on commas, respecting
     * quoted strings so commas inside quotes are not treated as separators.
     *
     * @return string[]
     */
    private static function splitInlineMapping(string $inner): array
    {
        $parts  = [];
        $buf    = '';
        $inQuot = false;
        $quotCh = '';
        $depth  = 0;

        for ($i = 0, $len = strlen($inner); $i < $len; $i++) {
            $ch = $inner[$i];

            if ($inQuot) {
                $buf .= $ch;
                if ($ch === $quotCh) {
                    $inQuot = false;
                }
            } elseif ($ch === '"' || $ch === "'") {
                $inQuot = true;
                $quotCh = $ch;
                $buf   .= $ch;
            } elseif ($ch === '{' || $ch === '[') {
                $depth++;
                $buf .= $ch;
            } elseif ($ch === '}' || $ch === ']') {
                $depth--;
                $buf .= $ch;
            } elseif ($ch === ',' && $depth === 0) {
                $parts[] = $buf;
                $buf     = '';
            } else {
                $buf .= $ch;
            }
        }

        if ($buf !== '') {
            $parts[] = $buf;
        }

        return $parts;
    }

    /**
     * Parse a scalar value from a YAML string:
     *   - true / false → bool
     *   - null / ~ → null
     *   - integers / floats → cast to number
     *   - quoted strings → strip quotes
     *   - everything else → string as-is
     */
    private static function parseScalar(string $value): mixed
    {
        $value = trim($value);

        if (strtolower($value) === 'true')  return true;
        if (strtolower($value) === 'false') return false;
        if (strtolower($value) === 'null' || $value === '~') return null;

        // Quoted string
        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            return substr($value, 1, -1);
        }

        // Integer
        if (preg_match('/^-?\d+$/', $value)) {
            return (int) $value;
        }

        // Float
        if (preg_match('/^-?\d+\.\d+$/', $value)) {
            return (float) $value;
        }

        return $value;
    }
}
