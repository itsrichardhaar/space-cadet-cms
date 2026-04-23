<?php
class MembersController {
    private function memberFilter(array $opts): array {
        $where=["role IN ('free_member','paid_member')"]; $params=[];
        if(!empty($opts['role'])){$where[]='role=?';$params[]=$opts['role'];}
        if(!empty($opts['status'])){$where[]='status=?';$params[]=$opts['status'];}
        if(!empty($opts['q'])){$where[]='(display_name LIKE ? OR email LIKE ?)';$q='%'.$opts['q'].'%';$params[]=$q;$params[]=$q;}
        $sql="SELECT id,email,display_name,role,status,last_login_at,created_at,updated_at FROM users WHERE ".implode(' AND ',$where)." ORDER BY created_at DESC";
        return Database::paginate($sql,$params,(int)($opts['page']??1),(int)($opts['per_page']??20));
    }
    public function list(Request $req): void { Auth::requireRole('editor'); $r=$this->memberFilter(['role'=>$req->get('role'),'status'=>$req->get('status'),'q'=>$req->get('q'),'page'=>$req->getInt('page',1),'per_page'=>$req->getInt('per_page',20)]); Response::paginated($r['rows'],$r['total'],$req->getInt('page',1),$req->getInt('per_page',20)); }
    public function create(Request $req): void {
        Auth::requireRole('admin');
        $d=Validator::validate($req->json()??[],['email'=>'required|email|unique:users.email','display_name'=>'required|string|min:2','password'=>'required|string|min:8','role'=>'in:free_member,paid_member'])->failOrReturn();
        $d['role']=$d['role']??'free_member';
        $id=User::create($d);
        Response::created(User::sanitize(User::findById($id)));
    }
    public function show(Request $req, int $id): void { Auth::requireRole('editor'); $u=User::findById($id)??Response::notFound(); if(!in_array($u['role'],['free_member','paid_member'],true)) Response::notFound(); Response::success(User::sanitize($u)); }
    public function update(Request $req, int $id): void { Auth::requireRole('admin'); $u=User::findById($id)??Response::notFound(); if(!in_array($u['role'],['free_member','paid_member'],true)) Response::forbidden(); $d=Validator::validate($req->json()??[],['role'=>'in:free_member,paid_member','status'=>'in:active,suspended'])->failOrReturn(); User::update($id,$d); Response::success(User::sanitize(User::findById($id))); }
    public function delete(Request $req, int $id): void { Auth::requireRole('admin'); $u=User::findById($id)??Response::notFound(); if(!in_array($u['role'],['free_member','paid_member'],true)) Response::forbidden(); User::delete($id); Response::noContent(); }
    public function bulk(Request $req): void {
        Auth::requireRole('admin');
        $body = $req->json() ?? [];
        $ids  = array_filter(array_map('intval', (array)($body['ids'] ?? [])));
        $action = $body['action'] ?? '';
        if (empty($ids) || $action !== 'delete') Response::error('Invalid bulk action.', 400);
        foreach ($ids as $id) {
            $u = User::findById($id);
            if ($u && in_array($u['role'], ['free_member', 'paid_member'], true)) {
                User::delete($id);
            }
        }
        Response::success(['affected' => count($ids)]);
    }
}
