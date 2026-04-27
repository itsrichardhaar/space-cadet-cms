<?php
class FeedController {

    public function collection(Request $req, int $collectionId): void {
        $collection = Collection::findById($collectionId);
        if (!$collection || !(bool)($collection['feed_enabled'] ?? false)) {
            http_response_code(404);
            exit;
        }

        $siteUrl = Database::queryOne("SELECT value FROM settings WHERE key='site_url'")['value'] ?? '';
        $siteUrl = rtrim($siteUrl, '/');

        $items = Database::query(
            "SELECT ci.id, ci.title, ci.slug, ci.published_at
             FROM collection_items ci
             WHERE ci.collection_id = ? AND ci.status = 'published'
             ORDER BY ci.published_at DESC, ci.created_at DESC
             LIMIT 50",
            [$collectionId]
        );

        // Fetch the first text-type field value per item for description
        $descField = Database::queryOne(
            "SELECT key FROM collection_fields
             WHERE collection_id = ? AND type IN ('richtext','textarea','text')
             ORDER BY sort_order ASC LIMIT 1",
            [$collectionId]
        );
        $descKey = $descField['key'] ?? null;

        header('Content-Type: application/rss+xml; charset=utf-8');
        header('Cache-Control: public, max-age=3600');

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
        echo '<channel>' . "\n";
        echo '  <title>' . self::xe($collection['name']) . '</title>' . "\n";
        echo '  <link>' . self::xe($siteUrl) . '</link>' . "\n";
        echo '  <description>' . self::xe($collection['description'] ?? '') . '</description>' . "\n";
        echo '  <language>en</language>' . "\n";
        echo '  <atom:link href="' . self::xe($siteUrl . '/api.php?action=feeds/' . $collectionId) . '" rel="self" type="application/rss+xml" />' . "\n";

        foreach ($items as $item) {
            $link = $siteUrl . '/' . $collection['slug'] . '/' . $item['slug'];
            $pubDate = $item['published_at'] ? date('r', (int)$item['published_at']) : date('r');

            // Get description from first text field
            $desc = '';
            if ($descKey) {
                $fieldRow = Database::queryOne(
                    "SELECT value_text, value_json FROM collection_item_fields WHERE item_id = ? AND field_key = ?",
                    [(int)$item['id'], $descKey]
                );
                $raw = $fieldRow['value_text'] ?? $fieldRow['value_json'] ?? '';
                $desc = strip_tags((string)$raw);
                if (mb_strlen($desc) > 300) {
                    $desc = mb_substr($desc, 0, 297) . '…';
                }
            }

            echo "  <item>\n";
            echo '    <title>' . self::xe($item['title']) . "</title>\n";
            echo '    <link>' . self::xe($link) . "</link>\n";
            echo '    <guid isPermaLink="true">' . self::xe($link) . "</guid>\n";
            echo '    <pubDate>' . self::xe($pubDate) . "</pubDate>\n";
            if ($desc) echo '    <description>' . self::xe($desc) . "</description>\n";
            echo "  </item>\n";
        }

        echo '</channel>' . "\n";
        echo '</rss>' . "\n";
        exit;
    }

    private static function xe(string $s): string {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
