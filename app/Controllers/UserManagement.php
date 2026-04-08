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
 * @author      Nilesh Nagin Cara
 * @copyright   2024-2026 Nilesh Nagin Cara
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
use App\Services\UserManagementMutationService;
use App\Services\ScheduleValidationService;
use App\Services\UserManagementContextService;
use App\Services\UserDeletionService;

class UserManagement extends BaseController
{
    protected $userModel;
    protected $permissionModel;
    protected $auditModel;

    protected $providerScheduleModel;
    protected ProviderStaffModel $providerStaffModel;
    protected LocalizationSettingsService $localization;
    protected ScheduleValidationService $scheduleValidation;
    protected UserManagementContextService $userManagementContextService;
    protected UserManagementMutationService $userManagementMutationService;
    protected UserDeletionService $userDeletionService;
    protected array $scheduleDays = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];

    public function __construct(
        ?UserModel $userModel = null,
        ?UserPermissionModel $permissionModel = null,
        ?AuditLogModel $auditModel = null,
        ?ProviderScheduleModel $providerScheduleModel = null,
        ?ProviderStaffModel $providerStaffModel = null,
        ?LocalizationSettingsService $localization = null,
        ?ScheduleValidationService $scheduleValidation = null,
        ?UserManagementContextService $userManagementContextService = null,
        ?UserManagementMutationService $userManagementMutationService = null,
        ?UserDeletionService $userDeletionService = null,
    )
    {
        helper('user');
        $this->userModel = $userModel ?? new UserModel();
        $this->permissionModel = $permissionModel ?? new UserPermissionModel();
        $this->auditModel = $auditModel ?? new AuditLogModel();
        $this->providerScheduleModel = $providerScheduleModel ?? new ProviderScheduleModel();
        $this->providerStaffModel = $providerStaffModel ?? new ProviderStaffModel();
        $this->localization = $localization ?? new LocalizationSettingsService();
        $this->scheduleValidation = $scheduleValidation ?? new ScheduleValidationService($this->localization);
        $this->userManagementContextService = $userManagementContextService ?? new UserManagementContextService(
            $this->userModel,
            $this->permissionModel,
            $this->providerStaffModel,
            $this->providerScheduleModel,
            $this->localization,
            $this->scheduleValidation,
        );
        $this->userManagementMutationService = $userManagementMutationService ?? new UserManagementMutationService(
            userModel: $this->userModel,
            providerStaffModel: $this->providerStaffModel,
            providerScheduleModel: $this->providerScheduleModel,
            auditModel: $this->auditModel,
            scheduleValidation: $this->scheduleValidation,
            contextService: $this->userManagementContextService,
        );
        $this->userDeletionService = $userDeletionService ?? new UserDeletionService($this->userModel, $this->auditModel);
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

        $data = $this->userManagementContextService->buildIndexViewData((int) $currentUserId, $currentUser);

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

        $availableRoles = $this->userManagementContextService->getAvailableRolesForUser((int) $currentUserId);
        
        if (empty($availableRoles)) {
            return redirect()->to(base_url('user-management'))
                           ->with('error', 'You do not have permission to create users.');
        }

        $data = $this->userManagementContextService->buildCreateViewData(
            (int) $currentUserId,
            $currentUser,
            old('schedule') ?? [],
            session()->getFlashdata('schedule_errors') ?? [],
            $this->validator,
            $this->scheduleDays,
        );

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

        $rules = $this->userManagementContextService->getStoreValidationRules();

        if (!$this->validate($rules)) {
            log_message('warning', 'Validation failed: ' . json_encode($this->validator->getErrors()));
            if ($this->request->isAJAX()) {
                return $this->respondUserActionFailure('Validation failed', 422, null, [
                    'errors' => $this->validator->getErrors()
                ]);
            }
            return redirect()->back()->withInput()->with('validation', $this->validator);
        }

        $result = $this->userManagementMutationService->createUser((int) $currentUserId, $currentUser, [
            'name' => $this->request->getPost('name'),
            'email' => $this->request->getPost('email'),
            'phone' => $this->request->getPost('phone'),
            'phone_country_code' => $this->request->getPost('phone_country_code'),
            'roles' => $this->request->getPost('roles') ?? [],
            'role' => $this->request->getPost('role'),
            'password' => $this->request->getPost('password'),
            'color' => $this->request->getPost('color'),
            'schedule' => $this->request->getPost('schedule') ?? [],
        ]);

        if (!$result['success']) {
            if ($this->request->isAJAX()) {
                return $this->respondUserActionFailure($result['message'], $result['statusCode'] ?? 400, null, [
                    'errors' => $result['errors'] ?? null,
                    'userId' => $result['userId'] ?? null,
                ]);
            }

            $redirect = !empty($result['redirect']) ? redirect()->to($result['redirect']) : redirect()->back()->withInput();
            if (!empty($result['scheduleErrors'])) {
                $redirect = $redirect->with('schedule_errors', $result['scheduleErrors']);
            }

            return $redirect->with('error', $result['message']);
        }

        if ($this->request->isAJAX()) {
            return $this->respondUserActionSuccess($result['message'], [
                'redirect' => $result['redirect'],
                'userId' => $result['userId'] ?? null,
            ]);
        }

        return redirect()->to($result['redirect'])
            ->with('success', $result['message']);
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

        $resolvedUser = $this->userManagementContextService->resolveManageableUser((int) $currentUserId, $userId);
        if (!$resolvedUser['success']) {
            return redirect()->to(base_url('user-management'))
                           ->with('error', $resolvedUser['message']);
        }

        $user = $resolvedUser['user'];

        // Populate user's current roles from xs_user_roles or fallback to xs_users.role
        $user['roles'] = $this->userModel->getRolesForUser($user['id']);

        $existingSchedule = $this->providerScheduleModel->getByProvider($user['id']);
        $rawSchedule = old('schedule') ?: $existingSchedule;

        $data = $this->userManagementContextService->buildEditViewData(
            (int) $currentUserId,
            $currentUser,
            $user,
            $rawSchedule,
            session()->getFlashdata('schedule_errors') ?? [],
            $this->validator,
            $this->scheduleDays,
        );

        return view('user-management/edit', $data);
    }

    /**
     * Process edit user form
     */
    public function update(int $userId)
    {
        $currentUserId = session()->get('user_id');
        $currentUser = session()->get('user');
        $editUrl = base_url('user-management/edit/' . $userId);

        if (!$currentUserId || !$currentUser) {
            return redirect()->to(base_url('auth/login'));
        }

        $resolvedUser = $this->userManagementContextService->resolveManageableUser((int) $currentUserId, $userId);
        if (!$resolvedUser['success']) {
            log_message('error', '[UserManagement::update] Unable to resolve manageable user. manager_id={managerId} target_id={targetId} message={message}', [
                'managerId' => (int) $currentUserId,
                'targetId' => $userId,
                'message' => $resolvedUser['message'],
            ]);
            return $this->respondUserActionFailure(
                $resolvedUser['message'],
                $resolvedUser['statusCode'] ?? 400,
                redirect()->to($editUrl)
            );
        }

        $user = $resolvedUser['user'];

        $rules = $this->userManagementContextService->getUpdateValidationRules(
            $userId,
            !empty($this->request->getPost('password')),
            $this->userManagementContextService->canChangeUserRole((int) $currentUserId, $userId)
        );

        if (!$this->validate($rules)) {
            log_message('error', '[UserManagement::update] Validation failed for user_id={userId}: {errors}', [
                'userId' => $userId,
                'errors' => json_encode($this->validator->getErrors()),
            ]);
            if ($this->request->isAJAX()) {
                return $this->respondUserActionFailure('Validation failed', 422, null, [
                    'errors' => $this->validator->getErrors(),
                ]);
            }
            return redirect()->to($editUrl)->withInput()->with('validation', $this->validator);
        }

        $result = $this->userManagementMutationService->updateUser($userId, (int) $currentUserId, $currentUser, $user, [
            'name' => $this->request->getPost('name'),
            'email' => $this->request->getPost('email'),
            'phone' => $this->request->getPost('phone'),
            'phone_country_code' => $this->request->getPost('phone_country_code'),
            'is_active' => $this->request->getPost('is_active'),
            'password' => $this->request->getPost('password'),
            'roles' => $this->request->getPost('roles') ?? [],
            'role' => $this->request->getPost('role'),
            'color' => $this->request->getPost('color'),
            'schedule' => $this->request->getPost('schedule') ?? [],
        ]);

        if (!$result['success']) {
            log_message('error', '[UserManagement::update] Update mutation failed for user_id={userId}: {message}', [
                'userId' => $userId,
                'message' => $result['message'],
            ]);
            if ($this->request->isAJAX()) {
                return $this->respondUserActionFailure($result['message'], $result['statusCode'] ?? 400, null, [
                    'errors' => $result['errors'] ?? null,
                    'blockCode' => $result['blockCode'] ?? null,
                ]);
            }

            $redirect = redirect()->to($editUrl)->withInput();
            if (!empty($result['scheduleErrors'])) {
                $redirect = $redirect->with('schedule_errors', $result['scheduleErrors']);
            }

            return $redirect->with('error', $result['message']);
        }

        if (!empty($result['updatedUser']) && $currentUserId === $userId) {
            session()->set('user', [
                'name' => $result['updatedUser']['name'],
                'email' => $result['updatedUser']['email'],
                'role' => $result['updatedUser']['role'],
            ]);
        }

        if ($this->request->isAJAX()) {
            return $this->respondUserActionSuccess($result['message'], [
                'redirect' => $result['redirect'],
            ]);
        }

        return redirect()->to($result['redirect'])
            ->with('success', $result['message']);
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

        $result = $this->userManagementMutationService->deactivateUser((int) $currentUserId, $userId);

        if (!$result['success']) {
            return $this->respondUserActionFailure(
                $result['message'],
                $result['statusCode'] ?? 400,
                redirect()->to(base_url('user-management')),
                ['blockCode' => $result['blockCode'] ?? null]
            );
        }

        return $this->respondUserActionSuccessOrRedirect(
            $result['message'],
            $result['redirect'] ?? base_url('user-management')
        );
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

        $result = $this->userManagementMutationService->activateUser((int) $currentUserId, $userId);

        if (!$result['success']) {
            return $this->respondUserActionFailure(
                $result['message'],
                $result['statusCode'] ?? 400,
                redirect()->to(base_url('user-management'))
            );
        }

        return $this->respondUserActionSuccessOrRedirect(
            $result['message'],
            $result['redirect'] ?? base_url('user-management')
        );
    }

    /**
     * Delete preview payload for role-aware confirmation modal.
     */
    public function deletePreview(int $userId)
    {
        $currentUserId = session()->get('user_id');

        if (!$currentUserId) {
            return $this->respondUserActionFailure('Unauthorized', 401);
        }

        $preview = $this->userDeletionService->buildPreviewForUserId((int) $currentUserId, $userId);
        if (!$preview['success']) {
            return $this->respondUserActionFailure($preview['message'], $preview['statusCode'] ?? 400);
        }

        return $this->respondUserActionSuccess('', [
            'allowed' => $preview['allowed'],
            'blockCode' => $preview['blockCode'],
            'typedConfirmationRequired' => $preview['typedConfirmationRequired'],
            'target' => $preview['target'],
            'impact' => $preview['impact'],
        ], false);
    }

    /**
     * Delete user with role-aware safety checks and transactional cleanup.
     */
    public function delete(int $userId)
    {
        $currentUserId = session()->get('user_id');

        if (!$currentUserId) {
            return redirect()->to(base_url('auth/login'));
        }

        $result = $this->userDeletionService->deleteUserById((int) $currentUserId, $userId);
        if (!$result['success']) {
            return $this->respondUserActionFailure(
                $result['message'] ?? 'You cannot delete this user.',
                $result['statusCode'] ?? 422,
                redirect()->to(base_url('user-management')),
                [
                    'blockCode' => $result['blockCode'] ?? null,
                ]
            );
        }

        return $this->respondUserActionSuccessOrRedirect(
            $result['message'],
            base_url('user-management')
        );
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
            return $this->respondSimpleApiError('Unauthorized', 401);
        }

        $stats = $this->userManagementContextService->getUserStats((int) $currentUserId);
        
        return $this->respondSimpleApiSuccess([
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
            return $this->respondSimpleApiError('Unauthorized', 401);
        }

        $role = $this->request->getGet('role');
        
        $payload = $this->userManagementContextService->getApiUsers((int) $currentUserId, $role ?: null);
        
        return $this->respondSimpleApiSuccess([
            'items' => $payload['items'],
            'total' => $payload['total']
        ]);
    }

    // Helper methods

    private function respondUserActionFailure(
        string $message,
        int $statusCode = 400,
        $redirect = null,
        array $extra = []
    ) {
        if ($this->request->isAJAX()) {
            return $this->response->setStatusCode($statusCode)->setJSON(array_merge([
                'success' => false,
                'message' => $message,
            ], $extra));
        }

        if ($redirect !== null) {
            return $redirect->with('error', $message);
        }

        return redirect()->to(base_url('user-management'))->with('error', $message);
    }

    private function respondUserActionSuccess(string $message, array $extra = [], bool $includeMessage = true)
    {
        $payload = array_merge([
            'success' => true,
        ], $extra);

        if ($includeMessage) {
            $payload['message'] = $message;
        }

        return $this->response->setJSON($payload);
    }

    private function respondUserActionSuccessOrRedirect(string $message, string $redirectUrl, array $extra = [])
    {
        if ($this->request->isAJAX()) {
            return $this->respondUserActionSuccess($message, array_merge([
                'redirect' => $redirectUrl,
            ], $extra));
        }

        return redirect()->to($redirectUrl)->with('success', $message);
    }

    private function respondSimpleApiError(string $message, int $statusCode)
    {
        return $this->response->setStatusCode($statusCode)->setJSON([
            'error' => $message,
        ]);
    }

    private function respondSimpleApiSuccess(array $payload)
    {
        return $this->response->setJSON($payload);
    }

    // -------------------------------------------------------------------------
    // Location‑day sync helpers
    // -------------------------------------------------------------------------

}
