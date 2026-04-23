<?php
class SettingsController {
    public function list(Request $req): void {
        Auth::requireRole('admin');
        $rows=Database::query("SELECT key,value FROM settings ORDER BY key ASC");
        $settings=[];
        foreach($rows as $r) $settings[$r['key']]=$r['value'];
        Response::success($settings);
    }
    public function update(Request $req): void {
        Auth::requireRole('admin');
        $data=$req->json()??[];
        $protected=['schema_version']; // Never overwrite via API
        $stmt=Database::get()->prepare("INSERT INTO settings (key,value,updated_at) VALUES(?,?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value,updated_at=excluded.updated_at");
        foreach($data as $k=>$v){
            if(in_array($k,$protected,true)) continue;
            $stmt->execute([preg_replace('/[^a-z0-9_]/','',$k),(string)$v,time()]);
        }
        Response::success(['updated'=>count($data)]);
    }
    public function stats(Request $req): void {
        Auth::requireRole('editor');
        Response::success([
            'collections' => (int)(Database::queryOne("SELECT COUNT(*) as n FROM collections")['n'] ?? 0),
            'items'       => (int)(Database::queryOne("SELECT COUNT(*) as n FROM collection_items")['n'] ?? 0),
            'pages'       => (int)(Database::queryOne("SELECT COUNT(*) as n FROM pages")['n'] ?? 0),
            'media'       => (int)(Database::queryOne("SELECT COUNT(*) as n FROM media")['n'] ?? 0),
            'members'     => (int)(Database::queryOne("SELECT COUNT(*) as n FROM users WHERE role IN ('free_member','paid_member')")['n'] ?? 0),
            'forms'       => (int)(Database::queryOne("SELECT COUNT(*) as n FROM forms")['n'] ?? 0),
        ]);
    }
    public function auditLog(Request $req): void {
        Auth::requireRole('admin');
        $page=$req->getInt('page',1); $perPage=50;
        $result=Database::paginate("SELECT al.*,u.display_name as user_name FROM audit_log al LEFT JOIN users u ON u.id=al.user_id ORDER BY al.created_at DESC",[],page:$page,perPage:$perPage);
        Response::paginated($result['rows'],$result['total'],$page,$perPage);
    }
}
