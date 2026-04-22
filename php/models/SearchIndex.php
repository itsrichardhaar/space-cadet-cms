<?php
class SearchIndex {
    public static function index(string $type, int $entityId, string $title, string $body, string $meta=''): void {
        self::remove($type,$entityId);
        Database::execute("INSERT INTO search_index (entity_type,entity_id,title,body,meta) VALUES(?,?,?,?,?)",[$type,$entityId,$title,$body,$meta]);
    }
    public static function remove(string $type, int $entityId): void {
        Database::execute("DELETE FROM search_index WHERE entity_type=? AND entity_id=?",[$type,$entityId]);
    }
    public static function search(string $q, array $types=[], int $page=1, int $perPage=20): array {
        $safe=self::escapeFts($q);
        $where="search_index MATCH ?"; $params=["{$safe}"];
        if(!empty($types)){ $ph=implode(',',array_fill(0,count($types),'?')); $where.=" AND entity_type IN ({$ph})"; $params=array_merge($params,$types); }
        $sql="SELECT rowid,entity_type,entity_id,title,snippet(search_index,3,'<mark>','</mark>','...',20) as excerpt,rank FROM search_index WHERE {$where} ORDER BY rank";
        return Database::paginate($sql,$params,$page,$perPage);
    }
    private static function escapeFts(string $q): string {
        $q=preg_replace('/[^\w\s]/u','',$q);
        return '"'.trim($q).'"*';
    }
}
