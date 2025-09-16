<?php

namespace App\Controllers\Api\V1;
use App\Models\ServiceModel;
use App\Models\CategoryModel;

class Services extends BaseApiController
{
    protected ServiceModel $model;
    protected CategoryModel $categories;

    public function __construct()
    {
        $this->model = new ServiceModel();
        $this->categories = new CategoryModel();
    }
    // GET /api/v1/services
    public function index()
    {
        [$page, $length, $offset] = $this->paginationParams();
        [$sortField, $sortDir] = $this->sortParam(['id','name','duration_min','price','active'], 'name');

        // data
    $rows = $this->model->orderBy($sortField, strtoupper($sortDir))->findAll($length, $offset);

        // total
    $total = $this->model->builder()->countAllResults();

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

    // GET /api/v1/services/{id}
    public function show($id = null)
    {
        if (!$id) return $this->error(400, 'Missing id');
        $row = $this->model->find((int)$id);
        if (!$row) return $this->error(404, 'Not found');
        return $this->ok($row);
    }

    // POST /api/v1/services
    public function create()
    {
        $body = $this->request->getJSON(true) ?? $this->request->getPost();
        if (!$body) return $this->error(400, 'Missing body');

        $data = [
            'name' => trim($body['name'] ?? ''),
            'description' => $body['description'] ?? null,
            'duration_min' => (int)($body['durationMin'] ?? $body['duration_min'] ?? 0),
            'price' => isset($body['price']) && $body['price'] !== '' ? (float)$body['price'] : null,
            'category_id' => isset($body['categoryId']) ? (int)$body['categoryId'] : (isset($body['category_id']) ? (int)$body['category_id'] : null),
            'active' => isset($body['active']) ? (int)!!$body['active'] : 1,
        ];

        if (!$this->model->insert($data)) {
            return $this->error(422, 'Validation failed', null, $this->model->errors());
        }

        $id = (int)$this->model->getInsertID();
        return $this->created(['id' => $id]);
    }

    // PUT /api/v1/services/{id}
    public function update($id = null)
    {
        if (!$id) return $this->error(400, 'Missing id');
        $body = $this->request->getJSON(true) ?? $this->request->getPost();
        if (!$body) return $this->error(400, 'Missing body');

        $data = [
            'name' => trim($body['name'] ?? ''),
            'description' => $body['description'] ?? null,
            'duration_min' => (int)($body['durationMin'] ?? $body['duration_min'] ?? 0),
            'price' => isset($body['price']) && $body['price'] !== '' ? (float)$body['price'] : null,
            'category_id' => isset($body['categoryId']) ? (int)$body['categoryId'] : (isset($body['category_id']) ? (int)$body['category_id'] : null),
            'active' => isset($body['active']) ? (int)!!$body['active'] : 1,
        ];

        if (!$this->model->update((int)$id, $data)) {
            return $this->error(422, 'Validation failed', null, $this->model->errors());
        }

        return $this->ok(['id' => (int)$id]);
    }

    // DELETE /api/v1/services/{id}
    public function delete($id = null)
    {
        if (!$id) return $this->error(400, 'Missing id');
        $this->model->delete((int)$id);
        return $this->ok(['deleted' => true]);
    }
}
