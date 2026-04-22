<?php
class Template {
    public static function all(): array { return Database::query("SELECT id,name,slug,type,compiled_hash,created_at,updated_at FROM templates ORDER BY name ASC"); }
    public static function findById(int $id): ?array { return Database::queryOne("SELECT * FROM templates WHERE id=?",[$id]); }
    public static function findBySlug(string $slug): ?array { return Database::queryOne("SELECT * FROM templates WHERE slug=?",[$slug]); }
    public static function create(array $d): int { $now=time(); Database::execute("INSERT INTO templates (name,slug,source,type,created_at,updated_at) VALUES(?,?,?,?,?,?)",[$d['name'],$d['slug'],$d['source']??'',$d['type']??'page',$now,$now]); return Database::lastInsertId(); }
    public static function update(int $id, array $d): void {
        $sets=[]; $params=[];
        foreach(['name','slug','type'] as $c){if(array_key_exists($c,$d)){$sets[]="{$c}=?";$params[]=$d[$c];}}
        if(array_key_exists('source',$d)){
            $sets[]='source=?'; $params[]=$d['source'];
            $newHash=hash('sha256',$d['source']);
            $sets[]='compiled_hash=?'; $params[]=$newHash;
            // Invalidate cache for old hash
            $old=self::findById($id);
            if($old&&$old['compiled_hash']){Cache::invalidate($old['compiled_hash']);}
        }
        $sets[]='updated_at=?'; $params[]=time(); $params[]=$id;
        Database::execute("UPDATE templates SET ".implode(',',$sets)." WHERE id=?",$params);
    }
    public static function delete(int $id): void {
        $t=self::findById($id); if($t&&$t['compiled_hash']){Cache::invalidate($t['compiled_hash']);}
        Database::execute("DELETE FROM templates WHERE id=?",[$id]);
    }
    public static function needsRecompile(array $template): bool {
        if(!$template['compiled_hash']) return true;
        $current=hash('sha256',$template['source']??'');
        if($current!==$template['compiled_hash']) return true;
        return Cache::get($template['compiled_hash'])===null;
    }
}
