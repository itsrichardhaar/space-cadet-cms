<?php
class SearchController {
    public function search(Request $req): void {
        Auth::requireRole('editor');
        $q=$req->get('q',''); if(strlen($q)<2) Response::success([]);
        $types=$req->getAll()['types']??[];
        $result=SearchIndex::search($q,(array)$types,$req->getInt('page',1),$req->getInt('per_page',20));
        Response::paginated($result['rows'],$result['total'],$req->getInt('page',1),$req->getInt('per_page',20));
    }
    public function publicSearch(Request $req): void {
        if(!RateLimit::checkContentApi($req->ip())) Response::tooManyRequests();
        $q=$req->get('q',''); if(strlen($q)<2) Response::success([]);
        $result=SearchIndex::search($q,['collection_item','page'],$req->getInt('page',1),min($req->getInt('per_page',20),50));
        Response::paginated($result['rows'],$result['total'],$req->getInt('page',1),$req->getInt('per_page',20));
    }
}
