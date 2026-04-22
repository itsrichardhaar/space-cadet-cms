<?php
class SmartForgeController {
    public function analyze(Request $req): void {
        Auth::requireRole('editor');
        $body=$req->json()??[];
        $html=$body['html']??''; $provider=$body['provider']??'claude';
        if(!$html) Response::error('html is required.',400);
        if(!in_array($provider,['claude','openai','gemini'],true)) Response::error('Invalid provider.',400);
        $now=time();
        Database::execute("INSERT INTO forge_jobs (user_id,provider,input_html,status,created_at) VALUES(?,?,?,?,?)",[Auth::userId(),$provider,$html,'processing',$now]);
        $jobId=Database::lastInsertId();
        try {
            require_once __DIR__.'/../forge/SmartForge.php';
            $result=SmartForge::analyze($html,$provider,$body['instructions']??'');
            Database::execute("UPDATE forge_jobs SET result_json=?,status='done',completed_at=?,prompt_used=? WHERE id=?",[json_encode($result),time(),$result['prompt_used']??null,$jobId]);
            Response::created(['job_id'=>$jobId,'result'=>$result]);
        } catch(Throwable $e) {
            Database::execute("UPDATE forge_jobs SET status='failed',error=?,completed_at=? WHERE id=?",[$e->getMessage(),time(),$jobId]);
            Response::error('AI analysis failed: '.$e->getMessage(),502);
        }
    }
    public function jobStatus(Request $req, int $id): void {
        Auth::requireRole('editor');
        $job=Database::queryOne("SELECT * FROM forge_jobs WHERE id=?",[$id])??Response::notFound();
        unset($job['input_html']); // Too large for polling response
        if($job['result_json']) $job['result']=json_decode($job['result_json'],true);
        Response::success($job);
    }
    public function apply(Request $req, int $id): void {
        Auth::requireRole('editor');
        $job=Database::queryOne("SELECT * FROM forge_jobs WHERE id=? AND status='done'",[$id])??Response::notFound();
        $result=json_decode($job['result_json'],true);
        $body=$req->json()??[];
        $target=$body['target']??'page'; // 'page' or collection slug
        if($target==='page'){
            $pageId=Page::create(['title'=>$result['title']??'Untitled','slug'=>'forge-'.uniqid(),'status'=>'draft','fields'=>$result['fields']??[],'author_id'=>Auth::userId()]);
            Response::created(['type'=>'page','id'=>$pageId]);
        } else {
            $coll=Collection::findBySlug($target)??Response::error("Collection '{$target}' not found.",404);
            $itemId=CollectionItem::create($coll['id'],['title'=>$result['title']??'Untitled','slug'=>'forge-'.uniqid(),'status'=>'draft','fields'=>$result['fields']??[]],Auth::userId());
            Response::created(['type'=>'collection_item','collection'=>$target,'id'=>$itemId]);
        }
    }
}
