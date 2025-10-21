<?php

namespace App\Controllers;

use App\Models\ProviderStaffModel;
use App\Models\UserModel;
use CodeIgniter\API\ResponseTrait;

class StaffProviders extends BaseController
{
    use ResponseTrait;

    protected ProviderStaffModel $providerStaffModel;
    protected UserModel $userModel;

    public function __construct()
    {
        $this->providerStaffModel = new ProviderStaffModel();
        $this->userModel = new UserModel();
    }

    public function list(int $staffId)
    {
        $currentUserId = (int) session()->get('user_id');
        $currentUser = session()->get('user');

        if (!$currentUserId || !$currentUser) {
            return $this->failUnauthorized('Authentication required.');
        }

        $staff = $this->userModel->find($staffId);
        if (!$staff || !in_array($staff['role'], ['staff', 'receptionist'], true)) {
            return $this->failNotFound('Staff member not found.');
        }

        // Admins can view any staff, staff only their own providers
        if ($currentUser['role'] !== 'admin' && $currentUserId !== $staffId) {
            return $this->failForbidden('You do not have permission to view this staff member.');
        }

        $providers = $this->providerStaffModel->getProvidersForStaff($staffId);
        $token = csrf_hash();
        $this->response->setHeader('X-CSRF-TOKEN', $token);

        return $this->respond([
            'status'     => 'ok',
            'providers'  => $providers,
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

        if ($currentUser['role'] !== 'admin') {
            return $this->failForbidden('Only administrators can assign providers.');
        }

        $staffId    = (int) $this->request->getPost('staff_id');
        $providerId = (int) $this->request->getPost('provider_id');

        if ($staffId <= 0 || $providerId <= 0) {
            return $this->failValidationErrors('Staff and provider are required.');
        }

        $staff = $this->userModel->find($staffId);
        if (!$staff || !in_array($staff['role'], ['staff', 'receptionist'], true)) {
            return $this->failValidationErrors('Only staff or receptionists can be assigned.');
        }

        $provider = $this->userModel->find($providerId);
        if (!$provider || $provider['role'] !== 'provider') {
            return $this->failValidationErrors('Invalid provider selection.');
        }

        if ($this->providerStaffModel->isStaffAssignedToProvider($staffId, $providerId)) {
            return $this->failResourceExists('Provider is already assigned to this staff member.');
        }

        try {
            // Pass current user ID for audit trail
            $this->providerStaffModel->assignStaff($providerId, $staffId, $currentUserId);
        } catch (\Throwable $e) {
            log_message('error', 'StaffProviders::assign exception: ' . $e->getMessage());
            return $this->failServerError('Failed to assign provider: ' . $e->getMessage());
        }

        $providerList = $this->providerStaffModel->getProvidersForStaff($staffId);
        $token = csrf_hash();
        $this->response->setHeader('X-CSRF-TOKEN', $token);

        return $this->respondCreated([
            'message' => 'Provider assigned successfully.',
            'providers' => $providerList,
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

        if ($currentUser['role'] !== 'admin') {
            return $this->failForbidden('Only administrators can remove provider assignments.');
        }

        $staffId    = (int) $this->request->getPost('staff_id');
        $providerId = (int) $this->request->getPost('provider_id');

        if ($staffId <= 0 || $providerId <= 0) {
            return $this->failValidationErrors('Staff and provider are required.');
        }

        if (!$this->providerStaffModel->isStaffAssignedToProvider($staffId, $providerId)) {
            return $this->failNotFound('Assignment not found.');
        }

        if (!$this->providerStaffModel->removeStaff($providerId, $staffId)) {
            return $this->failServerError('Failed to remove assignment.');
        }

        $providerList = $this->providerStaffModel->getProvidersForStaff($staffId);
        $token = csrf_hash();
        $this->response->setHeader('X-CSRF-TOKEN', $token);

        return $this->respond([
            'message'    => 'Provider removed successfully.',
            'providers'  => $providerList,
            'csrfToken'  => $token,
        ]);
    }
}
