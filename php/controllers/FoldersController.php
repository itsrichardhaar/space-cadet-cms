<?php
class FoldersController {
    public function list(Request $req): void { Auth::requireRole('editor'); Response::success(Folder::tree()); }
    public function create(Request $req): void { Auth::requireRole('editor'); $d=Validator::validate($req->json()??[],['name'=>'required|string'])->failOrReturn(); $id=Folder::create($d); Response::created(Folder::findById($id)); }
    public function update(Request $req, int $id): void { Auth::requireRole('editor'); Folder::findById($id)??Response::notFound(); Folder::update($id,$req->json()??[]); Response::success(Folder::findById($id)); }
    public function delete(Request $req, int $id): void { Auth::requireRole('admin'); Folder::findById($id)??Response::notFound(); Folder::delete($id); Response::noContent(); }
}
