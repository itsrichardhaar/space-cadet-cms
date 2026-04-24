<?php
class SiteAsset {

    public static function migrate(): void {
        Database::execute("CREATE TABLE IF NOT EXISTS site_assets (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            name        TEXT    NOT NULL,
            slug        TEXT    UNIQUE NOT NULL,
            type        TEXT    NOT NULL CHECK(type IN ('css','js')),
            content     TEXT    NOT NULL DEFAULT '',
            created_at  INTEGER NOT NULL,
            updated_at  INTEGER NOT NULL
        )");
    }

    public static function all(): array {
        self::migrate();
        return Database::query(
            "SELECT id, name, slug, type, created_at, updated_at FROM site_assets ORDER BY type ASC, name ASC"
        );
    }

    public static function findById(int $id): ?array {
        self::migrate();
        return Database::queryOne("SELECT * FROM site_assets WHERE id = ?", [$id]);
    }

    public static function findBySlug(string $slug): ?array {
        self::migrate();
        return Database::queryOne("SELECT * FROM site_assets WHERE slug = ?", [$slug]);
    }

    public static function create(array $data): int {
        self::migrate();
        $now = time();
        Database::execute(
            "INSERT INTO site_assets (name, slug, type, content, created_at, updated_at) VALUES (?,?,?,?,?,?)",
            [$data['name'], $data['slug'], $data['type'], $data['content'] ?? '', $now, $now]
        );
        $id = Database::lastInsertId();
        self::writeFile($data['slug'], $data['type'], $data['content'] ?? '');
        return $id;
    }

    public static function update(int $id, array $data): void {
        $sets = []; $params = [];
        foreach (['name', 'slug', 'content'] as $c) {
            if (array_key_exists($c, $data)) { $sets[] = "{$c}=?"; $params[] = $data[$c]; }
        }
        if (empty($sets)) { $sets[] = 'updated_at=?'; $params[] = time(); $params[] = $id; Database::execute("UPDATE site_assets SET " . implode(',', $sets) . " WHERE id=?", $params); return; }
        $sets[] = 'updated_at=?'; $params[] = time(); $params[] = $id;
        Database::execute("UPDATE site_assets SET " . implode(',', $sets) . " WHERE id=?", $params);
        $row = self::findById($id);
        if ($row) self::writeFile($row['slug'], $row['type'], $row['content']);
    }

    public static function delete(int $id): void {
        $row = self::findById($id);
        if ($row) self::deleteFile($row['slug'], $row['type']);
        Database::execute("DELETE FROM site_assets WHERE id=?", [$id]);
    }

    // ── File helpers ──────────────────────────────────────────────────────────

    private static function assetsDir(): string {
        $dir = SC_STORAGE . '/assets';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        return $dir;
    }

    private static function writeFile(string $slug, string $type, string $content): void {
        file_put_contents(self::assetsDir() . "/{$slug}.{$type}", $content);
    }

    private static function deleteFile(string $slug, string $type): void {
        $file = self::assetsDir() . "/{$slug}.{$type}";
        if (file_exists($file)) unlink($file);
    }
}
