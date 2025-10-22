<?php

namespace App\Controllers;

use App\Models\ProviderStaffModel;
use App\Models\UserModel;
use CodeIgniter\API\ResponseTrait;

class ProviderStaff extends BaseController
{
    use ResponseTrait;

    protected ProviderStaffModel $providerStaffModel;
    protected UserModel $userModel;

    public function __construct()
    {
        $this->providerStaffModel = new ProviderStaffModel();
        $this->userModel = new UserModel();
    }

    public function list(int $providerId)
    {
        $currentUserId = (int) session()->get('user_id');
        $currentUser = session()->get('user');

        if (!$currentUserId || !$currentUser) {
            return $this->failUnauthorized('Authentication required.');
        }

        $provider = $this->userModel->find($providerId);
        if (!$provider || $provider['role'] !== 'provider') {
            return $this->failNotFound('Provider not found.');
        }

        // Admins can view any provider, providers only their own staff
        if ($currentUser['role'] !== 'admin' && $currentUserId !== $providerId) {
            return $this->failForbidden('You do not have permission to view this provider.');
        }

        $staff = $this->providerStaffModel->getStaffByProvider($providerId);
        $token = csrf_hash();
        $this->response->setHeader('X-CSRF-TOKEN', $token);

        return $this->respond([
            'success'   => true,
            'status'     => 'ok',
            'staff'      => $staff,
            'csrfToken'  => $token,
        ]);
    }

    public function assign()
    {
        $currentUserId = (int) session()->get('user_id');
        $currentUser = session()->get('user');

        if (!$currentUserId || !$currentUser) {
            return $this->failUnauthorized('Authentication required.');
        }

        // Accept both JSON and FormData
        $jsonPayload = null;
        try {
            $jsonPayload = $this->request->getJSON(true);
        } catch (\Exception $e) {
            // Not JSON, will try POST data instead
        }
        
        $providerId = (int) ($jsonPayload['provider_id'] ?? $this->request->getPost('provider_id'));
        $staffId    = (int) ($jsonPayload['staff_id'] ?? $this->request->getPost('staff_id'));

        if ($providerId <= 0 || $staffId <= 0) {
            return $this->failValidationErrors('Provider and staff are required.');
        }

        $provider = $this->userModel->find($providerId);
        if (!$provider || $provider['role'] !== 'provider') {
            return $this->failValidationErrors('Invalid provider selection.');
        }

        $staff = $this->userModel->find($staffId);
        if (!$staff || $staff['role'] !== 'staff') {
            return $this->failValidationErrors('Only staff can be assigned.');
        }

        $role = $currentUser['role'] ?? '';
        if ($role === 'admin') {
            // permitted
        } elseif ($role === 'provider' && $currentUserId === $providerId) {
            // providers may manage their own staff
        } else {
            return $this->failForbidden('You do not have permission to assign staff to this provider.');
        }

        if ($this->providerStaffModel->isStaffAssignedToProvider($staffId, $providerId)) {
            return $this->failResourceExists('Staff member is already assigned to this provider.');
        }

        try {
            // Pass current user ID for audit trail
            $this->providerStaffModel->assignStaff($providerId, $staffId, $currentUserId);
        } catch (\Throwable $e) {
            log_message('error', 'ProviderStaff::assign exception: ' . $e->getMessage());
            return $this->failServerError('Failed to assign staff: ' . $e->getMessage());
        }

        $staffList = $this->providerStaffModel->getStaffByProvider($providerId);
        $token = csrf_hash();
        $this->response->setHeader('X-CSRF-TOKEN', $token);

        return $this->respondCreated([
            'success' => true,
            'message' => 'Staff assigned successfully.',
            'staff'   => $staffList,
            'csrfToken' => $token,
        ]);
    }

    public function remove()
    {
        $currentUserId = (int) session()->get('user_id');
        $currentUser = session()->get('user');

        if (!$currentUserId || !$currentUser) {
            return $this->failUnauthorized('Authentication required.');
        }

        // Accept both JSON and FormData
        $jsonPayload = null;
        try {
            $jsonPayload = $this->request->getJSON(true);
        } catch (\Exception $e) {
            // Not JSON, will try POST data instead
        }
        
        $providerId = (int) ($jsonPayload['provider_id'] ?? $this->request->getPost('provider_id'));
        $staffId    = (int) ($jsonPayload['staff_id'] ?? $this->request->getPost('staff_id'));

        if ($providerId <= 0 || $staffId <= 0) {
            return $this->failValidationErrors('Provider and staff are required.');
        }

        $role = $currentUser['role'] ?? '';
        if ($role === 'admin') {
            // permitted
        } elseif ($role === 'provider' && $currentUserId === $providerId) {
            // providers may manage their own staff relationships
        } else {
            return $this->failForbidden('You do not have permission to remove staff from this provider.');
        }

        if (!$this->providerStaffModel->isStaffAssignedToProvider($staffId, $providerId)) {
            return $this->failNotFound('Assignment not found.');
        }

        if (!$this->providerStaffModel->removeStaff($providerId, $staffId)) {
            return $this->failServerError('Failed to remove assignment.');
        }

        $staffList = $this->providerStaffModel->getStaffByProvider($providerId);
        $token = csrf_hash();
        $this->response->setHeader('X-CSRF-TOKEN', $token);

        return $this->respond([
            'success'   => true,
            'message'   => 'Staff removed successfully.',
            'staff'     => $staffList,
            'csrfToken' => $token,
        ]);
    }
}
