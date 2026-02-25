<?php

/**
 * =============================================================================
 * USER MANAGEMENT CONTROLLER
 * =============================================================================
 * 
 * @file        app/Controllers/UserManagement.php
 * @description Comprehensive user administration including creating, editing,
 *              and managing users, their roles, schedules, and staff assignments.
 * 
 * ROUTES HANDLED:
 * -----------------------------------------------------------------------------
 * GET  /user-management              : List all users
 * GET  /user-management/create       : Show user creation form
 * POST /user-management/store        : Create new user
 * GET  /user-management/edit/:id     : Show edit form for user
 * POST /user-management/update/:id   : Update existing user
 * POST /user-management/delete/:id   : Soft delete user
 * GET  /user-management/schedule/:id : View/edit provider schedule
 * POST /user-management/schedule/:id : Save provider schedule
 * GET  /user-management/staff/:id    : Manage provider's staff assignments
 * 
 * USER ROLES:
 * -----------------------------------------------------------------------------
 * - admin    : Full system access, can manage all users
 * - provider : Service provider, has own schedule and can have staff
 * - staff    : Assigned to provider(s), limited access
 * - customer : End user, books appointments (managed separately)
 * 
 * PURPOSE:
 * -----------------------------------------------------------------------------
 * Central administration for system users:
 * - User CRUD with role assignment
 * - Provider working hours and availability
 * - Staff-to-provider assignments
 * - Permission management
 * - Audit logging of changes
 * 
 * PROVIDER SCHEDULES:
 * -----------------------------------------------------------------------------
 * Providers have configurable weekly schedules:
 * - Working days (Mon-Sun)
 * - Start and end times per day
 * - Break periods
 * - Schedule validation against appointments
 * 
 * ACCESS CONTROL:
 * -----------------------------------------------------------------------------
 * - Admin: Can manage all users and roles
 * - Provider: Can manage own staff assignments only
 * 
 * @see         app/Views/user-management/ for view templates
 * @see         app/Models/UserModel.php for user data
 * @see         app/Models/ProviderScheduleModel.php for schedules
 * @package     App\Controllers
 * @extends     BaseController
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

namespace App\Controllers;

use App\Models\LocationModel;
use App\Models\ProviderScheduleModel;
use App\Models\ProviderStaffModel;
use App\Models\UserModel;
use App\Models\UserPermissionModel;
use App\Models\AuditLogModel;
use App\Services\LocalizationSettingsService;
use App\Services\ScheduleValidationService;

class UserManagement extends BaseController
{
    protected $userModel;
    protected $permissionModel;
    protected $auditModel;
    
    protected $providerScheduleModel;
    protected ProviderStaffModel $providerStaffModel;
    protected LocalizationSettingsService $localization;
    protected ScheduleValidationService $scheduleValidation;
    protected array $scheduleDays = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];

    public function __construct()
    {
        helper('user');
        $this->userModel = new UserModel();
        $this->permissionModel = new UserPermissionModel();
        $this->auditModel = new AuditLogModel();
        $this->providerScheduleModel = new ProviderScheduleModel();
        $this->providerStaffModel = new ProviderStaffModel();
        $this->localization = new LocalizationSettingsService();
        $this->scheduleValidation = new ScheduleValidationService($this->localization);
    }

    /**
     * Display user management dashboard
     */
    public function index()
    {
        $currentUserId = session()->get('user_id');
        $currentUser = session()->get('user');

        if (!$currentUserId || !$currentUser) {
            return redirect()->to(base_url('auth/login'));
        }

        // Get users based on current user's role and permissions
        $users = [];
        $stats = ['total' => 0, 'admins' => 0, 'providers' => 0, 'staff' => 0, 'recent' => 0];
        try {
            $users = $this->getUsersBasedOnRole($currentUserId);
            $stats = $this->getUserStatsBasedOnRole($currentUserId, $users);
        } catch (\Throwable $e) {
            log_message('warning', 'UserManagement::index failed to load users: ' . $e->getMessage());
        }

        $data = [
            'title' => 'User Management - WebSchedulr',
            'currentUser' => $currentUser,
            'users' => $users,
            'stats' => $stats,
            'canCreateAdmin' => $this->permissionModel->hasPermission($currentUserId, 'create_admin'),
            'canCreateProvider' => $this->permissionModel->hasPermission($currentUserId, 'create_provider'),
            'canCreateStaff' => $this->permissionModel->hasPermission($currentUserId, 'create_staff'),
        ];

        return view('user-management/index', $data);
    }

    /**
     * Show create user form
     */
    public function create()
    {
        $currentUserId = session()->get('user_id');
        $currentUser = session()->get('user');

        if (!$currentUserId || !$currentUser) {
            return redirect()->to(base_url('auth/login'));
        }

        // Determine which roles the current user can create
    $availableRoles = $this->getAvailableRolesForUser($currentUserId);
        
        if (empty($availableRoles)) {
            return redirect()->to(base_url('user-management'))
                           ->with('error', 'You do not have permission to create users.');
        }

        // Get providers for staff assignment
        $providers = [];
        if (in_array('staff', $availableRoles, true)) {
            $providers = $this->userModel->getProviders();
        }

        // Get available staff for provider assignment
        $availableStaff = [];
        if (in_array('provider', $availableRoles, true)) {
            $staffModel = new UserModel();
            $availableStaff = $staffModel->where('role', 'staff')
                ->where('is_active', true)
                ->orderBy('name', 'ASC')
                ->findAll();
        }

        // Get stats for the help panel
        $stats = $this->getUserStatsBasedOnRole($currentUserId);

        $data = [
            'title' => 'Create User - WebSchedulr',
            'currentUser' => $currentUser,
            'availableRoles' => $availableRoles,
            'providers' => $providers,
            'availableStaff' => $availableStaff,
            'assignedStaff' => [],
            'assignedProviders' => [],
            'canManageAssignments' => ($currentUser['role'] ?? '') === 'admin',
            'stats' => $stats,
            'validation' => $this->validator,
            'providerSchedule' => $this->scheduleValidation->prepareScheduleForView(old('schedule') ?? []),
            'scheduleDays' => $this->scheduleDays,
            'scheduleErrors' => session()->getFlashdata('schedule_errors') ?? [],
            'localizationContext' => $this->localization->getContext(),
            'timeFormatExample' => $this->localization->getFormatExample(),
        ];

        return view('user-management/create', $data);
    }

    /**
     * Process create user form
     */
    public function store()
    {
        log_message('info', '========== STORE METHOD CALLED ==========');
        
        $currentUserId = session()->get('user_id');
        $currentUser = session()->get('user');

        if (!$currentUserId || !$currentUser) {
            log_message('warning', 'No session in store - redirecting to login');
            return redirect()->to(base_url('auth/login'));
        }

        $rules = $this->getStoreValidationRules();

        if (!$this->validate($rules)) {
            log_message('warning', 'Validation failed: ' . json_encode($this->validator->getErrors()));
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(422)->setJSON([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $this->validator->getErrors()
                ]);
            }
            return redirect()->back()->withInput()->with('validation', $this->validator);
        }

        $role = $this->request->getPost('role');
        
        log_message('info', 'Creating user with role: ' . $role . ' by user: ' . $currentUserId . ' (role: ' . $currentUser['role'] . ')');
        
        // Check if current user can create this role
        if (!$this->canCreateRole($currentUserId, $role)) {
            log_message('error', 'Permission denied: User ' . $currentUserId . ' cannot create role: ' . $role);
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(403)->setJSON([
                    'success' => false,
                    'message' => 'You do not have permission to create users with this role.'
                ]);
            }
            return redirect()->back()
                           ->with('error', 'You do not have permission to create users with this role.');
        }

        $userData = [
            'name' => $this->request->getPost('name'),
            'email' => $this->request->getPost('email'),
            'phone' => $this->request->getPost('phone'),
            'role' => $role,
            'password' => $this->request->getPost('password'),
            'is_active' => true,
        ];

        $scheduleInput = $this->request->getPost('schedule') ?? [];
        $scheduleClean = [];
        if ($role === 'provider') {
            // Auto-assign color for provider
            $userData['color'] = $this->request->getPost('color') ?: $this->userModel->getAvailableProviderColor();
            log_message('info', 'Assigned color ' . $userData['color'] . ' to new provider');
            
            [$scheduleClean, $scheduleErrors] = $this->scheduleValidation->validateProviderSchedule($scheduleInput);
            if (!empty($scheduleErrors)) {
                if ($this->request->isAJAX()) {
                    return $this->response->setStatusCode(422)->setJSON([
                        'success' => false,
                        'message' => 'Please fix the highlighted schedule issues.',
                        'errors' => $scheduleErrors
                    ]);
                }
                return redirect()->back()->withInput()
                    ->with('error', 'Please fix the highlighted schedule issues.')
                    ->with('schedule_errors', $scheduleErrors);
            }
        }

        $userId = $this->userModel->createUser($userData);

        log_message('info', 'User creation returned: ' . var_export($userId, true) . ' (type: ' . gettype($userId) . ')');

        if ($userId) {
            log_message('info', 'User created with ID: ' . $userId);
            
            // Audit log for user creation
            $this->auditModel->log(
                'user_created',
                $currentUserId,
                'user',
                $userId,
                null,
                ['role' => $role, 'email' => $userData['email']]
            );
            
            // Auto-assignment only when provider creates staff (NOT when admin creates staff)
            if ($currentUser['role'] === 'provider' && $role === 'staff') {
                log_message('info', 'Auto-assigning staff ' . $userId . ' to provider ' . $currentUserId);
                $assigned = $this->providerStaffModel->assignStaff((int) $currentUserId, (int) $userId, (int) $currentUserId, 'active');

                if (!$assigned) {
                    log_message('error', '[UserManagement::store] Staff created but failed to auto-assign to provider. provider_id=' . $currentUserId . ' staff_id=' . $userId . ' errors=' . json_encode($this->providerStaffModel->errors()));
                    if ($this->request->isAJAX()) {
                        return $this->response->setStatusCode(400)->setJSON([
                            'success' => false,
                            'message' => 'User created, but assignment to provider failed. Please contact support or try assigning again.',
                            'userId' => $userId
                        ]);
                    }
                    return redirect()->to(base_url('user-management/edit/' . $userId))
                        ->with('error', 'User created, but assignment to provider failed. Please contact support or try assigning again.');
                }
                
                // Audit log for auto-assignment
                $this->auditModel->log(
                    'staff_assigned',
                    $currentUserId,
                    'assignment',
                    $userId,
                    null,
                    ['provider_id' => $currentUserId, 'staff_id' => $userId]
                );
            }
            
            if ($role === 'provider' && !empty($scheduleClean)) {
                $this->providerScheduleModel->saveSchedule($userId, $scheduleClean);
            }

            if ($this->request->isAJAX()) {
                return $this->response->setJSON([
                    'success' => true,
                    'message' => 'User created successfully. You can now manage assignments and schedules.',
                    'redirect' => base_url('user-management/edit/' . $userId),
                    'userId' => $userId
                ]);
            }
            return redirect()->to(base_url('user-management/edit/' . $userId))
                           ->with('success', 'User created successfully. You can now manage assignments and schedules.');
        } else {
            log_message('error', '[UserManagement::store] Failed to create user');
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(400)->setJSON([
                    'success' => false,
                    'message' => 'Failed to create user. Please try again.'
                ]);
            }
            return redirect()->back()
                           ->with('error', 'Failed to create user. Please try again.');
        }
    }

    /**
     * Show edit user form
     */
    public function edit(int $userId)
    {
        $currentUserId = session()->get('user_id');
        $currentUser = session()->get('user');

        if (!$currentUserId || !$currentUser) {
            return redirect()->to(base_url('auth/login'));
        }

        $user = $this->userModel->find($userId);
        
        if (!$user) {
            return redirect()->to(base_url('user-management'))
                           ->with('error', 'User not found.');
        }

        // Check permission to edit this user
        $canManage = $this->userModel->canManageUser($currentUserId, $userId);
        
        if (!$canManage) {
            return redirect()->to(base_url('user-management'))
                           ->with('error', 'You do not have permission to edit this user.');
        }

        // Get available roles for this user
        $availableRoles = $this->getAvailableRolesForUser($currentUserId, $user);
        
        // Get providers for staff assignment
        $providers = [];
        if (in_array('staff', $availableRoles) || $user['role'] === 'staff') {
            $providers = $this->userModel->getProviders();
        }

        $existingSchedule = $this->providerScheduleModel->getByProvider($user['id']);
        $rawSchedule = old('schedule') ?: $existingSchedule;

        $assignedStaff = [];
        $availableStaff = [];
        $assignedProviders = [];
        $availableProviders = [];
        $canManageAssignments = ($currentUser['role'] ?? '') === 'admin';

        if (($user['role'] ?? '') === 'provider'
            && ($currentUser['role'] ?? '') === 'provider'
            && (int) $currentUserId === (int) $userId) {
            $canManageAssignments = true;
        }

        if (($user['role'] ?? '') === 'provider') {
            $assignedStaff = $this->providerStaffModel->getStaffByProvider($user['id']);

            if ($canManageAssignments) {
                $availableStaff = $this->userModel
                    ->where('role', 'staff')
                    ->where('is_active', true)
                    ->orderBy('name', 'ASC')
                    ->findAll();
            }
        } elseif ($user['role'] === 'staff') {
            $assignedProviders = $this->providerStaffModel->getProvidersForStaff($user['id']);

            if ($canManageAssignments) {
                $availableProviders = $this->userModel
                    ->where('role', 'provider')
                    ->where('is_active', true)
                    ->orderBy('name', 'ASC')
                    ->findAll();
            }
        }

        // Load provider locations if user is a provider
        $providerLocations = [];
        if (($user['role'] ?? '') === 'provider') {
            $locationModel = new LocationModel();
            $providerLocations = $locationModel->getProviderLocationsWithDays($user['id']);
        }

        $data = [
            'title' => 'Edit User - WebSchedulr',
            'currentUser' => $currentUser,
            'user' => $user,
            'userId' => $userId,
            'providerId' => $userId, // For provider_staff component
            'staffId' => $userId, // For staff_providers component
            'availableRoles' => $availableRoles,
            'providers' => $providers,
            'validation' => $this->validator,
            'providerSchedule' => $this->scheduleValidation->prepareScheduleForView($rawSchedule),
            'scheduleDays' => $this->scheduleDays,
            'scheduleErrors' => session()->getFlashdata('schedule_errors') ?? [],
            'assignedStaff' => $assignedStaff,
            'availableStaff' => $availableStaff,
            'assignedProviders' => $assignedProviders,
            'availableProviders' => $availableProviders,
            'canManageAssignments' => $canManageAssignments,
            'providerLocations' => $providerLocations,
            'localizationContext' => $this->localization->getContext(),
            'timeFormatExample' => $this->localization->getFormatExample(),
        ];

        return view('user-management/edit', $data);
    }

    /**
     * Process edit user form
     */
    public function update(int $userId)
    {
        $currentUserId = session()->get('user_id');
        $currentUser = session()->get('user');

        if (!$currentUserId || !$currentUser) {
            return redirect()->to(base_url('auth/login'));
        }

        $user = $this->userModel->find($userId);
        if (!$user) {
            return redirect()->to(base_url('user-management'))
                           ->with('error', 'User not found.');
        }

        // Check permission to edit this user
        if (!$this->userModel->canManageUser($currentUserId, $userId)) {
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(403)->setJSON([
                    'success' => false,
                    'message' => 'You do not have permission to edit this user.'
                ]);
            }
            return redirect()->to(base_url('user-management'))
                           ->with('error', 'You do not have permission to edit this user.');
        }

        $rules = $this->getUpdateValidationRules(
            $userId,
            !empty($this->request->getPost('password')),
            $this->canChangeUserRole($currentUserId, $userId)
        );

        if (!$this->validate($rules)) {
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(422)->setJSON([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $this->validator->getErrors()
                ]);
            }
            return redirect()->back()->withInput()->with('validation', $this->validator);
        }

        $updateData = [
            'name' => $this->request->getPost('name'),
            'email' => $this->request->getPost('email'),
            'phone' => $this->request->getPost('phone'),
        ];

        // Handle is_active checkbox (checkboxes don't send value when unchecked)
        // Convert to integer for MySQL BOOLEAN/TINYINT compatibility
        $updateData['is_active'] = $this->request->getPost('is_active') ? 1 : 0;

        // Add password if provided
        if ($this->request->getPost('password')) {
            $updateData['password'] = $this->request->getPost('password');
        }

        // Add role if user can change it - SECURITY: Prevent role escalation by non-admins
        if ($currentUser['role'] === 'admin' && $this->canChangeUserRole($currentUserId, $userId)) {
            $newRole = $this->request->getPost('role');
            if ($newRole) {
                // Check permission for new role if it's different
                if ($newRole !== $user['role'] && !$this->canCreateRole($currentUserId, $newRole)) {
                    return redirect()->back()
                                   ->with('error', 'You do not have permission to assign this role.');
                }
                $updateData['role'] = $newRole;
            }
        } elseif ($currentUser['role'] !== 'admin' && $this->request->getPost('role')) {
            // Non-admin attempted to change role - log and ignore
            log_message('warning', "[UserManagement::update] Non-admin user {$currentUserId} attempted to change role for user {$userId}");
        }

        // Provider assignments now handled via staff_providers component and pivot table
        $finalRole = $updateData['role'] ?? $user['role'];

        $scheduleInput = $this->request->getPost('schedule') ?? [];
        $scheduleClean = [];
        if ($finalRole === 'provider') {
            // Handle provider color update (admin only)
            if ($currentUser['role'] === 'admin' && $this->request->getPost('color')) {
                $updateData['color'] = $this->request->getPost('color');
            }
            
            [$scheduleClean, $scheduleErrors] = $this->scheduleValidation->validateProviderSchedule($scheduleInput);
            if (!empty($scheduleErrors)) {
                if ($this->request->isAJAX()) {
                    return $this->response->setStatusCode(422)->setJSON([
                        'success' => false,
                        'message' => 'Please fix the highlighted schedule issues.',
                        'errors' => $scheduleErrors
                    ]);
                }
                return redirect()->back()->withInput()
                    ->with('error', 'Please fix the highlighted schedule issues.')
                    ->with('schedule_errors', $scheduleErrors);
            }
        }

        if ($this->userModel->updateUser($userId, $updateData, $currentUserId)) {
            // Audit logging for critical changes
            $changedFields = array_keys($updateData);
            
            // Log role change specifically
            if (isset($updateData['role']) && $updateData['role'] !== $user['role']) {
                $this->auditModel->log(
                    'role_changed',
                    $currentUserId,
                    'user',
                    $userId,
                    ['role' => $user['role']],
                    ['role' => $updateData['role']]
                );
            }
            
            // Log password reset
            if (isset($updateData['password'])) {
                $this->auditModel->log(
                    'password_reset',
                    $currentUserId,
                    'user',
                    $userId
                );
            }
            
            // Log general user update
            $this->auditModel->log(
                'user_updated',
                $currentUserId,
                'user',
                $userId,
                null,
                ['fields' => $changedFields]
            );
            
            // Update session if user updated themselves
            if ($currentUserId === $userId) {
                $updatedUser = $this->userModel->find($userId);
                session()->set('user', [
                    'name' => $updatedUser['name'],
                    'email' => $updatedUser['email'],
                    'role' => $updatedUser['role']
                ]);
            }

            if ($finalRole === 'provider') {
                $this->providerScheduleModel->saveSchedule($userId, $scheduleClean);

                // Sync location‑day assignments from schedule tick boxes
                $this->syncLocationDaysFromSchedule($userId, $scheduleInput);
            } else {
                $this->providerScheduleModel->deleteByProvider($userId);
            }
            
            if ($this->request->isAJAX()) {
                return $this->response->setJSON([
                    'success' => true,
                    'message' => 'User updated successfully.',
                    'redirect' => base_url('user-management/edit/' . $userId)
                ]);
            }
            return redirect()->to(base_url('user-management/edit/' . $userId))
                           ->with('success', 'User updated successfully.');
        } else {
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(400)->setJSON([
                    'success' => false,
                    'message' => 'Failed to update user. Please try again.'
                ]);
            }
            return redirect()->back()
                           ->with('error', 'Failed to update user. Please try again.');
        }
    }

    /**
     * Deactivate user
     */
    public function deactivate(int $userId)
    {
        $currentUserId = session()->get('user_id');

        if (!$currentUserId) {
            return redirect()->to(base_url('auth/login'));
        }

        if ($currentUserId === $userId) {
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(400)->setJSON([
                    'success' => false,
                    'message' => 'You cannot deactivate your own account.'
                ]);
            }
            return redirect()->to(base_url('user-management'))
                           ->with('error', 'You cannot deactivate your own account.');
        }

        if ($this->userModel->deactivateUser($userId, $currentUserId)) {
            if ($this->request->isAJAX()) {
                return $this->response->setJSON([
                    'success' => true,
                    'message' => 'User deactivated successfully.',
                    'redirect' => base_url('user-management')
                ]);
            }
            return redirect()->to(base_url('user-management'))
                           ->with('success', 'User deactivated successfully.');
        } else {
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(400)->setJSON([
                    'success' => false,
                    'message' => 'Failed to deactivate user or insufficient permissions.'
                ]);
            }
            return redirect()->to(base_url('user-management'))
                           ->with('error', 'Failed to deactivate user or insufficient permissions.');
        }
    }

    /**
     * Activate user
     */
    public function activate(int $userId)
    {
        $currentUserId = session()->get('user_id');

        if (!$currentUserId) {
            return redirect()->to(base_url('auth/login'));
        }

        if ($this->userModel->update($userId, ['is_active' => true])) {
            if ($this->request->isAJAX()) {
                return $this->response->setJSON([
                    'success' => true,
                    'message' => 'User activated successfully.',
                    'redirect' => base_url('user-management')
                ]);
            }
            return redirect()->to(base_url('user-management'))
                           ->with('success', 'User activated successfully.');
        } else {
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(400)->setJSON([
                    'success' => false,
                    'message' => 'Failed to activate user.'
                ]);
            }
            return redirect()->to(base_url('user-management'))
                           ->with('error', 'Failed to activate user.');
        }
    }

    /**
     * Delete user (hard delete, admin only)
     */
    public function delete(int $userId)
    {
        $currentUserId = session()->get('user_id');
        $currentUser = session()->get('user');

        if (!$currentUserId || !$currentUser) {
            return redirect()->to(base_url('auth/login'));
        }

        // Only admins can delete users
        if ($currentUser['role'] !== 'admin') {
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(403)->setJSON([
                    'success' => false,
                    'message' => 'Only administrators can delete users.'
                ]);
            }
            return redirect()->to(base_url('user-management'))
                           ->with('error', 'Only administrators can delete users.');
        }

        // Cannot delete yourself
        if ($currentUserId === $userId) {
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(400)->setJSON([
                    'success' => false,
                    'message' => 'You cannot delete your own account.'
                ]);
            }
            return redirect()->to(base_url('user-management'))
                           ->with('error', 'You cannot delete your own account.');
        }

        $targetUser = $this->userModel->find($userId);
        if (!$targetUser) {
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(404)->setJSON([
                    'success' => false,
                    'message' => 'User not found.'
                ]);
            }
            return redirect()->to(base_url('user-management'))
                           ->with('error', 'User not found.');
        }

        // Perform the delete
        if ($this->userModel->delete($userId)) {
            if ($this->request->isAJAX()) {
                return $this->response->setJSON([
                    'success' => true,
                    'message' => 'User "' . esc($targetUser['name']) . '" deleted successfully.',
                    'redirect' => base_url('user-management')
                ]);
            }
            return redirect()->to(base_url('user-management'))
                           ->with('success', 'User "' . esc($targetUser['name']) . '" deleted successfully.');
        } else {
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(500)->setJSON([
                    'success' => false,
                    'message' => 'Failed to delete user. Please try again.'
                ]);
            }
            return redirect()->to(base_url('user-management'))
                           ->with('error', 'Failed to delete user. Please try again.');
        }
    }

    // =========================================================================
    // API Endpoints for AJAX calls
    // =========================================================================

    /**
     * API: Get user counts by role
     * GET /api/user-counts
     * 
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function apiCounts()
    {
        $currentUserId = session()->get('user_id');
        if (!$currentUserId) {
            return $this->response->setStatusCode(401)->setJSON(['error' => 'Unauthorized']);
        }

        $stats = ['total' => 0, 'admins' => 0, 'providers' => 0, 'staff' => 0];
        try {
            $stats = $this->getUserStatsBasedOnRole($currentUserId);
        } catch (\Throwable $e) {
            log_message('warning', 'UserManagement::apiCounts failed: ' . $e->getMessage());
        }
        
        return $this->response->setJSON([
            'counts' => [
                'total' => (int)($stats['total'] ?? 0),
                'admins' => (int)($stats['admins'] ?? 0),
                'providers' => (int)($stats['providers'] ?? 0),
                'staff' => (int)($stats['staff'] ?? 0),
            ]
        ]);
    }

    /**
     * API: Get users list with optional role filter
     * GET /api/users?role=admin|provider|staff
     * 
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function apiList()
    {
        $currentUserId = session()->get('user_id');
        if (!$currentUserId) {
            return $this->response->setStatusCode(401)->setJSON(['error' => 'Unauthorized']);
        }

        $role = $this->request->getGet('role');
        
        // Get users based on role permission
        try {
            $users = $this->getUsersBasedOnRole($currentUserId);
        } catch (\Throwable $e) {
            log_message('warning', 'UserManagement::apiList failed to load users: ' . $e->getMessage());
            return $this->response->setJSON(['items' => [], 'total' => 0]);
        }
        
        // Filter by role if specified
        if ($role && in_array($role, ['admin', 'provider', 'staff'])) {
            $users = array_filter($users, fn($u) => ($u['role'] ?? '') === $role);
            $users = array_values($users); // Re-index array
        }
        
        // Enrich with assignments
        $users = $this->enrichUsersWithAssignments($users);
        
        return $this->response->setJSON([
            'items' => $users,
            'total' => count($users)
        ]);
    }

    // Helper methods

    private function getUsersBasedOnRole(int $currentUserId): array
    {
        $currentUser = $this->userModel->find($currentUserId);
        if (!$currentUser) {
            return [];
        }

        $users = [];
        switch ($currentUser['role']) {
            case 'admin':
                $users = $this->userModel
                    ->whereIn('role', ['admin', 'provider', 'staff'])
                    ->findAll();
                break;
                
            case 'provider':
                $staff = $this->providerStaffModel->getStaffByProvider($currentUserId);
                $users = array_merge([$currentUser], $staff);
                break;
                    
            case 'staff':
                // Staff and customers can only see themselves
                $users = [$currentUser];
                break;
                
            default:
                return [];
        }

        // Enrich users with assignment information
        return $this->enrichUsersWithAssignments($users);
    }

    /**
     * Enrich user array with assignment relationships.
     *
     * Uses database-agnostic GROUP_CONCAT syntax so the query works on
     * both MySQL/MariaDB and SQLite (production uses SQLite).
     */
    private function enrichUsersWithAssignments(array $users): array
    {
        if (empty($users)) {
            return $users;
        }

        $db = $this->userModel->db;
        $driver = $db->DBDriver;
        $isSQLite = stripos($driver, 'sqlite') !== false;

        // Group users by role for batch queries
        $providerIds = [];
        $staffIds = [];
        
        foreach ($users as $user) {
            if ($user['role'] === 'provider') {
                $providerIds[] = $user['id'];
            } elseif ($user['role'] === 'staff') {
                $staffIds[] = $user['id'];
            }
        }

        // Build GROUP_CONCAT expression per driver
        // MySQL:  GROUP_CONCAT(DISTINCT col ORDER BY col SEPARATOR ', ')
        // SQLite: GROUP_CONCAT(col, ', ')   — no DISTINCT/ORDER inside aggregate
        $staffConcat = $isSQLite
            ? "GROUP_CONCAT(staff.name, ', ') AS staff_names"
            : "GROUP_CONCAT(DISTINCT staff.name ORDER BY staff.name SEPARATOR ', ') AS staff_names";

        $providerConcat = $isSQLite
            ? "GROUP_CONCAT(provider.name, ', ') AS provider_names"
            : "GROUP_CONCAT(DISTINCT provider.name ORDER BY provider.name SEPARATOR ', ') AS provider_names";

        // Fetch assignments for providers
        $providerAssignments = [];
        if (!empty($providerIds)) {
            try {
                $builder = $db->table('xs_provider_staff_assignments AS psa')
                    ->select("psa.provider_id, {$staffConcat}", false)
                    ->join('xs_users AS staff', 'staff.id = psa.staff_id', 'left')
                    ->whereIn('psa.provider_id', $providerIds)
                    ->groupBy('psa.provider_id');
                
                $results = $builder->get()->getResultArray();
                foreach ($results as $row) {
                    $providerAssignments[$row['provider_id']] = $row['staff_names'];
                }
            } catch (\Throwable $e) {
                log_message('warning', 'enrichUsersWithAssignments: provider assignments query failed: ' . $e->getMessage());
            }
        }

        // Fetch assignments for staff
        $staffAssignments = [];
        if (!empty($staffIds)) {
            try {
                $builder = $db->table('xs_provider_staff_assignments AS psa')
                    ->select("psa.staff_id, {$providerConcat}", false)
                    ->join('xs_users AS provider', 'provider.id = psa.provider_id', 'left')
                    ->whereIn('psa.staff_id', $staffIds)
                    ->groupBy('psa.staff_id');
                
                $results = $builder->get()->getResultArray();
                foreach ($results as $row) {
                    $staffAssignments[$row['staff_id']] = $row['provider_names'];
                }
            } catch (\Throwable $e) {
                log_message('warning', 'enrichUsersWithAssignments: staff assignments query failed: ' . $e->getMessage());
            }
        }

        // Add assignment info to each user
        foreach ($users as &$user) {
            if ($user['role'] === 'provider') {
                $user['assignments'] = $providerAssignments[$user['id']] ?? null;
            } elseif ($user['role'] === 'staff') {
                $user['assignments'] = $staffAssignments[$user['id']] ?? null;
            } else {
                $user['assignments'] = null;
            }
        }
        unset($user);

        return $users;
    }

    private function getUserStatsBasedOnRole(int $currentUserId, array $usersForContext = []): array
    {
        $currentUser = $this->userModel->find($currentUserId);
        if (!$currentUser) {
            return [];
        }

        // Build stats from provided users (or fetch a small set if empty)
        if ($currentUser['role'] === 'admin') {
            $rows = $usersForContext ?: $this->userModel->whereIn('role',[ 'admin','provider','staff' ])->findAll();
            $admins = 0; $providers = 0; $staff = 0;
            foreach ($rows as $u) {
                $r = $u['role'] ?? '';
                if ($r === 'admin') $admins++;
                elseif ($r === 'provider') $providers++;
                elseif ($r === 'staff') $staff++;
            }
            return [
                'total' => $admins + $providers + $staff,
                'admins' => $admins,
                'providers' => $providers,
                'staff' => $staff,
                'recent' => 0,
            ];
        } elseif ($currentUser['role'] === 'provider') {
            $staff = $this->userModel->getStaffForProvider($currentUserId);
            return [
                'total' => count($staff) + 1, // +1 for the provider
                'staff' => count($staff),
                'providers' => 1,
                'admins' => 0,
                'recent' => 0
            ];
        }

        return ['total' => 1, 'staff' => 0, 'providers' => 0, 'admins' => 0, 'recent' => 0];
    }

    private function getAvailableRolesForUser(int $currentUserId, ?array $targetUser = null): array
    {
        $roles = [];
        
        if ($this->permissionModel->hasPermission($currentUserId, 'create_admin')) {
            $roles[] = 'admin';
        }
        if ($this->permissionModel->hasPermission($currentUserId, 'create_provider')) {
            $roles[] = 'provider';
        }
        if ($this->permissionModel->hasPermission($currentUserId, 'create_staff')) {
            $roles[] = 'staff';
        }
        
        // Customer creation is handled via xs_customers, not users

        return $roles;
    }

    private function canCreateRole(int $currentUserId, string $role): bool
    {
        switch ($role) {
            case 'admin':
                return $this->permissionModel->hasPermission($currentUserId, 'create_admin');
            case 'provider':
                return $this->permissionModel->hasPermission($currentUserId, 'create_provider');
            case 'staff':
                return $this->permissionModel->hasPermission($currentUserId, 'create_staff');
            default:
                return false;
        }
    }

    private function getStoreValidationRules(): array
    {
        return [
            'name' => 'required|min_length[2]|max_length[255]',
            'email' => 'required|valid_email|is_unique[xs_users.email]',
            'role' => 'required|in_list[admin,provider,staff]',
            'password' => 'required|min_length[8]',
            'password_confirm' => 'required|matches[password]',
            'phone' => 'permit_empty|max_length[20]',
        ];
    }

    private function getUpdateValidationRules(int $userId, bool $includePasswordRules, bool $canChangeRole): array
    {
        $rules = [
            'name' => 'required|min_length[2]|max_length[255]',
            'email' => "required|valid_email|is_unique[xs_users.email,id,{$userId}]",
            'phone' => 'permit_empty|max_length[20]',
        ];

        if ($includePasswordRules) {
            $rules['password'] = 'required|min_length[8]';
            $rules['password_confirm'] = 'required|matches[password]';
        }

        if ($canChangeRole) {
            $rules['role'] = 'required|in_list[admin,provider,staff]';
        }

        return $rules;
    }

    private function canChangeUserRole(int $currentUserId, int $targetUserId): bool
    {
        $currentUser = $this->userModel->find($currentUserId);
        
        // Only admins can change user roles
        return $currentUser['role'] === 'admin';
    }

    // -------------------------------------------------------------------------
    // Location‑day sync helpers
    // -------------------------------------------------------------------------

    /**
     * Rebuild each location's day_of_week rows from the schedule checkboxes.
     *
     * The form posts:
     *   schedule[monday][locations][] = 5
     *   schedule[monday][locations][] = 7
     *   schedule[tuesday][locations][] = 5
     *   ...
     *
     * We invert this to per-location day lists, then call setLocationDays().
     */
    private function syncLocationDaysFromSchedule(int $providerId, array $scheduleInput): void
    {
        $locationModel = new LocationModel();

        // Get the provider's existing locations so we know the full set
        $providerLocations = $locationModel->getProviderLocations($providerId);
        if (empty($providerLocations)) {
            return;
        }

        // Build a map: locationId → [dayInt, dayInt, ...]
        $locationDaysMap = [];
        foreach ($providerLocations as $loc) {
            $locationDaysMap[(int) $loc['id']] = [];
        }

        foreach ($scheduleInput as $dayName => $dayData) {
            if (!isset(LocationModel::DAY_NAME_TO_INT[$dayName])) {
                continue;
            }
            $dayInt = LocationModel::DAY_NAME_TO_INT[$dayName];

            $locationIds = $dayData['locations'] ?? [];
            foreach ($locationIds as $locId) {
                $locId = (int) $locId;
                if (isset($locationDaysMap[$locId])) {
                    $locationDaysMap[$locId][] = $dayInt;
                }
            }
        }

        // Persist each location's day set
        foreach ($locationDaysMap as $locId => $days) {
            $locationModel->setLocationDays($locId, $days);
        }
    }
}
