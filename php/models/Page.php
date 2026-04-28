<?php
class Page {
    public static function all(array $opts = []): array {
        $where = ['1=1']; $params = [];
        if (!empty($opts['status'])) { $where[] = 'status = ?'; $params[] = $opts['status']; }
        $sql = "SELECT p.*, u.display_name as author_name FROM pages p LEFT JOIN users u ON u.id = p.author_id WHERE " . implode(' AND ', $where) . " ORDER BY p.parent_id ASC, p.sort_order ASC";
        $rows = Database::query($sql, $params);
        foreach ($rows as &$row) { $row['fields'] = self::getFields($row['id']); }
        return $rows;
    }
    public static function findById(int $id): ?array {
        $row = Database::queryOne("SELECT * FROM pages WHERE id = ?", [$id]);
        if (!$row) return null;
        $row['fields']    = self::getFields($id);
        $row['fieldDefs'] = self::getFieldDefs($id);
        $row['blocks']    = isset($row['blocks']) && $row['blocks'] !== null
            ? json_decode($row['blocks'], true) ?? []
            : [];
        return $row;
    }
    public static function findBySlug(string $slug): ?array {
        $row = Database::queryOne("SELECT * FROM pages WHERE slug = ?", [$slug]);
        if (!$row) return null;
        $row['fields'] = self::getFields($row['id']);
        $row['blocks'] = isset($row['blocks']) && $row['blocks'] !== null
            ? json_decode($row['blocks'], true) ?? []
            : [];
        return $row;
    }
    public static function create(array $data): int {
        $now = time();
        $blocksJson = isset($data['blocks']) ? json_encode($data['blocks']) : null;
        Database::execute(
            "INSERT INTO pages (title,slug,parent_id,status,template_id,layout,blocks,author_id,sort_order,meta_title,meta_desc,published_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [$data['title'],$data['slug'],$data['parent_id']??null,$data['status']??'draft',$data['template_id']??null,$data['layout']??null,$blocksJson,$data['author_id']??null,$data['sort_order']??0,$data['meta_title']??null,$data['meta_desc']??null,$data['published_at']??null,$now,$now]
        );
        $id = Database::lastInsertId();
        if (!empty($data['fields'])) self::upsertFields($id, $data['fields']);
        return $id;
    }
    public static function update(int $id, array $data): void {
        $sets=[]; $params=[];
        foreach(['title','slug','parent_id','status','template_id','layout','sort_order','meta_title','meta_desc','published_at'] as $c) {
            if(array_key_exists($c,$data)){$sets[]="{$c}=?";$params[]=$data[$c];}
        }
        if(array_key_exists('blocks',$data)){
            $sets[]='blocks=?';
            $params[]=$data['blocks']!==null ? json_encode($data['blocks']) : null;
        }
        $sets[]='updated_at=?'; $params[]=time(); $params[]=$id;
        Database::execute("UPDATE pages SET ".implode(',',$sets)." WHERE id=?",$params);
        if(array_key_exists('fields',$data)) self::upsertFields($id,$data['fields']);
        if(array_key_exists('fieldDefs',$data)) self::replaceFieldDefs($id,$data['fieldDefs']);
    }
    public static function delete(int $id): void { Database::execute("DELETE FROM pages WHERE id=?",[$id]); }
    public static function reorder(array $items): void {
        $stmt = Database::get()->prepare("UPDATE pages SET parent_id=?,sort_order=?,updated_at=? WHERE id=?");
        foreach($items as $item) { $stmt->execute([$item['parent_id']??null,$item['sort_order']??0,time(),$item['id']]); }
    }
    private static function getFields(int $id): array {
        $rows=Database::query("SELECT * FROM page_fields WHERE page_id=?",[$id]);
        $out=[];
        foreach($rows as $r){ $out[$r['field_key']]=$r['value_json']!==null?json_decode($r['value_json'],true):($r['value_real']!==null?(float)$r['value_real']:($r['value_int']!==null?(int)$r['value_int']:$r['value_text'])); }
        return $out;
    }
    private static function getFieldDefs(int $id): array {
        return Database::query("SELECT * FROM page_field_defs WHERE page_id=? ORDER BY sort_order ASC",[$id]);
    }
    private static function upsertFields(int $id, array $fields): void {
        $stmt=Database::get()->prepare("INSERT INTO page_fields (page_id,field_key,value_text,value_int,value_real,value_json) VALUES(?,?,?,?,?,?) ON CONFLICT(page_id,field_key) DO UPDATE SET value_text=excluded.value_text,value_int=excluded.value_int,value_real=excluded.value_real,value_json=excluded.value_json");
        foreach($fields as $k=>$v){$vT=$vI=$vR=$vJ=null;if(is_array($v)||is_object($v)){$vJ=json_encode($v);}elseif(is_int($v)){$vI=$v;}elseif(is_float($v)){$vR=$v;}else{$vT=(string)$v;}$stmt->execute([$id,$k,$vT,$vI,$vR,$vJ]);}
    }
    private static function replaceFieldDefs(int $id, array $defs): void {
        Database::execute("DELETE FROM page_field_defs WHERE page_id=?",[$id]);
        $stmt=Database::get()->prepare("INSERT INTO page_field_defs (page_id,name,key,type,options,required,sort_order) VALUES(?,?,?,?,?,?,?)");
        foreach($defs as $i=>$d){$stmt->execute([$id,$d['name'],$d['key'],$d['type'],isset($d['options'])?json_encode($d['options']):'{}',((int)($d['required']??0)),$d['sort_order']??$i]);}
    }
}
