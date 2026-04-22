<?php
class LabelsController {
    public function list(Request $req): void { Auth::requireRole('editor'); Response::success(Label::all()); }
    public function create(Request $req): void { Auth::requireRole('editor'); $d=Validator::validate($req->json()??[],['name'=>'required|string','slug'=>'required|slug|unique:labels.slug'])->failOrReturn(); $id=Label::create($d); Response::created(Label::findById($id)); }
    public function update(Request $req, int $id): void { Auth::requireRole('editor'); Label::findById($id)??Response::notFound(); Label::update($id,$req->json()??[]); Response::success(Label::findById($id)); }
    public function delete(Request $req, int $id): void { Auth::requireRole('admin'); Label::findById($id)??Response::notFound(); Label::delete($id); Response::noContent(); }
}
