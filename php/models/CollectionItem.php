<?php
/**
 * Space Cadet CMS — Collection Item Model
 */

class CollectionItem {

    public static function findById(int $id): ?array {
        $item = Database::queryOne("SELECT * FROM collection_items WHERE id = ?", [$id]);
        if (!$item) return null;
        $item['fields'] = self::getFields($id);
        $item['labels'] = self::getLabels($id);
        return $item;
    }

    public static function findBySlug(int $collectionId, string $slug): ?array {
        $item = Database::queryOne(
            "SELECT * FROM collection_items WHERE collection_id = ? AND slug = ?",
            [$collectionId, $slug]
        );
        if (!$item) return null;
        $item['fields'] = self::getFields($item['id']);
        $item['labels'] = self::getLabels($item['id']);
        return $item;
    }

    public static function list(int $collectionId, array $opts = []): array {
        $where  = ['ci.collection_id = ?'];
        $params = [$collectionId];

        if (!empty($opts['status'])) {
            $where[]  = 'ci.status = ?';
            $params[] = $opts['status'];
        }
        if (!empty($opts['folder_id'])) {
            $where[]  = 'ci.folder_id = ?';
            $params[] = (int) $opts['folder_id'];
        }
        if (!empty($opts['label'])) {
            $where[]  = "EXISTS (SELECT 1 FROM collection_item_labels cil JOIN labels l ON l.id = cil.label_id WHERE cil.item_id = ci.id AND l.slug = ?)";
            $params[] = $opts['label'];
        }
        if (!empty($opts['q'])) {
            $where[]  = 'ci.title LIKE ?';
            $params[] = '%' . $opts['q'] . '%';
        }

        $sort      = in_array($opts['sort'] ?? '', ['title','created_at','updated_at','published_at','sort_order'], true)
                     ? ($opts['sort'] ?? 'created_at') : 'created_at';
        $direction = ($opts['direction'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

        $sql = "SELECT ci.*, u.display_name as author_name
                FROM collection_items ci
                LEFT JOIN users u ON u.id = ci.author_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY ci.{$sort} {$direction}";

        $perPage = min((int)($opts['per_page'] ?? 20), 100);
        $page    = max(1, (int)($opts['page'] ?? 1));

        $result = Database::paginate($sql, $params, $page, $perPage);
        foreach ($result['rows'] as &$row) {
            $row['labels'] = self::getLabels($row['id']);
        }
        return $result;
    }

    public static function create(int $collectionId, array $data, int $authorId): int {
        $now = time();
        Database::execute(
            "INSERT INTO collection_items (collection_id, title, slug, status, author_id, folder_id, published_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $collectionId,
                $data['title'],
                $data['slug'],
                $data['status']      ?? 'draft',
                $authorId,
                $data['folder_id']   ?? null,
                $data['published_at'] ?? null,
                $now, $now,
            ]
        );
        $itemId = Database::lastInsertId();

        if (!empty($data['fields'])) {
            self::upsertFields($itemId, $data['fields']);
        }
        if (!empty($data['labels'])) {
            self::syncLabels($itemId, $data['labels']);
        }

        return $itemId;
    }

    public static function update(int $id, array $data): void {
        $sets   = [];
        $params = [];

        foreach (['title','slug','status','folder_id','published_at'] as $col) {
            if (array_key_exists($col, $data)) { $sets[] = "{$col} = ?"; $params[] = $data[$col]; }
        }
        if (empty($sets)) { $sets[] = 'updated_at = ?'; $params[] = time(); }
        else { $sets[] = 'updated_at = ?'; $params[] = time(); }

        $params[] = $id;
        Database::execute("UPDATE collection_items SET " . implode(', ', $sets) . " WHERE id = ?", $params);

        if (array_key_exists('fields', $data)) {
            self::upsertFields($id, $data['fields']);
        }
        if (array_key_exists('labels', $data)) {
            self::syncLabels($id, $data['labels']);
        }
    }

    public static function delete(int $id): void {
        Database::execute("DELETE FROM collection_items WHERE id = ?", [$id]);
    }

    public static function bulkUpdate(int $collectionId, array $ids, string $status): int {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        return Database::execute(
            "UPDATE collection_items SET status = ?, updated_at = ?
             WHERE collection_id = ? AND id IN ({$placeholders})",
            array_merge([$status, time(), $collectionId], $ids)
        );
    }

    private static function getFields(int $itemId): array {
        $rows   = Database::query("SELECT * FROM collection_item_fields WHERE item_id = ?", [$itemId]);
        $fields = [];
        foreach ($rows as $row) {
            if ($row['value_json'] !== null) {
                $fields[$row['field_key']] = json_decode($row['value_json'], true);
            } elseif ($row['value_real'] !== null) {
                $fields[$row['field_key']] = (float) $row['value_real'];
            } elseif ($row['value_int'] !== null) {
                $fields[$row['field_key']] = (int) $row['value_int'];
            } else {
                $fields[$row['field_key']] = $row['value_text'];
            }
        }
        return $fields;
    }

    private static function upsertFields(int $itemId, array $fields): void {
        $stmt = Database::get()->prepare(
            "INSERT INTO collection_item_fields (item_id, field_key, value_text, value_int, value_real, value_json)
             VALUES (?, ?, ?, ?, ?, ?)
             ON CONFLICT(item_id, field_key) DO UPDATE SET
               value_text = excluded.value_text,
               value_int  = excluded.value_int,
               value_real = excluded.value_real,
               value_json = excluded.value_json"
        );
        foreach ($fields as $key => $value) {
            $vText = $vInt = $vReal = $vJson = null;
            if (is_array($value) || is_object($value)) {
                $vJson = json_encode($value);
            } elseif (is_int($value)) {
                $vInt = $value;
            } elseif (is_float($value)) {
                $vReal = $value;
            } else {
                $vText = (string) $value;
            }
            $stmt->execute([$itemId, $key, $vText, $vInt, $vReal, $vJson]);
        }
    }

    private static function getLabels(int $itemId): array {
        return Database::query(
            "SELECT l.id, l.name, l.slug, l.color FROM labels l
             JOIN collection_item_labels cil ON cil.label_id = l.id
             WHERE cil.item_id = ?",
            [$itemId]
        );
    }

    private static function syncLabels(int $itemId, array $labelIds): void {
        Database::execute("DELETE FROM collection_item_labels WHERE item_id = ?", [$itemId]);
        if (empty($labelIds)) return;
        $stmt = Database::get()->prepare(
            "INSERT OR IGNORE INTO collection_item_labels (item_id, label_id) VALUES (?, ?)"
        );
        foreach ($labelIds as $labelId) {
            $stmt->execute([$itemId, (int) $labelId]);
        }
    }

    /**
     * Generate a unique slug within a collection.
     */
    public static function uniqueSlug(int $collectionId, string $title, ?int $excludeId = null): string {
        $base = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $title));
        $base = trim($base, '-') ?: 'item';
        $slug = $base;
        $i    = 1;

        while (true) {
            $sql    = "SELECT id FROM collection_items WHERE collection_id = ? AND slug = ?";
            $params = [$collectionId, $slug];
            if ($excludeId) { $sql .= " AND id != ?"; $params[] = $excludeId; }
            if (!Database::queryOne($sql, $params)) break;
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
