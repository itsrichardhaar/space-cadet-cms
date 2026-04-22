<?php
class Menu {
    public static function all(): array { return Database::query("SELECT * FROM menus ORDER BY name ASC"); }
    public static function findById(int $id): ?array {
        $menu = Database::queryOne("SELECT * FROM menus WHERE id=?",[$id]);
        if(!$menu) return null;
        $menu['items'] = self::buildTree($id);
        return $menu;
    }
    public static function findBySlug(string $slug): ?array {
        $menu = Database::queryOne("SELECT * FROM menus WHERE slug=?",[$slug]);
        if(!$menu) return null;
        $menu['items'] = self::buildTree($menu['id']);
        return $menu;
    }
    public static function create(array $data): int {
        $now=time(); Database::execute("INSERT INTO menus (name,slug,created_at,updated_at) VALUES(?,?,?,?)",[$data['name'],$data['slug'],$now,$now]); return Database::lastInsertId();
    }
    public static function update(int $id, array $data): void {
        $sets=[]; $params=[];
        foreach(['name','slug'] as $c){if(array_key_exists($c,$data)){$sets[]="{$c}=?";$params[]=$data[$c];}}
        $sets[]='updated_at=?'; $params[]=time(); $params[]=$id;
        Database::execute("UPDATE menus SET ".implode(',',$sets)." WHERE id=?",$params);
    }
    public static function delete(int $id): void { Database::execute("DELETE FROM menus WHERE id=?",[$id]); }
    public static function replaceItems(int $menuId, array $items): void {
        Database::transaction(function() use($menuId,$items){
            Database::execute("DELETE FROM menu_items WHERE menu_id=?",[$menuId]);
            self::insertItems($menuId, $items, null, 0);
            Database::execute("UPDATE menus SET updated_at=? WHERE id=?", [time(),$menuId]);
        });
    }
    private static function insertItems(int $menuId, array $items, ?int $parentId, int $order): void {
        $stmt=Database::get()->prepare("INSERT INTO menu_items (menu_id,parent_id,label,url,target,rel,icon,link_type,linked_id,sort_order,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?)");
        foreach($items as $i=>$item){
            $stmt->execute([$menuId,$parentId,$item['label'],$item['url']??null,$item['target']??'_self',$item['rel']??null,$item['icon']??null,$item['link_type']??'custom',$item['linked_id']??null,($i+$order),time()]);
            $insertedId=(int)Database::get()->lastInsertId();
            if(!empty($item['children'])) self::insertItems($menuId,$item['children'],$insertedId,0);
        }
    }
    private static function buildTree(int $menuId, ?int $parentId=null): array {
        $items=Database::query("SELECT * FROM menu_items WHERE menu_id=? AND parent_id".($parentId===null?" IS NULL":"=?")." ORDER BY sort_order ASC",$parentId===null?[$menuId]:[$menuId,$parentId]);
        foreach($items as &$item){$item['children']=self::buildTree($menuId,(int)$item['id']);}
        return $items;
    }
}
