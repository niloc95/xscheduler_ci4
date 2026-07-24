<?php

/**
 * =============================================================================
 * V1 CUSTOMERS API CONTROLLER
 * =============================================================================
 *
 * @file        app/Controllers/Api/V1/Customers.php
 * @description External CRUD for customer records. All routes sit behind
 *              `api_auth` and identify the acting user via current_user_id()
 *              so Bearer-token and session callers audit identically.
 *
 * API ENDPOINTS:
 * -----------------------------------------------------------------------------
 * GET    /api/v1/customers        : List customers (paginated, optional ?q=)
 * GET    /api/v1/customers/:id    : Get one customer
 * POST   /api/v1/customers        : Create a customer   (admin/provider/staff)
 * PUT    /api/v1/customers/:id    : Update a customer   (admin/provider/staff)
 * PATCH  /api/v1/customers/:id    : Update a customer   (admin/provider/staff)
 * DELETE /api/v1/customers/:id    : Delete a customer   (admin/provider/staff)
 *
 * Persistence and auditing live in CustomerService / CustomerDeletionService.
 * Numeric IDs are acceptable here because every route is authenticated — the
 * "no numeric customer IDs" rule applies to PUBLIC URLs only (public-booking).
 *
 * @see         app/Services/CustomerService.php          (insert/update)
 * @see         app/Services/CustomerDeletionService.php   (delete + guards)
 * @see         app/Models/CustomerModel.php               (fields)
 * @package     App\Controllers\Api\V1
 * @extends     BaseApiController
 * =============================================================================
 */

namespace App\Controllers\Api\V1;

use App\Controllers\Api\BaseApiController;
use App\Models\CustomerModel;
use App\Services\CustomerDeletionService;
use App\Services\CustomerService;

class Customers extends BaseApiController
{
    protected CustomerModel $model;
    protected CustomerService $customers;
    protected CustomerDeletionService $deletion;

    public function __construct()
    {
        $this->model     = new CustomerModel();
        $this->customers = new CustomerService();
        $this->deletion  = new CustomerDeletionService();
    }

    // GET /api/v1/customers
    public function index()
    {
        [$page, $length, $offset] = $this->paginationParams();
        [$sortField, $sortDir]    = $this->sortParam(['id', 'first_name', 'last_name', 'email', 'created_at'], 'first_name');

        $builder = $this->model->builder();

        $q = trim((string) ($this->request->getGet('q') ?? ''));
        if ($q !== '') {
            $builder->groupStart()
                ->like('first_name', $q)
                ->orLike('last_name', $q)
                ->orLike('email', $q)
                ->orLike('phone', $q)
                ->groupEnd();
        }

        $total = (clone $builder)->countAllResults(false);

        $rows = $builder->orderBy($sortField, strtoupper($sortDir))
            ->limit($length, $offset)
            ->get()
            ->getResultArray();

        $items = array_map([$this, 'shape'], $rows);

        return $this->ok($items, $this->paginationMeta($page, $length, (int) $total, $sortField . ':' . $sortDir));
    }

    // GET /api/v1/customers/{id}
    public function show($id = null)
    {
        if (!$id) {
            return $this->badRequest('Missing id');
        }

        $row = $this->model->find((int) $id);
        if (!$row) {
            return $this->notFound('Customer not found');
        }

        return $this->ok($this->shape($row));
    }

    // POST /api/v1/customers
    public function create()
    {
        $data = $this->payload();
        if ($data === null) {
            return $this->badRequest('Missing body');
        }

        if ($errors = $this->validatePayload($data)) {
            return $this->validationError($errors);
        }

        $now                 = date('Y-m-d H:i:s');
        $data['created_at']  = $now;
        $data['updated_at']  = $now;

        try {
            $id = $this->customers->insertCustomer($data);
        } catch (\Throwable $e) {
            return $this->unprocessable('Unable to create customer', $e->getMessage());
        }

        return $this->created($this->shape($this->model->find($id)));
    }

    // PUT|PATCH /api/v1/customers/{id}
    public function update($id = null)
    {
        if (!$id) {
            return $this->badRequest('Missing id');
        }
        if (!$this->model->find((int) $id)) {
            return $this->notFound('Customer not found');
        }

        $data = $this->payload();
        if ($data === null) {
            return $this->badRequest('Missing body');
        }

        if ($errors = $this->validatePayload($data)) {
            return $this->validationError($errors);
        }

        $data['updated_at'] = date('Y-m-d H:i:s');

        try {
            $this->customers->updateCustomerById((int) $id, $data);
        } catch (\Throwable $e) {
            return $this->unprocessable('Unable to update customer', $e->getMessage());
        }

        return $this->ok($this->shape($this->model->find((int) $id)));
    }

    // DELETE /api/v1/customers/{id}
    public function delete($id = null)
    {
        if (!$id) {
            return $this->badRequest('Missing id');
        }

        $result = $this->deletion->deleteCustomerByIdentifier((int) current_user_id(), (string) $id);

        if (empty($result['success'])) {
            $status  = (int) ($result['statusCode'] ?? 422);
            $details = isset($result['appointmentCount'])
                ? ['appointment_count' => $result['appointmentCount'], 'block_code' => $result['blockCode'] ?? null]
                : null;

            return $this->error($status, $result['message'] ?? 'Unable to delete customer', $result['blockCode'] ?? null, $details);
        }

        return $this->ok(['deleted' => true, 'id' => (int) ($result['customerId'] ?? $id)]);
    }

    /**
     * Build a customer payload from the request. Accepts either firstName/
     * lastName or a combined name, mirroring the CustomerManagement form path.
     *
     * @return array<string, mixed>|null Null when the body is empty.
     */
    private function payload(): ?array
    {
        $body = $this->request->getJSON(true) ?? $this->request->getPost();
        if (!$body) {
            return null;
        }

        $first = trim((string) ($body['firstName'] ?? $body['first_name'] ?? ''));
        $last  = trim((string) ($body['lastName'] ?? $body['last_name'] ?? ''));

        // Combined-name fallback when discrete name fields are not supplied.
        $name = trim((string) ($body['name'] ?? ''));
        if ($name !== '' && $first === '' && $last === '') {
            $parts = preg_split('/\s+/', $name, 2);
            $first = $parts[0] ?? '';
            $last  = $parts[1] ?? '';
        }

        return [
            'first_name' => $first,
            'last_name'  => $last,
            'email'      => trim((string) ($body['email'] ?? '')),
            'phone'      => $body['phone'] ?? null,
            'address'    => $body['address'] ?? null,
            'notes'      => $body['notes'] ?? null,
        ];
    }

    /**
     * Lightweight required-field / format checks. Returns a field=>message map,
     * or an empty array when the payload is acceptable.
     *
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    private function validatePayload(array $data): array
    {
        $errors = [];

        if (($data['first_name'] ?? '') === '') {
            $errors['firstName'] = 'A first name (or combined name) is required.';
        }

        $email = (string) ($data['email'] ?? '');
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email address is not valid.';
        }

        return $errors;
    }

    /**
     * Stable API representation of a customer row. Never leaks the hash.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function shape(array $row): array
    {
        return [
            'id'        => (int) $row['id'],
            'firstName' => $row['first_name'] ?? '',
            'lastName'  => $row['last_name'] ?? '',
            'email'     => $row['email'] ?? null,
            'phone'     => $row['phone'] ?? null,
            'address'   => $row['address'] ?? null,
            'notes'     => $row['notes'] ?? null,
            'createdAt' => $row['created_at'] ?? null,
        ];
    }
}
