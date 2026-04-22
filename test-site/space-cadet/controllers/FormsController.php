<?php
class FormsController {
    public function list(Request $req): void { Auth::requireRole('editor'); Response::success(Form::all()); }
    public function create(Request $req): void { Auth::requireRole('admin'); $d=Validator::validate($req->json()??[],['name'=>'required|string','slug'=>'required|slug|unique:forms.slug'])->failOrReturn(); $id=Form::create($d); AuditLog::write(Auth::userId(),'created','form',$id,['name'=>$d['name']]); Response::created(Form::findById($id)); }
    public function show(Request $req, int $id): void { Auth::requireRole('editor'); Response::success(Form::findById($id)??Response::notFound()); }
    public function update(Request $req, int $id): void { Auth::requireRole('admin'); $f=Form::findById($id)??Response::notFound(); Form::update($id,$req->json()??[]); AuditLog::write(Auth::userId(),'updated','form',$id,['name'=>$f['name']]); Response::success(Form::findById($id)); }
    public function delete(Request $req, int $id): void { Auth::requireRole('admin'); $f=Form::findById($id)??Response::notFound(); Form::delete($id); AuditLog::write(Auth::userId(),'deleted','form',$id,['name'=>$f['name']]); Response::noContent(); }
    public function replaceFields(Request $req, int $id): void { Auth::requireRole('admin'); Form::findById($id)??Response::notFound(); Form::replaceFields($id,($req->json())['fields']??[]); Response::success(Form::findById($id)); }
    public function submissions(Request $req, int $id): void {
        Auth::requireRole('editor'); Form::findById($id)??Response::notFound();
        $opts=['is_read'=>$req->get('is_read',null)===null?null:(int)$req->get('is_read'),'is_spam'=>$req->get('is_spam',null)===null?null:(int)$req->get('is_spam'),'page'=>$req->getInt('page',1),'per_page'=>$req->getInt('per_page',50)];
        $result=FormSubmission::list($id,$opts);
        Response::paginated($result['rows'],$result['total'],(int)$opts['page'],(int)$opts['per_page']);
    }
    public function showSubmission(Request $req, int $formId, int $subId): void { Auth::requireRole('editor'); Response::success(FormSubmission::findById($subId)??Response::notFound()); }
    public function updateSubmission(Request $req, int $formId, int $subId): void { Auth::requireRole('editor'); FormSubmission::findById($subId)??Response::notFound(); FormSubmission::update($subId,$req->json()??[]); Response::success(FormSubmission::findById($subId)); }
    public function deleteSubmission(Request $req, int $formId, int $subId): void { Auth::requireRole('admin'); FormSubmission::delete($subId); Response::noContent(); }
    public function exportSubmissions(Request $req, int $id): void {
        Auth::requireRole('admin'); $form=Form::findById($id)??Response::notFound();
        $subs=Database::query("SELECT * FROM form_submissions WHERE form_id=? ORDER BY created_at DESC",[$id]);
        header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="submissions-'.$form['slug'].'.csv"');
        $out=fopen('php://output','w');
        if(!empty($subs)){fputcsv($out,array_keys(json_decode($subs[0]['data'],true)??[])+['submitted_at','ip']);}
        foreach($subs as $s){ $d=json_decode($s['data'],true)??[]; $d['submitted_at']=date('c',$s['created_at']); $d['ip']=$s['ip_address']??''; fputcsv($out,$d); }
        fclose($out); exit;
    }
    public function publicSubmit(Request $req, int $formId): void {
        if(!RateLimit::checkContentApi($req->ip())) Response::tooManyRequests();
        $form=Form::findById($formId)??Response::notFound();
        $body=$req->json()??$_POST;
        // Honeypot check
        if(!empty($body[$form['honeypot_field']??'website'])) { Response::success(['ok'=>true]); } // silent drop
        FormSubmission::create($formId,$body,$req);
        EventEmitter::emit('form.submitted',['form_id'=>$formId]);
        Response::success(['message'=>$form['success_message']]);
    }
}
