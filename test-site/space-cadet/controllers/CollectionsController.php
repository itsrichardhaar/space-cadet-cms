<?php
class CollectionsController {
    public function list(Request $req): void {
        Auth::requireRole('editor');
        Response::success(Collection::withItemCount());
    }
    public function create(Request $req): void {
        Auth::requireRole('admin');
        $d=Validator::validate($req->json()??[],['name'=>'required|string','slug'=>'required|slug|unique:collections.slug'])->failOrReturn();
        $id=Collection::create($d);
        if(!empty(($req->json())['fields'])) Collection::replaceFields($id,($req->json())['fields']);
        EventEmitter::emit('collection.created',['id'=>$id]);
        Response::created(Collection::findById($id));
    }
    public function show(Request $req, int $id): void {
        Auth::requireRole('editor');
        $c=Collection::findById($id)??Response::notFound();
        $c['fields']=Collection::fields($id);
        Response::success($c);
    }
    public function update(Request $req, int $id): void {
        Auth::requireRole('admin');
        Collection::findById($id)??Response::notFound();
        $d=Validator::validate($req->json()??[],['name'=>'string','slug'=>'slug|unique:collections.slug'])->failOrReturn();
        Collection::update($id,$d);
        EventEmitter::emit('collection.updated',['id'=>$id]);
        $c=Collection::findById($id); $c['fields']=Collection::fields($id);
        Response::success($c);
    }
    public function delete(Request $req, int $id): void {
        Auth::requireRole('admin');
        Collection::findById($id)??Response::notFound();
        Collection::delete($id);
        EventEmitter::emit('collection.deleted',['id'=>$id]);
        Response::noContent();
    }
    public function fields(Request $req, int $id): void {
        Auth::requireRole('editor');
        Collection::findById($id)??Response::notFound();
        Response::success(Collection::fields($id));
    }
    public function replaceFields(Request $req, int $id): void {
        Auth::requireRole('admin');
        Collection::findById($id)??Response::notFound();
        $fields=($req->json())['fields']??[];
        Collection::replaceFields($id,$fields);
        Response::success(Collection::fields($id));
    }
    // ── Public API ───────────────────────────────────────────
    public function publicList(Request $req): void {
        if(!RateLimit::checkContentApi($req->ip())) Response::tooManyRequests();
        Response::success(Collection::all());
    }
    public function publicShow(Request $req, int $id): void {
        if(!RateLimit::checkContentApi($req->ip())) Response::tooManyRequests();
        $c=Collection::findById($id)??Response::notFound();
        $c['fields']=Collection::fields($id);
        Response::success($c);
    }
}
