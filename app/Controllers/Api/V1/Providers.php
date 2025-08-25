<?php

namespace App\Controllers\Api\V1;
use App\Models\UserModel;

class Providers extends BaseApiController
{
    // GET /api/v1/providers
    public function index()
    {
        [$page, $length, $offset] = $this->paginationParams();
        [$sortField, $sortDir] = $this->sortParam(['id','name','email','active'], 'name');

        $model = new UserModel();
        // Assuming providers identified by role = 'provider'
        $builder = $model->where('role', 'provider')->orderBy($sortField, strtoupper($sortDir));
        $rows = $builder->findAll($length, $offset);
        $total = $model->where('role', 'provider')->countAllResults();

        $items = array_map(function ($p) {
            return [
                'id' => (int)$p['id'],
                'name' => $p['name'] ?? ($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? ''),
                'email' => $p['email'] ?? null,
                'phone' => $p['phone'] ?? null,
                'active' => isset($p['active']) ? (bool)$p['active'] : true,
            ];
        }, $rows);

        return $this->ok($items, [
            'page' => $page,
            'length' => $length,
            'total' => (int)$total,
            'sort' => $sortField . ':' . $sortDir,
        ]);
    }
}
