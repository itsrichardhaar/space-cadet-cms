<?php
class Media {
    public static function list(array $opts=[]): array {
        $where=['1=1']; $params=[];
        if(!empty($opts['folder_id'])){$where[]='folder_id=?';$params[]=(int)$opts['folder_id'];}
        if(!empty($opts['mime'])){$where[]='mime_type LIKE ?';$params[]=$opts['mime'].'%';}
        if(!empty($opts['q'])){$where[]='original_name LIKE ?';$params[]='%'.$opts['q'].'%';}
        $sql="SELECT * FROM media WHERE ".implode(' AND ',$where)." ORDER BY created_at DESC";
        return Database::paginate($sql,$params,(int)($opts['page']??1),min((int)($opts['per_page']??40),100));
    }
    public static function findById(int $id): ?array { return Database::queryOne("SELECT * FROM media WHERE id=?",[$id]); }
    public static function create(array $data): int {
        $now=time(); Database::execute("INSERT INTO media (filename,original_name,mime_type,size_bytes,width,height,folder_id,alt_text,caption,webp_path,thumb_path,uploaded_by,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)",[$data['filename'],$data['original_name'],$data['mime_type'],$data['size_bytes'],$data['width']??null,$data['height']??null,$data['folder_id']??null,$data['alt_text']??null,$data['caption']??null,$data['webp_path']??null,$data['thumb_path']??null,$data['uploaded_by']??null,$now,$now]); return Database::lastInsertId();
    }
    public static function update(int $id, array $data): void {
        $sets=[]; $params=[];
        foreach(['alt_text','caption','folder_id'] as $c){if(array_key_exists($c,$data)){$sets[]="{$c}=?";$params[]=$data[$c];}}
        $sets[]='updated_at=?'; $params[]=time(); $params[]=$id;
        Database::execute("UPDATE media SET ".implode(',',$sets)." WHERE id=?",$params);
    }
    public static function delete(int $id): ?array {
        $row=self::findById($id); if(!$row) return null;
        Database::execute("DELETE FROM media WHERE id=?",[$id]);
        return $row;
    }
    public static function url(array $media): string {
        return '/storage/uploads/'.$media['filename'];
    }
    public static function thumbUrl(array $media): ?string {
        return $media['thumb_path'] ? '/storage/thumbnails/'.$media['thumb_path'] : null;
    }
    public static function webpUrl(array $media): ?string {
        return $media['webp_path'] ? '/storage/uploads/'.$media['webp_path'] : null;
    }
}
