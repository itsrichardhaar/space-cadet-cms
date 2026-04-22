<?php
class GlobalGroup {
    public static function all(): array { return Database::query("SELECT * FROM global_groups ORDER BY name ASC"); }
    public static function findById(int $id): ?array {
        $row = Database::queryOne("SELECT * FROM global_groups WHERE id=?",[$id]);
        if(!$row) return null;
        $row['fields'] = Database::query("SELECT * FROM global_fields WHERE group_id=? ORDER BY sort_order ASC",[$id]);
        $row['values'] = self::getValues($id);
        return $row;
    }
    public static function findBySlug(string $slug): ?array {
        $row = Database::queryOne("SELECT * FROM global_groups WHERE slug=?",[$slug]);
        if(!$row) return null;
        $row['fields'] = Database::query("SELECT * FROM global_fields WHERE group_id=? ORDER BY sort_order ASC",[$row['id']]);
        $row['values'] = self::getValues($row['id']);
        return $row;
    }
    public static function create(array $data): int {
        $now=time(); Database::execute("INSERT INTO global_groups (name,slug,description,created_at,updated_at) VALUES(?,?,?,?,?)",[$data['name'],$data['slug'],$data['description']??null,$now,$now]); return Database::lastInsertId();
    }
    public static function update(int $id, array $data): void {
        $sets=[]; $params=[];
        foreach(['name','slug','description'] as $c){if(array_key_exists($c,$data)){$sets[]="{$c}=?";$params[]=$data[$c];}}
        $sets[]='updated_at=?'; $params[]=time(); $params[]=$id;
        Database::execute("UPDATE global_groups SET ".implode(',',$sets)." WHERE id=?",$params);
        if(array_key_exists('values',$data)) self::saveValues($id,$data['values']);
    }
    public static function delete(int $id): void { Database::execute("DELETE FROM global_groups WHERE id=?",[$id]); }
    public static function replaceFields(int $id, array $fields): void {
        Database::transaction(function() use($id,$fields){
            Database::execute("DELETE FROM global_fields WHERE group_id=?",[$id]);
            $stmt=Database::get()->prepare("INSERT INTO global_fields (group_id,name,key,type,options,sort_order) VALUES(?,?,?,?,?,?)");
            foreach($fields as $i=>$f){$stmt->execute([$id,$f['name'],$f['key'],$f['type'],isset($f['options'])?json_encode($f['options']):'{}',($f['sort_order']??$i)]);}
        });
    }
    private static function getValues(int $id): array {
        $rows=Database::query("SELECT * FROM global_values WHERE group_id=?",[$id]); $out=[];
        foreach($rows as $r){$out[$r['field_key']]=$r['value_json']!==null?json_decode($r['value_json'],true):($r['value_real']!==null?(float)$r['value_real']:($r['value_int']!==null?(int)$r['value_int']:$r['value_text']));}
        return $out;
    }
    private static function saveValues(int $id, array $values): void {
        $stmt=Database::get()->prepare("INSERT INTO global_values (group_id,field_key,value_text,value_int,value_real,value_json,updated_at) VALUES(?,?,?,?,?,?,?) ON CONFLICT(group_id,field_key) DO UPDATE SET value_text=excluded.value_text,value_int=excluded.value_int,value_real=excluded.value_real,value_json=excluded.value_json,updated_at=excluded.updated_at");
        foreach($values as $k=>$v){$vT=$vI=$vR=$vJ=null;if(is_array($v)){$vJ=json_encode($v);}elseif(is_int($v)){$vI=$v;}elseif(is_float($v)){$vR=$v;}else{$vT=(string)$v;}$stmt->execute([$id,$k,$vT,$vI,$vR,$vJ,time()]);}
    }
}
