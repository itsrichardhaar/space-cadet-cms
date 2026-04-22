<?php
/**
 * Space Cadet CMS — Collection Model
 */

class Collection {

    public static function all(): array {
        return Database::query("SELECT * FROM collections ORDER BY name ASC");
    }

    public static function findById(int $id): ?array {
        return Database::queryOne("SELECT * FROM collections WHERE id = ?", [$id]);
    }

    public static function findBySlug(string $slug): ?array {
        return Database::queryOne("SELECT * FROM collections WHERE slug = ?", [$slug]);
    }

    public static function create(array $data): int {
        $now = time();
        Database::execute(
            "INSERT INTO collections (name, slug, description, icon, supports_status, supports_author,
             supports_dates, sort_field, sort_direction, is_singleton, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $data['name'],
                $data['slug'],
                $data['description']     ?? null,
                $data['icon']            ?? 'folder',
                (int)($data['supports_status'] ?? 1),
                (int)($data['supports_author'] ?? 1),
                (int)($data['supports_dates']  ?? 1),
                $data['sort_field']      ?? 'created_at',
                $data['sort_direction']  ?? 'desc',
                (int)($data['is_singleton'] ?? 0),
                $now, $now,
            ]
        );
        return Database::lastInsertId();
    }

    public static function update(int $id, array $data): void {
        $sets   = [];
        $params = [];
        foreach (['name','slug','description','icon','sort_field','sort_direction'] as $col) {
            if (array_key_exists($col, $data)) { $sets[] = "{$col} = ?"; $params[] = $data[$col]; }
        }
        foreach (['supports_status','supports_author','supports_dates','is_singleton'] as $col) {
            if (array_key_exists($col, $data)) { $sets[] = "{$col} = ?"; $params[] = (int)$data[$col]; }
        }
        if (empty($sets)) return;
        $sets[] = 'updated_at = ?'; $params[] = time(); $params[] = $id;
        Database::execute("UPDATE collections SET " . implode(', ', $sets) . " WHERE id = ?", $params);
    }

    public static function delete(int $id): void {
        Database::execute("DELETE FROM collections WHERE id = ?", [$id]);
    }

    public static function fields(int $collectionId): array {
        $rows = Database::query(
            "SELECT * FROM collection_fields WHERE collection_id = ? ORDER BY sort_order ASC",
            [$collectionId]
        );
        foreach ($rows as &$row) {
            $row['options'] = json_decode($row['options'] ?? '{}', true) ?? [];
        }
        return $rows;
    }

    public static function replaceFields(int $collectionId, array $fields): void {
        Database::transaction(function () use ($collectionId, $fields) {
            Database::execute("DELETE FROM collection_fields WHERE collection_id = ?", [$collectionId]);
            $stmt = Database::get()->prepare(
                "INSERT INTO collection_fields (collection_id, name, key, type, options, required, sort_order, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            foreach ($fields as $i => $f) {
                $stmt->execute([
                    $collectionId,
                    $f['name'],
                    $f['key'],
                    $f['type'],
                    isset($f['options']) ? json_encode($f['options']) : '{}',
                    (int)($f['required'] ?? 0),
                    $f['sort_order'] ?? $i,
                    time(),
                ]);
            }
            Database::execute("UPDATE collections SET updated_at = ? WHERE id = ?", [time(), $collectionId]);
        });
    }

    public static function withItemCount(): array {
        return Database::query(
            "SELECT c.*, COUNT(ci.id) as item_count
             FROM collections c
             LEFT JOIN collection_items ci ON ci.collection_id = c.id
             GROUP BY c.id ORDER BY c.name ASC"
        );
    }
}
