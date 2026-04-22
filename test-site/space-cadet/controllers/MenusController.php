<?php
class MenusController {
    public function list(Request $req): void { Auth::requireRole('editor'); Response::success(Menu::all()); }
    public function create(Request $req): void { Auth::requireRole('admin'); $d=Validator::validate($req->json()??[],['name'=>'required|string','slug'=>'required|slug|unique:menus.slug'])->failOrReturn(); $id=Menu::create($d); AuditLog::write(Auth::userId(),'created','menu',$id,['name'=>$d['name']]); Response::created(Menu::findById($id)); }
    public function show(Request $req, int $id): void { Auth::requireRole('editor'); Response::success(Menu::findById($id)??Response::notFound()); }
    public function update(Request $req, int $id): void { Auth::requireRole('editor'); $m=Menu::findById($id)??Response::notFound(); Menu::update($id,$req->json()??[]); AuditLog::write(Auth::userId(),'updated','menu',$id,['name'=>$m['name']]); Response::success(Menu::findById($id)); }
    public function delete(Request $req, int $id): void { Auth::requireRole('admin'); $m=Menu::findById($id)??Response::notFound(); Menu::delete($id); AuditLog::write(Auth::userId(),'deleted','menu',$id,['name'=>$m['name']]); Response::noContent(); }
    public function replaceItems(Request $req, int $id): void { Auth::requireRole('editor'); Menu::findById($id)??Response::notFound(); Menu::replaceItems($id,($req->json())['items']??[]); Response::success(Menu::findById($id)); }
    public function publicShow(Request $req, int $id): void {
        if(!RateLimit::checkContentApi($req->ip())) Response::tooManyRequests();
        Response::success(Menu::findById($id)??Response::notFound());
    }
}
