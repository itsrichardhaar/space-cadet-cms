<?php
/**
 * Space Cadet CMS — SVG Sanitizer
 * DOM-based allowlist: strips <script>, on* event handlers, javascript: URLs.
 */

class SvgSanitizer {
    private const ALLOWED_ELEMENTS = [
        'svg','g','path','circle','rect','ellipse','line','polyline','polygon',
        'text','tspan','defs','use','symbol','linearGradient','radialGradient',
        'stop','clipPath','mask','image','a','title','desc','filter',
        'feGaussianBlur','feOffset','feComposite','feMerge','feMergeNode',
    ];

    public static function sanitize(string $svg): string {
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadXML($svg, LIBXML_NONET);
        libxml_clear_errors();

        $xpath = new DOMXPath($doc);

        // Remove <script> elements
        foreach ($xpath->query('//script') as $node) {
            $node->parentNode->removeChild($node);
        }

        // Remove disallowed elements
        $all = $xpath->query('//*');
        $toRemove = [];
        foreach ($all as $node) {
            if (!in_array(strtolower($node->localName), self::ALLOWED_ELEMENTS, true)) {
                $toRemove[] = $node;
            }
        }
        foreach ($toRemove as $node) {
            $node->parentNode->removeChild($node);
        }

        // Remove dangerous attributes
        foreach ($xpath->query('//@*') as $attr) {
            $name = strtolower($attr->nodeName);
            if (str_starts_with($name, 'on')) {
                $attr->ownerElement->removeAttributeNode($attr);
                continue;
            }
            if (in_array($name, ['href', 'xlink:href', 'src', 'action'], true)) {
                $val = strtolower(trim($attr->nodeValue));
                if (str_starts_with($val, 'javascript:') || str_starts_with($val, 'data:')) {
                    $attr->ownerElement->removeAttributeNode($attr);
                }
            }
        }

        return $doc->saveXML($doc->documentElement);
    }
}
