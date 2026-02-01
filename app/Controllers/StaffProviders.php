<?php

/**
 * =============================================================================
 * STAFF PROVIDERS CONTROLLER
 * =============================================================================
 * 
 * @file        app/Controllers/StaffProviders.php
 * @description API controller for managing providers assigned to a staff
 *              member. Inverse of ProviderStaff (staff → provider view).
 * 
 * ROUTES HANDLED:
 * -----------------------------------------------------------------------------
 * GET  /staff-providers/:staffId          : List providers for staff member
 * POST /staff-providers/:staffId/assign   : Assign provider to staff
 * POST /staff-providers/:staffId/remove   : Remove provider assignment
 * 
 * PURPOSE:
 * -----------------------------------------------------------------------------
 * Manages the staff-to-provider relationship (inverse view):
 * - List all providers a staff member works with
 * - View from staff member's perspective
 * - Useful for staff dashboards and availability
 * 
 * RELATIONSHIP STRUCTURE:
 * -----------------------------------------------------------------------------
 * - Staff members can work with multiple providers
 * - Shows which providers the staff member supports
 * - Same xs_provider_staff table, different perspective
 * 
 * ACCESS CONTROL:
 * -----------------------------------------------------------------------------
 * - Admin: Can view/manage any staff's provider list
 * - Staff: Can only view own provider assignments
 * - Receptionist: Can view own provider assignments
 * 
 * RESPONSE FORMAT:
 * -----------------------------------------------------------------------------
 * Returns JSON with provider list including:
 * - id, name, email
 * - specialties/services offered
 * - assignment details
 * 
 * @see         app/Models/ProviderStaffModel.php for data layer
 * @see         app/Controllers/ProviderStaff.php for inverse relationship
 * @package     App\Controllers
 * @extends     BaseController
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

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
        if (!$staff || $staff['role'] !== 'staff') {
            return $this->failUnauthorized('Invalid staff user');
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
