<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Models\UserModel;

class Providers extends BaseController
{
    // GET /api/v1/providers
    public function index()
    {
        $model = new UserModel();
        // Assuming providers identified by role = 'provider'
        $providers = $model->where('role', 'provider')->orderBy('name', 'ASC')->findAll();
        $items = array_map(function ($p) {
            return [
                'id' => (int)$p['id'],
                'name' => $p['name'] ?? ($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? ''),
                'email' => $p['email'] ?? null,
                'phone' => $p['phone'] ?? null,
                'active' => isset($p['active']) ? (bool)$p['active'] : true,
            ];
        }, $providers);

        return $this->response->setJSON(['data' => $items]);
    }
}
