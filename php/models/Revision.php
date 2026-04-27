<?php
class Revision {

    /**
     * Snapshot the current state of an entity after a successful save.
     */
    public static function snapshot(string $entityType, int $entityId, ?int $userId): void {
        $snapshot = match($entityType) {
            'page'            => Page::findById($entityId),
            'collection_item' => CollectionItem::findById($entityId),
            default           => null,
        };
        if (!$snapshot) return;

        Database::execute(
            "INSERT INTO revisions (entity_type, entity_id, user_id, snapshot_json, created_at)
             VALUES (?, ?, ?, ?, ?)",
            [$entityType, $entityId, $userId, json_encode($snapshot), time()]
        );

        // Keep only the last 50 revisions per entity
        Database::execute(
            "DELETE FROM revisions WHERE entity_type = ? AND entity_id = ?
             AND id NOT IN (
                 SELECT id FROM revisions
                 WHERE entity_type = ? AND entity_id = ?
                 ORDER BY created_at DESC LIMIT 50
             )",
            [$entityType, $entityId, $entityType, $entityId]
        );
    }

    public static function listForEntity(string $entityType, int $entityId, int $limit = 20): array {
        return Database::query(
            "SELECT r.id, r.entity_type, r.entity_id, r.user_id, r.action, r.created_at,
                    u.display_name as user_name
             FROM revisions r
             LEFT JOIN users u ON u.id = r.user_id
             WHERE r.entity_type = ? AND r.entity_id = ?
             ORDER BY r.created_at DESC
             LIMIT ?",
            [$entityType, $entityId, $limit]
        );
    }

    public static function findById(int $id): ?array {
        return Database::queryOne("SELECT * FROM revisions WHERE id = ?", [$id]) ?: null;
    }

    /**
     * Restore an entity to a previous snapshot.
     * Returns the restored entity data.
     */
    public static function restore(int $revisionId): array {
        $rev = self::findById($revisionId);
        if (!$rev) Response::notFound('Revision not found');

        $data = json_decode($rev['snapshot_json'], true);
        if (!$data) Response::error('Invalid revision snapshot', 422);

        match($rev['entity_type']) {
            'page'            => Page::update((int)$rev['entity_id'], $data),
            'collection_item' => CollectionItem::update((int)$rev['entity_id'], $data),
            default           => null,
        };

        return $data;
    }
}
