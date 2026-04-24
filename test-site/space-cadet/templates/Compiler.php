<?php
/**
 * Space Cadet CMS — Template Compiler
 *
 * Transforms two syntaxes into executable PHP:
 * 1. Data-attribute: <h1 data-sc-field="title">
 * 2. Liquid-style:   {{ title }}, {% for item in items %}
 */

class Compiler {

    public static function compile(string $source): string {
        $out = $source;

        // ── Pass 1: data-sc-* attribute syntax ────────────────

        // data-sc-repeat="collection" → foreach loop
        $out = preg_replace_callback(
            '/<([a-z][a-z0-9-]*)\s[^>]*data-sc-repeat=["\']([^"\']+)["\'][^>]*>(.*?)<\/\1>/si',
            function ($m) {
                $tag   = $m[1];
                $var   = $m[2];
                $inner = $m[3];
                return "<?php foreach(\$_ctx['{$var}'] ?? [] as \$item): ?>\n{$inner}\n<?php endforeach; ?>";
            },
            $out
        );

        // data-sc-if="condition"
        $out = preg_replace_callback(
            '/data-sc-if=["\']([^"\']+)["\']/',
            function ($m) {
                $cond = htmlspecialchars_decode($m[1]);
                return "<?php if({$cond}): ?>";
            },
            $out
        );

        // data-sc-field="fieldName" → echo
        $out = preg_replace_callback(
            '/(<[^>]+)data-sc-field=["\']([^"\']+)["\']([^>]*>)(.*?)(<\/[a-z][a-z0-9-]*>)/si',
            function ($m) {
                $key = $m[2];
                return $m[1] . $m[3]
                     . "<?php echo htmlspecialchars(\$_ctx['{$key}'] ?? \$item['{$key}'] ?? '', ENT_QUOTES); ?>"
                     . $m[5];
            },
            $out
        );

        // data-sc-include="partial"
        $out = preg_replace_callback(
            '/data-sc-include=["\']([^"\']+)["\']/',
            function ($m) {
                $slug = $m[1];
                return "<?php \$_sc_partial=Template::findBySlug('{$slug}'); if(\$_sc_partial){ \$_sc_engine=new Engine(); echo \$_sc_engine->render('{$slug}',\$_ctx); } ?>";
            },
            $out
        );

        // ── Pass 2: Liquid-style syntax ───────────────────────

        // {% for item in collection %}...{% endfor %}
        $out = preg_replace_callback(
            '/\{%[-\s]*for\s+(\w+)\s+in\s+(\w+)\s*[-\s]*%\}(.*?)\{%[-\s]*endfor\s*[-\s]*%\}/si',
            function ($m) {
                return "<?php foreach(\$_ctx['{$m[2]}'] ?? [] as \${$m[1]}): ?>{$m[3]}<?php endforeach; ?>";
            },
            $out
        );

        // {% if condition %}...{% endif %}
        $out = preg_replace_callback(
            '/\{%[-\s]*if\s+(.+?)\s*[-\s]*%\}(.*?)\{%[-\s]*endif\s*[-\s]*%\}/si',
            function ($m) {
                $cond = htmlspecialchars_decode(trim($m[1]));
                return "<?php if({$cond}): ?>{$m[2]}<?php endif; ?>";
            },
            $out
        );

        // {% include "partial" %}
        $out = preg_replace_callback(
            '/\{%[-\s]*include\s+["\']([^"\']+)["\']\s*[-\s]*%\}/',
            function ($m) {
                $slug = $m[1];
                return "<?php echo (new Engine())->render('{$slug}', \$_ctx); ?>";
            },
            $out
        );

        // {{{ raw variable }}} — no escape (must run BEFORE {{ }} to avoid partial match)
        $out = preg_replace_callback(
            '/\{\{\{\s*([^\}]+?)\s*\}\}\}/',
            function ($m) {
                $var = trim($m[1]);
                return "<?php echo \$_ctx['{$var}'] ?? ''; ?>";
            },
            $out
        );

        // {{ variable }} — auto-escape
        $out = preg_replace_callback(
            '/\{\{\s*([^\}]+?)\s*\}\}/',
            function ($m) {
                $expr = trim($m[1]);
                // Simple variable: word.word or word
                if (preg_match('/^[\w.]+$/', $expr)) {
                    $parts = explode('.', $expr);
                    if (count($parts) === 1) {
                        return "<?php echo htmlspecialchars(\$_ctx['{$parts[0]}'] ?? \$item['{$parts[0]}'] ?? '', ENT_QUOTES); ?>";
                    }
                    $chain = "\$_ctx['{$parts[0]}']";
                    for ($i = 1; $i < count($parts); $i++) {
                        $chain .= "['{$parts[$i]}']";
                    }
                    return "<?php echo htmlspecialchars({$chain} ?? '', ENT_QUOTES); ?>";
                }
                // Arbitrary expression — still escape output
                return "<?php echo htmlspecialchars((string)({$expr}), ENT_QUOTES); ?>";
            },
            $out
        );

        // Wrap in a function so $item is available in loops
        $wrapped = "<?php\n// Space Cadet CMS — compiled template\n// DO NOT EDIT — auto-generated\n?>\n" . $out;

        return $wrapped;
    }
}
