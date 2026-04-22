<?php
/**
 * Space Cadet CMS — FTS5 Full-Text Search
 */

class FullTextSearch {

    public static function indexItem(int $itemId): void {
        $item = CollectionItem::findById($itemId);
        if (!$item) return;
        $body = implode(' ', array_map('strval', $item['fields'] ?? []));
        SearchIndex::index('collection_item', $itemId, $item['title'], $body);
    }

    public static function indexPage(int $pageId): void {
        $page = Page::findById($pageId);
        if (!$page) return;
        $body = implode(' ', array_map('strval', $page['fields'] ?? []));
        SearchIndex::index('page', $pageId, $page['title'], $body);
    }

    public static function reindex(): void {
        // Reindex all collection items
        $items = Database::query("SELECT id FROM collection_items WHERE status='published'");
        foreach ($items as $item) {
            self::indexItem($item['id']);
        }
        // Reindex all pages
        $pages = Database::query("SELECT id FROM pages WHERE status='published'");
        foreach ($pages as $page) {
            self::indexPage($page['id']);
        }
    }
}
