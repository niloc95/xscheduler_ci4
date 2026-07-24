<?php

/**
 * =============================================================================
 * V1 CATEGORIES API CONTROLLER
 * =============================================================================
 *
 * @file        app/Controllers/Api/V1/Categories.php
 * @description External CRUD for service categories. Reads are open to any
 *              authenticated caller; writes require admin/provider and are
 *              gated by the route filter (`api_auth:admin,provider`).
 *
 * API ENDPOINTS:
 * -----------------------------------------------------------------------------
 * GET    /api/v1/categories        : List categories
 * GET    /api/v1/categories/:id    : Get one category
 * POST   /api/v1/categories        : Create a category      (admin/provider)
 * PUT    /api/v1/categories/:id    : Update a category      (admin/provider)
 * PATCH  /api/v1/categories/:id    : Update a category      (admin/provider)
 * DELETE /api/v1/categories/:id    : Delete a category      (admin/provider)
 *
 * All business logic lives in ServiceMutationService — this controller only
 * shapes request/response and maps thrown validation errors to 422.
 *
 * @see         app/Services/ServiceMutationService.php  (create/update/delete)
 * @see         app/Models/CategoryModel.php             (fields + validation)
 * @package     App\Controllers\Api\V1
 * @extends     BaseApiController
 * =============================================================================
 */

namespace App\Controllers\Api\V1;

use App\Controllers\Api\BaseApiController;
use App\Models\CategoryModel;
use App\Services\ServiceMutationService;

class Categories extends BaseApiController
{
    protected CategoryModel $model;
    protected ServiceMutationService $mutation;

    public function __construct()
    {
        $this->model    = new CategoryModel();
        $this->mutation = new ServiceMutationService();
    }

    // GET /api/v1/categories
    public function index()
    {
        [$page, $length, $offset] = $this->paginationParams();
        [$sortField, $sortDir]    = $this->sortParam(['id', 'name', 'active'], 'name');

        $rows  = $this->model->orderBy($sortField, strtoupper($sortDir))->findAll($length, $offset);
        $total = $this->model->builder()->countAllResults();

        $items = array_map([$this, 'shape'], $rows);

        return $this->ok($items, $this->paginationMeta($page, $length, (int) $total, $sortField . ':' . $sortDir));
    }

    // GET /api/v1/categories/{id}
    public function show($id = null)
    {
        if (!$id) {
            return $this->badRequest('Missing id');
        }

        $row = $this->model->find((int) $id);
        if (!$row) {
            return $this->notFound('Category not found');
        }

        return $this->ok($this->shape($row));
    }

    // POST /api/v1/categories
    public function create()
    {
        $data = $this->payload();
        if ($data === null) {
            return $this->badRequest('Missing body');
        }

        try {
            $id = $this->mutation->createCategory($data);
        } catch (\Throwable $e) {
            return $this->validationError($this->model->errors() ?: ['name' => $e->getMessage()]);
        }

        return $this->created($this->shape($this->model->find($id)));
    }

    // PUT|PATCH /api/v1/categories/{id}
    public function update($id = null)
    {
        if (!$id) {
            return $this->badRequest('Missing id');
        }
        if (!$this->model->find((int) $id)) {
            return $this->notFound('Category not found');
        }

        $data = $this->payload();
        if ($data === null) {
            return $this->badRequest('Missing body');
        }

        try {
            $this->mutation->updateCategory((int) $id, $data);
        } catch (\Throwable $e) {
            return $this->validationError($this->model->errors() ?: ['name' => $e->getMessage()]);
        }

        return $this->ok($this->shape($this->model->find((int) $id)));
    }

    // DELETE /api/v1/categories/{id}
    public function delete($id = null)
    {
        if (!$id) {
            return $this->badRequest('Missing id');
        }
        if (!$this->model->find((int) $id)) {
            return $this->notFound('Category not found');
        }

        try {
            $this->mutation->deleteCategory((int) $id);
        } catch (\Throwable $e) {
            return $this->unprocessable('Unable to delete category', $e->getMessage());
        }

        return $this->ok(['deleted' => true]);
    }

    /**
     * Build a category payload from the request, bridging camelCase input to the
     * snake_case model columns (mirrors Api/V1/Services::create()).
     *
     * @return array<string, mixed>|null Null when the body is empty.
     */
    private function payload(): ?array
    {
        $body = $this->request->getJSON(true) ?? $this->request->getPost();
        if (!$body) {
            return null;
        }

        return [
            'name'        => trim((string) ($body['name'] ?? '')),
            'description' => $body['description'] ?? null,
            'color'       => $body['color'] ?? null,
            'active'      => isset($body['active']) ? (int) !!$body['active'] : 1,
        ];
    }

    /**
     * Stable API representation of a category row.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function shape(array $row): array
    {
        return [
            'id'          => (int) $row['id'],
            'name'        => $row['name'] ?? '',
            'description' => $row['description'] ?? null,
            'color'       => $row['color'] ?? null,
            'active'      => isset($row['active']) ? (bool) $row['active'] : true,
        ];
    }
}
