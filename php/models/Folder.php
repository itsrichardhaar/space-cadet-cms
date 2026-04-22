<?php
class Folder {
    public static function all(): array { return Database::query("SELECT * FROM folders ORDER BY parent_id ASC,sort_order ASC,name ASC"); }
    public static function findById(int $id): ?array { return Database::queryOne("SELECT * FROM folders WHERE id=?",[$id]); }
    public static function tree(): array { $all=self::all(); return self::buildTree($all,null); }
    private static function buildTree(array $all, ?int $parentId): array { $out=[]; foreach($all as $f){ if($f['parent_id']==$parentId){$f['children']=self::buildTree($all,(int)$f['id']);$out[]=$f;} } return $out; }
    public static function create(array $d): int { Database::execute("INSERT INTO folders (name,parent_id,sort_order,created_at) VALUES(?,?,?,?)",[$d['name'],$d['parent_id']??null,$d['sort_order']??0,time()]); return Database::lastInsertId(); }
    public static function update(int $id, array $d): void { $sets=[]; $params=[]; foreach(['name','parent_id','sort_order'] as $c){if(array_key_exists($c,$d)){$sets[]="{$c}=?";$params[]=$d[$c];}} $params[]=$id; Database::execute("UPDATE folders SET ".implode(',',$sets)." WHERE id=?",$params); }
    public static function delete(int $id): void { Database::execute("UPDATE folders SET parent_id=NULL WHERE parent_id=?",[$id]); Database::execute("DELETE FROM folders WHERE id=?",[$id]); }
}
