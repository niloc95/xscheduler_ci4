<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Models\ServiceModel;

class Services extends BaseController
{
    // GET /api/v1/services
    public function index()
    {
        $model = new ServiceModel();
        $services = $model->orderBy('name', 'ASC')->findAll();
        // Shape to a stable API-friendly structure
        $items = array_map(function ($s) {
            return [
                'id' => (int)$s['id'],
                'name' => $s['name'],
                'durationMin' => (int)($s['duration_min'] ?? 30),
                'price' => isset($s['price']) ? (float)$s['price'] : null,
                'active' => isset($s['active']) ? (bool)$s['active'] : true,
            ];
        }, $services);

        return $this->response->setJSON(['data' => $items]);
    }
}
