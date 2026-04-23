<?php
class PagesController {
    public function list(Request $req): void { Auth::requireRole('editor'); Response::success(Page::all(['status'=>$req->get('status')])); }
    public function create(Request $req): void {
        Auth::requireRole('editor');
        $body=$req->json()??[];
        $d=Validator::validate($body,['title'=>'required|string'])->failOrReturn();
        if(empty($body['slug'])){$base=strtolower(preg_replace('/[^a-z0-9]+/i','-',$d['title']));$d['slug']=trim($base,'-');}
        else $d['slug']=$body['slug'];
        $d=array_merge($d,array_intersect_key($body,array_flip(['parent_id','status','template_id','meta_title','meta_desc','published_at','fields','fieldDefs'])));
        $d['author_id']=Auth::userId();
        $id=Page::create($d);
        SearchIndex::index('page',$id,$d['title'],'');
        EventEmitter::emit('page.created',['id'=>$id]);
        AuditLog::write(Auth::userId(),'created','page',$id,['title'=>$d['title'],'slug'=>$d['slug']]);
        Response::created(Page::findById($id));
    }
    public function show(Request $req, int $id): void { Auth::requireRole('editor'); Response::success(Page::findById($id)??Response::notFound()); }
    public function update(Request $req, int $id): void {
        Auth::requireRole('editor'); Page::findById($id)??Response::notFound();
        $body=$req->json()??[]; Page::update($id,$body);
        $p=Page::findById($id); SearchIndex::index('page',$id,$p['title'],'');
        EventEmitter::emit('page.updated',['id'=>$id]);
        AuditLog::write(Auth::userId(),'updated','page',$id,array_intersect_key($body,array_flip(['title','slug','status','meta_title','meta_desc'])));
        Response::success($p);
    }
    public function delete(Request $req, int $id): void { Auth::requireRole('admin'); $p=Page::findById($id)??Response::notFound(); Page::delete($id); SearchIndex::remove('page',$id); EventEmitter::emit('page.deleted',['id'=>$id]); AuditLog::write(Auth::userId(),'deleted','page',$id,['title'=>$p['title']]); Response::noContent(); }
    public function duplicate(Request $req, int $id): void {
        Auth::requireRole('editor');
        $p = Page::findById($id) ?? Response::notFound();
        $newTitle = $p['title'] . ' (Copy)';
        $base = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $newTitle));
        $newSlug  = trim($base, '-');
        $data = [
            'title'       => $newTitle,
            'slug'        => $newSlug,
            'status'      => 'draft',
            'parent_id'   => $p['parent_id']   ?? null,
            'template_id' => $p['template_id'] ?? null,
            'meta_title'  => $p['meta_title']  ?? null,
            'meta_desc'   => $p['meta_desc']   ?? null,
            'fields'      => $p['fields']      ?? [],
            'author_id'   => Auth::userId(),
        ];
        $newId = Page::create($data);
        SearchIndex::index('page', $newId, $newTitle, '');
        AuditLog::write(Auth::userId(), 'duplicated', 'page', $newId, ['source_id' => $id, 'title' => $newTitle]);
        Response::created(Page::findById($newId));
    }
    public function reorder(Request $req): void { Auth::requireRole('editor'); $items=($req->json())['items']??[]; Page::reorder($items); Response::success(['reordered'=>count($items)]); }
    public function publicList(Request $req): void {
        if(!RateLimit::checkContentApi($req->ip())) Response::tooManyRequests();
        Response::success(Page::all(['status'=>'published']));
    }
    public function publicShow(Request $req, int $id): void {
        if(!RateLimit::checkContentApi($req->ip())) Response::tooManyRequests();
        $p=Page::findById($id)??Response::notFound();
        if($p['status']!=='published') Response::notFound();
        Response::success($p);
    }
}
