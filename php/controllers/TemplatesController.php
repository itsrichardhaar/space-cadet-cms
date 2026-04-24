<?php
class TemplatesController {
    public function list(Request $req): void { Auth::requireRole('developer'); Response::success(Template::all()); }
    public function create(Request $req): void { Auth::requireRole('developer'); $raw=$req->json()??[]; $d=Validator::validate($raw,['name'=>'required|string','slug'=>'required|slug|unique:templates.slug','type'=>'in:page,layout,partial'])->failOrReturn(); $d['source']=$raw['source']??''; $id=Template::create($d); Response::created(Template::findById($id)); }
    public function show(Request $req, int $id): void { Auth::requireRole('developer'); Response::success(Template::findById($id)??Response::notFound()); }
    public function update(Request $req, int $id): void { Auth::requireRole('developer'); Template::findById($id)??Response::notFound(); Template::update($id,$req->json()??[]); Response::success(Template::findById($id)); }
    public function delete(Request $req, int $id): void { Auth::requireRole('developer'); Template::findById($id)??Response::notFound(); Template::delete($id); Response::noContent(); }
}
