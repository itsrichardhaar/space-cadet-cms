<?php
class CollectionItemsController {
    public function list(Request $req, int $collectionId): void {
        Auth::requireRole('editor');
        Collection::findById($collectionId)??Response::notFound();
        $opts=['status'=>$req->get('status'),'folder_id'=>$req->getInt('folder_id')||null,'label'=>$req->get('label'),'q'=>$req->get('q'),'sort'=>$req->get('sort','created_at'),'direction'=>$req->get('direction','desc'),'page'=>$req->getInt('page',1),'per_page'=>$req->getInt('per_page',20)];
        $result=CollectionItem::list($collectionId,$opts);
        Response::paginated($result['rows'],$result['total'],(int)$opts['page'],(int)$opts['per_page']);
    }
    public function create(Request $req, int $collectionId): void {
        Auth::requireRole('editor');
        Collection::findById($collectionId)??Response::notFound();
        $body=$req->json()??[];
        $d=Validator::validate($body,['title'=>'required|string'])->failOrReturn();
        if(empty($body['slug'])) $d['slug']=CollectionItem::uniqueSlug($collectionId,$d['title']);
        else $d['slug']=$body['slug'];
        $d['fields']=$body['fields']??[];
        $d['labels']=$body['labels']??[];
        $d['status']=$body['status']??'draft';
        $d['folder_id']=$body['folder_id']??null;
        $id=CollectionItem::create($collectionId,$d,Auth::userId());
        SearchIndex::index('collection_item',$id,$d['title'],implode(' ',array_map('strval',$d['fields'])));
        EventEmitter::emit('item.created',['collection_id'=>$collectionId,'item_id'=>$id]);
        AuditLog::write(Auth::userId(),'created','collection_item',$id,['title'=>$d['title'],'collection_id'=>$collectionId]);
        Response::created(CollectionItem::findById($id));
    }
    public function show(Request $req, int $collectionId, int $itemId): void {
        Auth::requireRole('editor');
        $item=CollectionItem::findById($itemId)??Response::notFound();
        if((int)$item['collection_id']!==$collectionId) Response::notFound();
        Response::success($item);
    }
    public function update(Request $req, int $collectionId, int $itemId): void {
        Auth::requireRole('editor');
        $item=CollectionItem::findById($itemId)??Response::notFound();
        if((int)$item['collection_id']!==$collectionId) Response::notFound();
        $body=$req->json()??[];
        CollectionItem::update($itemId,$body);
        $updated=CollectionItem::findById($itemId);
        SearchIndex::index('collection_item',$itemId,$updated['title'],implode(' ',array_map('strval',$updated['fields']??[])));
        Revision::snapshot('collection_item',$itemId,Auth::userId());
        EventEmitter::emit('item.updated',['collection_id'=>$collectionId,'item_id'=>$itemId,'status'=>$body['status']??$item['status']]);
        AuditLog::write(Auth::userId(),'updated','collection_item',$itemId,array_intersect_key($body,array_flip(['title','status','slug','fields'])));
        Response::success($updated);
    }
    public function delete(Request $req, int $collectionId, int $itemId): void {
        Auth::requireRole('editor');
        $item=CollectionItem::findById($itemId)??Response::notFound();
        if((int)$item['collection_id']!==$collectionId) Response::notFound();
        CollectionItem::delete($itemId);
        SearchIndex::remove('collection_item',$itemId);
        EventEmitter::emit('item.deleted',['collection_id'=>$collectionId,'item_id'=>$itemId]);
        AuditLog::write(Auth::userId(),'deleted','collection_item',$itemId,['title'=>$item['title']]);
        Response::noContent();
    }
    public function bulk(Request $req, int $collectionId): void {
        Auth::requireRole('editor');
        $body=$req->json()??[];
        $ids=(array)($body['ids']??[]);
        $action=$body['action']??'';
        if(empty($ids)||!in_array($action,['publish','archive','draft','delete'],true)) Response::error('Invalid bulk action.',400);
        if($action==='delete'){
            foreach($ids as $id){CollectionItem::delete((int)$id);SearchIndex::remove('collection_item',(int)$id);}
        } else {
            CollectionItem::bulkUpdate($collectionId,$ids,$action);
        }
        Response::success(['affected'=>count($ids)]);
    }
    public function duplicate(Request $req, int $collectionId, int $itemId): void {
        Auth::requireRole('editor');
        $item = CollectionItem::findById($itemId) ?? Response::notFound();
        if ((int)$item['collection_id'] !== $collectionId) Response::notFound();
        $newTitle = $item['title'] . ' (Copy)';
        $newSlug  = CollectionItem::uniqueSlug($collectionId, $newTitle);
        $data = [
            'title'     => $newTitle,
            'slug'      => $newSlug,
            'status'    => 'draft',
            'fields'    => $item['fields'] ?? [],
            'labels'    => [],
            'folder_id' => $item['folder_id'] ?? null,
        ];
        $id = CollectionItem::create($collectionId, $data, Auth::userId());
        AuditLog::write(Auth::userId(), 'duplicated', 'collection_item', $id, ['source_id' => $itemId, 'title' => $newTitle]);
        Response::created(CollectionItem::findById($id));
    }
    // ── Public API ───────────────────────────────────────────
    public function publicList(Request $req, int $collectionId): void {
        if(!RateLimit::checkContentApi($req->ip())) Response::tooManyRequests();
        Collection::findById($collectionId)??Response::notFound();
        $opts=['status'=>'published','label'=>$req->get('label'),'q'=>$req->get('q'),'sort'=>$req->get('sort','published_at'),'direction'=>$req->get('direction','desc'),'page'=>$req->getInt('page',1),'per_page'=>min($req->getInt('per_page',20),100)];
        $result=CollectionItem::list($collectionId,$opts);
        Response::paginated($result['rows'],$result['total'],(int)$opts['page'],(int)$opts['per_page']);
    }
    public function publicShow(Request $req, int $collectionId, int $itemId): void {
        if(!RateLimit::checkContentApi($req->ip())) Response::tooManyRequests();
        $item=CollectionItem::findById($itemId)??Response::notFound();
        if((int)$item['collection_id']!==$collectionId||$item['status']!=='published') Response::notFound();
        Response::success($item);
    }
}
