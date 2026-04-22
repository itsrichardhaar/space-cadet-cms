<?php
class FormSubmission {
    public static function list(int $formId, array $opts=[]): array { $where=['form_id=?']; $params=[$formId]; if(isset($opts['is_read'])){$where[]='is_read=?';$params[]=(int)$opts['is_read'];} if(isset($opts['is_spam'])){$where[]='is_spam=?';$params[]=(int)$opts['is_spam'];} return Database::paginate("SELECT * FROM form_submissions WHERE ".implode(' AND ',$where)." ORDER BY created_at DESC",$params,(int)($opts['page']??1),(int)($opts['per_page']??50)); }
    public static function findById(int $id): ?array { return Database::queryOne("SELECT * FROM form_submissions WHERE id=?",[$id]); }
    public static function create(int $formId, array $data, Request $req): int { Database::execute("INSERT INTO form_submissions (form_id,data,ip_address,user_agent,referrer,created_at) VALUES(?,?,?,?,?,?)",[$formId,json_encode($data),$req->ip(),$req->userAgent(),$req->header('Referer'),time()]); return Database::lastInsertId(); }
    public static function update(int $id, array $data): void { $sets=[]; $params=[]; if(array_key_exists('is_read',$data)){$sets[]='is_read=?';$params[]=(int)$data['is_read'];} if(array_key_exists('is_spam',$data)){$sets[]='is_spam=?';$params[]=(int)$data['is_spam'];} if(empty($sets)) return; $params[]=$id; Database::execute("UPDATE form_submissions SET ".implode(',',$sets)." WHERE id=?",$params); }
    public static function delete(int $id): void { Database::execute("DELETE FROM form_submissions WHERE id=?",[$id]); }
}
