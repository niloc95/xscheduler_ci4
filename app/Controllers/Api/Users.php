<?php
namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\UserModel;

class Users extends BaseController
{
    protected UserModel $users;

    public function __construct()
    {
        $this->users = new UserModel();
    }

    // GET /api/user-counts
    public function counts()
    {
        try {
            // IMPORTANT: countAllResults(false) does NOT reset builder; previous version caused cascading WHERE conditions
            // Use default reset (true) to ensure independent counts
            $counts = [];
            $counts['admins']    = (int)$this->users->where('role','admin')->countAllResults();
            $counts['providers'] = (int)$this->users->where('role','provider')->countAllResults();
            $counts['staff']     = (int)$this->users->whereIn('role',['staff','receptionist'])->countAllResults();
            // Customers are managed separately; exclude from totals
            $counts['total'] = $counts['admins'] + $counts['providers'] + $counts['staff'];
            return $this->response->setJSON(['status'=>'ok','counts'=>$counts]);
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON(['status'=>'error','message'=>$e->getMessage()]);
        }
    }

    // GET /api/users?role=provider|admin|staff
    public function index()
    {
        $role  = $this->request->getGet('role');
        $q     = $this->request->getGet('q');
        $limit = (int)($this->request->getGet('limit') ?? 50);
        $users = [];
        try {
            if ($role === 'staff') {
                // Include both staff and receptionist roles under unified 'staff' filter
                $users = $this->users->groupStart()->where('role','staff')->orWhere('role','receptionist')->groupEnd()
                    ->like('name',$q ?? '', 'both')->limit($limit)->find();
            } elseif (in_array((string)$role, ['admin','provider','receptionist'], true)) {
                $users = $this->users->where('role',$role)->like('name',$q ?? '', 'both')->limit($limit)->find();
            } else { // all
                $users = $this->users->like('name',$q ?? '', 'both')->limit($limit)->find();
            }
            $users = array_map(fn($u)=> $u + ['_type'=>'user'], $users);
            return $this->response->setJSON([
                'status'=>'ok',
                'role'=>$role ?: 'all',
                'items'=>$users
            ]);
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON(['status'=>'error','message'=>$e->getMessage()]);
        }
    }
}
