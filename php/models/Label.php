<?php
class Label {
    public static function all(): array { return Database::query("SELECT l.*,(SELECT COUNT(*) FROM collection_item_labels cil WHERE cil.label_id=l.id) as item_count FROM labels l ORDER BY l.name ASC"); }
    public static function findById(int $id): ?array { return Database::queryOne("SELECT * FROM labels WHERE id=?",[$id]); }
    public static function findBySlug(string $slug): ?array { return Database::queryOne("SELECT * FROM labels WHERE slug=?",[$slug]); }
    public static function create(array $d): int { Database::execute("INSERT INTO labels (name,slug,color,created_at) VALUES(?,?,?,?)",[$d['name'],$d['slug'],$d['color']??'#7c6af7',time()]); return Database::lastInsertId(); }
    public static function update(int $id, array $d): void { $sets=[]; $params=[]; foreach(['name','slug','color'] as $c){if(array_key_exists($c,$d)){$sets[]="{$c}=?";$params[]=$d[$c];}} $params[]=$id; Database::execute("UPDATE labels SET ".implode(',',$sets)." WHERE id=?",$params); }
    public static function delete(int $id): void { Database::execute("DELETE FROM labels WHERE id=?",[$id]); }
}
