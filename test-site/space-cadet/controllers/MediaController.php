<?php
class MediaController {
    public function list(Request $req): void {
        Auth::requireRole('editor');
        $opts=['folder_id'=>$req->getInt('folder_id')||null,'mime'=>$req->get('mime'),'q'=>$req->get('q'),'page'=>$req->getInt('page',1),'per_page'=>$req->getInt('per_page',40)];
        $result=Media::list($opts);
        Response::paginated($result['rows'],$result['total'],(int)$opts['page'],(int)$opts['per_page']);
    }
    public function upload(Request $req): void {
        Auth::requireRole('editor');
        require_once __DIR__.'/../media/Uploader.php';
        require_once __DIR__.'/../media/ImageProcessor.php';
        require_once __DIR__.'/../media/SvgSanitizer.php';
        $file=$req->file('file');
        if(!$file||$file['error']!==UPLOAD_ERR_OK) Response::error('No file uploaded or upload error.',400);
        $result=Uploader::handle($file,Auth::userId());
        AuditLog::write(Auth::userId(),'created','media',(int)$result['id'],['filename'=>$result['original_name']]);
        Response::created($result);
    }
    public function show(Request $req, int $id): void { Auth::requireRole('editor'); Response::success(Media::findById($id)??Response::notFound()); }
    public function update(Request $req, int $id): void {
        Auth::requireRole('editor'); Media::findById($id)??Response::notFound();
        $d=Validator::validate($req->json()??[],['alt_text'=>'string','caption'=>'string'])->failOrReturn();
        Media::update($id,$d); AuditLog::write(Auth::userId(),'updated','media',$id,$d); Response::success(Media::findById($id));
    }
    public function delete(Request $req, int $id): void {
        Auth::requireRole('editor');
        $media=Media::delete($id)??Response::notFound();
        // Delete physical files
        foreach([$media['filename'],$media['webp_path']??null] as $f){ if($f&&file_exists(SC_UPLOADS.'/'.$f)) unlink(SC_UPLOADS.'/'.$f); }
        if($media['thumb_path']&&file_exists(SC_THUMBS.'/'.$media['thumb_path'])) unlink(SC_THUMBS.'/'.$media['thumb_path']);
        AuditLog::write(Auth::userId(),'deleted','media',$id,['filename'=>$media['original_name']]);
        Response::noContent();
    }
}
