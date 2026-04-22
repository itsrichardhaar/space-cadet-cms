<?php
class ApiKey {
    public static function forUser(int $userId): array { return Database::query("SELECT id,name,key_prefix,scopes,last_used_at,expires_at,created_at FROM api_keys WHERE user_id=? ORDER BY created_at DESC",[$userId]); }
    public static function all(): array { return Database::query("SELECT ak.id,ak.name,ak.key_prefix,ak.scopes,ak.last_used_at,ak.expires_at,ak.created_at,u.display_name as user_name FROM api_keys ak LEFT JOIN users u ON u.id=ak.user_id ORDER BY ak.created_at DESC"); }
    public static function create(int $userId, string $name, array $scopes, ?int $expiresAt=null): array { [$plain,$prefix,$hash]=Auth::generateApiKey(); Database::execute("INSERT INTO api_keys (user_id,name,key_hash,key_prefix,scopes,expires_at,created_at) VALUES(?,?,?,?,?,?,?)",[$userId,$name,$hash,$prefix,json_encode($scopes),$expiresAt,time()]); $id=Database::lastInsertId(); return ['id'=>$id,'key'=>$plain,'prefix'=>$prefix,'scopes'=>$scopes]; }
    public static function delete(int $id): void { Database::execute("DELETE FROM api_keys WHERE id=?",[$id]); }
}
