<?php
class ApiKeysController {
    public function list(Request $req): void { Auth::requireRole('admin'); Response::success(ApiKey::all()); }
    public function create(Request $req): void {
        Auth::requireRole('admin');
        $d=Validator::validate($req->json()??[],['name'=>'required|string'])->failOrReturn();
        $scopes=($req->json())['scopes']??['read'];
        $result=ApiKey::create(Auth::userId(),$d['name'],$scopes);
        Response::created($result); // Full key shown only here
    }
    public function delete(Request $req, int $id): void { Auth::requireRole('admin'); ApiKey::delete($id); Response::noContent(); }
}
