<?php

namespace App\Controllers;

use App\Models\ReceptionistProviderModel;
use App\Models\UserModel;
use App\Models\AuditLogModel;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\RESTful\ResourceController;

class ReceptionistProviders extends ResourceController
{
    protected $receptionistProviderModel;
    protected $userModel;
    protected $auditModel;
    protected $format = 'json';

    public function __construct()
    {
        $this->receptionistProviderModel = new ReceptionistProviderModel();
        $this->userModel = new UserModel();
        $this->auditModel = new AuditLogModel();
    }

    /**
     * List providers assigned to a receptionist
     * Route: GET /receptionist-providers/receptionist/:id
     *
     * @param int $receptionistId
     * @return ResponseInterface
     */
    public function list(int $receptionistId): ResponseInterface
    {
        $currentUserId = session()->get('user_id');
        $currentUser = session()->get('user');

        if (!$currentUserId || !$currentUser) {
            return $this->fail('Unauthorized', 401);
        }

        // Admin can see all, receptionist can only see their own
        if ($currentUser['role'] !== 'admin' && $currentUserId !== $receptionistId) {
            return $this->fail('You can only view your own provider assignments', 403);
        }

        $providers = $this->receptionistProviderModel->getProvidersForReceptionist($receptionistId);

        return $this->respond([
            'success' => true,
            'data' => $providers
        ]);
    }

    /**
     * Assign a receptionist to a provider
     * Route: POST /receptionist-providers/assign
     *
     * @return ResponseInterface
     */
    public function assign(): ResponseInterface
    {
        $currentUserId = session()->get('user_id');
        $currentUser = session()->get('user');

        if (!$currentUserId || !$currentUser) {
            return $this->fail('Unauthorized', 401);
        }

        // Only admins can assign receptionists
        if ($currentUser['role'] !== 'admin') {
            return $this->fail('Only administrators can manage receptionist assignments', 403);
        }

        // Validate CSRF token
        if (!$this->request->is('secure') && !$this->validateCSRF()) {
            return $this->fail('Invalid CSRF token', 403);
        }

        try {
            $data = $this->request->getJSON(true);
        } catch (\Exception $e) {
            $data = $this->request->getPost();
        }

        $receptionistId = (int)($data['receptionist_id'] ?? 0);
        $providerId = (int)($data['provider_id'] ?? 0);

        if (!$receptionistId || !$providerId) {
            return $this->fail('Receptionist ID and Provider ID are required', 400);
        }

        // Verify receptionist exists and has receptionist role
        $receptionist = $this->userModel->find($receptionistId);
        if (!$receptionist || $receptionist['role'] !== 'receptionist') {
            return $this->fail('Invalid receptionist', 400);
        }

        // Verify provider exists and has provider role
        $provider = $this->userModel->find($providerId);
        if (!$provider || $provider['role'] !== 'provider') {
            return $this->fail('Invalid provider', 400);
        }

        // Perform assignment
        $result = $this->receptionistProviderModel->assignReceptionist(
            $receptionistId,
            $providerId,
            $currentUserId
        );

        if ($result === false) {
            return $this->fail('Receptionist is already assigned to this provider', 400);
        }

        // Audit log
        $this->auditModel->log(
            'receptionist_assigned',
            $currentUserId,
            'assignment',
            $receptionistId,
            null,
            ['provider_id' => $providerId, 'receptionist_id' => $receptionistId]
        );

        return $this->respond([
            'success' => true,
            'message' => 'Receptionist assigned to provider successfully'
        ]);
    }

    /**
     * Remove a receptionist from a provider
     * Route: POST /receptionist-providers/remove
     *
     * @return ResponseInterface
     */
    public function remove(): ResponseInterface
    {
        $currentUserId = session()->get('user_id');
        $currentUser = session()->get('user');

        if (!$currentUserId || !$currentUser) {
            return $this->fail('Unauthorized', 401);
        }

        // Only admins can remove assignments
        if ($currentUser['role'] !== 'admin') {
            return $this->fail('Only administrators can remove receptionist assignments', 403);
        }

        // Validate CSRF token
        if (!$this->request->is('secure') && !$this->validateCSRF()) {
            return $this->fail('Invalid CSRF token', 403);
        }

        try {
            $data = $this->request->getJSON(true);
        } catch (\Exception $e) {
            $data = $this->request->getPost();
        }

        $receptionistId = (int)($data['receptionist_id'] ?? 0);
        $providerId = (int)($data['provider_id'] ?? 0);

        if (!$receptionistId || !$providerId) {
            return $this->fail('Receptionist ID and Provider ID are required', 400);
        }

        if ($this->receptionistProviderModel->removeReceptionist($receptionistId, $providerId)) {
            // Audit log
            $this->auditModel->log(
                'receptionist_unassigned',
                $currentUserId,
                'assignment',
                $receptionistId,
                ['provider_id' => $providerId],
                null
            );

            return $this->respond([
                'success' => true,
                'message' => 'Receptionist removed from provider successfully'
            ]);
        }

        return $this->fail('Failed to remove assignment', 500);
    }

    /**
     * Validate CSRF token from request headers
     *
     * @return bool
     */
    private function validateCSRF(): bool
    {
        $csrfToken = $this->request->getHeaderLine('X-CSRF-TOKEN');
        $sessionToken = csrf_hash();

        return $csrfToken && $csrfToken === $sessionToken;
    }
}
