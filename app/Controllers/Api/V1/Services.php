<?php

namespace App\Controllers\Api\V1;
use App\Models\ServiceModel;

class Services extends BaseApiController
{
    // GET /api/v1/services
    public function index()
    {
        [$page, $length, $offset] = $this->paginationParams();
        [$sortField, $sortDir] = $this->sortParam(['id','name','duration_min','price','active'], 'name');

        $model = new ServiceModel();

        // data
        $rows = $model->orderBy($sortField, strtoupper($sortDir))->findAll($length, $offset);

        // total
        $total = $model->builder()->countAllResults();

        // Shape to a stable API-friendly structure
        $items = array_map(function ($s) {
            return [
                'id' => (int)$s['id'],
                'name' => $s['name'],
                'durationMin' => (int)($s['duration_min'] ?? 30),
                'price' => isset($s['price']) ? (float)$s['price'] : null,
                'active' => isset($s['active']) ? (bool)$s['active'] : true,
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
