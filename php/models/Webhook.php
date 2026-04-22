<?php
class Webhook {
    public static function all(): array { return Database::query("SELECT id,name,url,events,is_active,last_fired_at,last_status,created_at,updated_at FROM webhooks ORDER BY name ASC"); }
    public static function findById(int $id): ?array { return Database::queryOne("SELECT * FROM webhooks WHERE id=?",[$id]); }
    public static function create(array $d): int { $now=time(); Database::execute("INSERT INTO webhooks (name,url,secret,events,is_active,created_at,updated_at) VALUES(?,?,?,?,?,?,?)",[$d['name'],$d['url'],$d['secret'],json_encode($d['events']??[]),(int)($d['is_active']??1),$now,$now]); return Database::lastInsertId(); }
    public static function update(int $id, array $d): void { $sets=[]; $params=[]; foreach(['name','url'] as $c){if(array_key_exists($c,$d)){$sets[]="{$c}=?";$params[]=$d[$c];}} if(array_key_exists('is_active',$d)){$sets[]='is_active=?';$params[]=(int)$d['is_active'];} if(array_key_exists('events',$d)){$sets[]='events=?';$params[]=json_encode($d['events']);} if(array_key_exists('secret',$d)&&$d['secret']){$sets[]='secret=?';$params[]=$d['secret'];} $sets[]='updated_at=?'; $params[]=time(); $params[]=$id; Database::execute("UPDATE webhooks SET ".implode(',',$sets)." WHERE id=?",$params); }
    public static function delete(int $id): void { Database::execute("DELETE FROM webhooks WHERE id=?",[$id]); }
    public static function recordDelivery(int $id, string $event, string $payload, string $sig, int $status, string $response, int $ms): void { Database::execute("INSERT INTO webhook_deliveries (webhook_id,event,payload,signature,status_code,response,duration_ms,fired_at) VALUES(?,?,?,?,?,?,?,?)",[$id,$event,$payload,$sig,$status,$response,$ms,time()]); Database::execute("UPDATE webhooks SET last_fired_at=?,last_status=?,updated_at=? WHERE id=?",[time(),$status,time(),$id]); }
    public static function deliveries(int $id, int $page=1): array { return Database::paginate("SELECT * FROM webhook_deliveries WHERE webhook_id=? ORDER BY fired_at DESC",[$id],$page,50); }
    public static function findActive(): array { return Database::query("SELECT * FROM webhooks WHERE is_active=1"); }
}
