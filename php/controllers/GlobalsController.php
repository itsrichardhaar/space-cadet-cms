<?php
class GlobalsController {
    public function list(Request $req): void { Auth::requireRole('editor'); Response::success(GlobalGroup::all()); }
    public function create(Request $req): void { Auth::requireRole('admin'); $d=Validator::validate($req->json()??[],['name'=>'required|string','slug'=>'required|slug|unique:global_groups.slug'])->failOrReturn(); $id=GlobalGroup::create($d); AuditLog::write(Auth::userId(),'created','global_group',$id,['name'=>$d['name']]); Response::created(GlobalGroup::findById($id)); }
    public function show(Request $req, int $id): void { Auth::requireRole('editor'); Response::success(GlobalGroup::findById($id)??Response::notFound()); }
    public function update(Request $req, int $id): void { Auth::requireRole('editor'); $g=GlobalGroup::findById($id)??Response::notFound(); GlobalGroup::update($id,$req->json()??[]); AuditLog::write(Auth::userId(),'updated','global_group',$id,['name'=>$g['name']]); Response::success(GlobalGroup::findById($id)); }
    public function delete(Request $req, int $id): void { Auth::requireRole('admin'); $g=GlobalGroup::findById($id)??Response::notFound(); GlobalGroup::delete($id); AuditLog::write(Auth::userId(),'deleted','global_group',$id,['name'=>$g['name']]); Response::noContent(); }
    public function replaceFields(Request $req, int $id): void { Auth::requireRole('admin'); GlobalGroup::findById($id)??Response::notFound(); GlobalGroup::replaceFields($id,($req->json())['fields']??[]); Response::success(GlobalGroup::findById($id)); }
    public function publicShow(Request $req, int $id): void {
        if(!RateLimit::checkContentApi($req->ip())) Response::tooManyRequests();
        $g=GlobalGroup::findById($id)??Response::notFound();
        Response::success(['slug'=>$g['slug'],'values'=>$g['values']]);
    }
}
