<?php
class WebhooksController {
    public function list(Request $req): void { Auth::requireRole('admin'); Response::success(Webhook::all()); }
    public function create(Request $req): void {
        Auth::requireRole('admin');
        $d=Validator::validate($req->json()??[],['name'=>'required|string','url'=>'required|url'])->failOrReturn();
        $d['events']=($req->json())['events']??[];
        $d['secret']=($req->json())['secret']??bin2hex(random_bytes(20));
        $id=Webhook::create($d);
        AuditLog::write(Auth::userId(),'created','webhook',$id,['name'=>$d['name'],'url'=>$d['url']]);
        Response::created(Webhook::findById($id));
    }
    public function show(Request $req, int $id): void {
        Auth::requireRole('admin');
        $w=Webhook::findById($id)??Response::notFound();
        unset($w['secret']); // write-only
        Response::success($w);
    }
    public function update(Request $req, int $id): void { Auth::requireRole('admin'); $w=Webhook::findById($id)??Response::notFound(); Webhook::update($id,$req->json()??[]); AuditLog::write(Auth::userId(),'updated','webhook',$id,['name'=>$w['name']]); $w=Webhook::findById($id); unset($w['secret']); Response::success($w); }
    public function delete(Request $req, int $id): void { Auth::requireRole('admin'); $w=Webhook::findById($id)??Response::notFound(); Webhook::delete($id); AuditLog::write(Auth::userId(),'deleted','webhook',$id,['name'=>$w['name']]); Response::noContent(); }
    public function test(Request $req, int $id): void {
        Auth::requireRole('admin');
        $w=Webhook::findById($id)??Response::notFound();
        require_once __DIR__.'/../webhooks/Dispatcher.php';
        $result=Dispatcher::send($w,'webhook.test',['message'=>'Test delivery from Space Cadet CMS']);
        Response::success($result);
    }
    public function deliveries(Request $req, int $id): void { Auth::requireRole('admin'); Webhook::findById($id)??Response::notFound(); $result=Webhook::deliveries($id,$req->getInt('page',1)); Response::paginated($result['rows'],$result['total'],$req->getInt('page',1),50); }
}
