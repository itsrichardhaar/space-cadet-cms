<?php
class UsersController {
    public function list(Request $req): void {
        Auth::requireRole('admin');
        $users=User::all(['role'=>$req->get('role'),'status'=>$req->get('status'),'q'=>$req->get('q')]);
        Response::success(array_map([User::class,'sanitize'],$users));
    }
    public function create(Request $req): void {
        Auth::requireRole('admin');
        $data=Validator::validate($req->json()??[],[
            'email'=>'required|email|unique:users.email',
            'display_name'=>'required|string|min:2',
            'password'=>'required|string|min:8',
            'role'=>'required|in:super_admin,admin,developer,editor,free_member,paid_member',
        ])->failOrReturn();
        // Prevent privilege escalation
        if($data['role']==='super_admin'&&!Auth::hasRole('super_admin')) Response::forbidden();
        $id=User::create($data);
        AuditLog::write(Auth::userId(),'created','user',$id,['email'=>$data['email'],'role'=>$data['role']]);
        Response::created(User::sanitize(User::findById($id)));
    }
    public function show(Request $req, int $id): void {
        Auth::requireRole('admin');
        $user=User::findById($id); if(!$user) Response::notFound();
        Response::success(User::sanitize($user));
    }
    public function update(Request $req, int $id): void {
        Auth::requireRole('admin');
        $user=User::findById($id); if(!$user) Response::notFound();
        $data=Validator::validate($req->json()??[],[
            'email'=>'email|unique:users.email',
            'display_name'=>'string|min:2',
            'role'=>'in:super_admin,admin,developer,editor,free_member,paid_member',
            'status'=>'in:active,suspended,pending',
        ])->failOrReturn();
        User::update($id,$data);
        AuditLog::write(Auth::userId(),'updated','user',$id,array_intersect_key($data,array_flip(['role','status','email'])));
        Response::success(User::sanitize(User::findById($id)));
    }
    public function delete(Request $req, int $id): void {
        Auth::requireRole('super_admin');
        if($id===Auth::userId()) Response::error('Cannot delete your own account.',400);
        $u=User::findById($id)??Response::notFound();
        User::delete($id);
        AuditLog::write(Auth::userId(),'deleted','user',$id,['email'=>$u['email']]);
        Response::noContent();
    }
}
