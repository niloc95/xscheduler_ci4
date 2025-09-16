<?php
namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\UserModel;
use App\Models\CustomerModel;

class Users extends BaseController
{
    protected UserModel $users;
    protected ?CustomerModel $customers = null;

    public function __construct()
    {
        $this->users = new UserModel();
        $db = \Config\Database::connect();
        if ($db->tableExists('customers')) {
            $this->customers = new CustomerModel();
        }
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
            // Customers that might still reside in users table (legacy)
            $customersViaUsers   = (int)$this->users->where('role','customer')->countAllResults();
            $customersReal = $this->customers? $this->customers->countAllSafe() : 0;
            $counts['customers'] = max($customersViaUsers, $customersReal);
            $counts['total'] = $counts['admins'] + $counts['providers'] + $counts['staff'] + $counts['customers'];
            return $this->response->setJSON(['status'=>'ok','counts'=>$counts]);
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON(['status'=>'error','message'=>$e->getMessage()]);
        }
    }

    // GET /api/users?role=provider|admin|staff|customer
    public function index()
    {
        $role  = $this->request->getGet('role');
        $q     = $this->request->getGet('q');
        $limit = (int)($this->request->getGet('limit') ?? 50);
        $users = [];
        $customers = [];
        try {
            if ($role === 'customer') {
                if ($this->customers) {
                    $customers = $this->customers->search(['q'=>$q,'limit'=>$limit]);
                } else {
                    $users = $this->users->where('role','customer')->like('name',$q ?? '', 'both')->limit($limit)->find();
                }
            } elseif ($role === 'staff') {
                // Include both staff and receptionist roles under unified 'staff' filter
                $users = $this->users->groupStart()->where('role','staff')->orWhere('role','receptionist')->groupEnd()
                    ->like('name',$q ?? '', 'both')->limit($limit)->find();
            } elseif (in_array((string)$role, ['admin','provider','receptionist'], true)) {
                $users = $this->users->where('role',$role)->like('name',$q ?? '', 'both')->limit($limit)->find();
            } else { // all
                $users = $this->users->like('name',$q ?? '', 'both')->limit($limit)->find();
                if ($this->customers) {
                    $customers = $this->customers->search(['q'=>$q,'limit'=>$limit]);
                }
            }
            $users = array_map(fn($u)=> $u + ['_type'=>'user'], $users);
            $customers = array_map(fn($c)=> $c + ['_type'=>'customer','name'=>trim(($c['first_name']??'').' '.($c['last_name']??''))], $customers);
            return $this->response->setJSON([
                'status'=>'ok',
                'role'=>$role ?: 'all',
                'items'=>array_values(array_merge($users,$customers))
            ]);
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON(['status'=>'error','message'=>$e->getMessage()]);
        }
    }
}
