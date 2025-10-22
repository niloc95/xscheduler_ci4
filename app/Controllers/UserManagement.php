<?php

namespace App\Controllers;

use App\Models\ProviderScheduleModel;
use App\Models\ProviderStaffModel;
use App\Models\UserModel;
use App\Models\UserPermissionModel;
use App\Models\AuditLogModel;
use App\Services\LocalizationSettingsService;

class UserManagement extends BaseController
{
    protected $userModel;
    protected $permissionModel;
    protected $auditModel;
    
    protected $providerScheduleModel;
    protected ProviderStaffModel $providerStaffModel;
    protected LocalizationSettingsService $localization;
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
    }

    /**
     * Display user management dashboard
     */
    public function index()
    {
        $currentUserId = session()->get('user_id');
        $currentUser = session()->get('user');

        if (!$currentUserId || !$currentUser) {
            return redirect()->to('/auth/login');
        }

        // Get users based on current user's role and permissions
        $users = $this->getUsersBasedOnRole($currentUserId);
        $stats = $this->getUserStatsBasedOnRole($currentUserId, $users);

        $data = [
            'title' => 'User Management - WebSchedulr',
            'currentUser' => $currentUser,
            'users' => $users,
            'stats' => $stats,
            'canCreateAdmin' => $this->permissionModel->hasPermission($currentUserId, 'create_admin'),
            'canCreateProvider' => $this->permissionModel->hasPermission($currentUserId, 'create_provider'),
            'canCreateStaff' => $this->permissionModel->hasPermission($currentUserId, 'create_staff'),
        ];

        return view('user_management/index', $data);
    }

    /**
     * Show create user form
     */
    public function create()
    {
        $currentUserId = session()->get('user_id');
        $currentUser = session()->get('user');

        if (!$currentUserId || !$currentUser) {
            return redirect()->to('/auth/login');
        }

        // Determine which roles the current user can create
    $availableRoles = $this->getAvailableRolesForUser($currentUserId);
        
        if (empty($availableRoles)) {
            return redirect()->to('/user-management')
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
            $availableStaff = $staffModel->whereIn('role', ['staff', 'receptionist'])
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
            'providerSchedule' => $this->prepareScheduleForView(old('schedule') ?? []),
            'scheduleDays' => $this->scheduleDays,
            'scheduleErrors' => session()->getFlashdata('schedule_errors') ?? [],
            'localizationContext' => $this->localization->getContext(),
            'timeFormatExample' => $this->localization->getFormatExample(),
        ];

        return view('user_management/create', $data);
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
            return redirect()->to('/auth/login');
        }

        // Validation rules
        $rules = [
            'name' => 'required|min_length[2]|max_length[255]',
            'email' => 'required|valid_email|is_unique[users.email]',
            'role' => 'required|in_list[admin,provider,staff,receptionist]',
            'password' => 'required|min_length[8]',
            'password_confirm' => 'required|matches[password]',
            'phone' => 'permit_empty|max_length[20]',
        ];

        if (!$this->validate($rules)) {
            log_message('warning', 'Validation failed: ' . json_encode($this->validator->getErrors()));
            return redirect()->back()->withInput()->with('validation', $this->validator);
        }

        $role = $this->request->getPost('role');
        
        log_message('info', 'Creating user with role: ' . $role . ' by user: ' . $currentUserId . ' (role: ' . $currentUser['role'] . ')');
        
        // Check if current user can create this role
        if (!$this->canCreateRole($currentUserId, $role)) {
            log_message('error', 'Permission denied: User ' . $currentUserId . ' cannot create role: ' . $role);
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
            [$scheduleClean, $scheduleErrors] = $this->validateProviderScheduleInput($scheduleInput);
            if (!empty($scheduleErrors)) {
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
            if ($currentUser['role'] === 'provider' && in_array($role, ['staff', 'receptionist'], true)) {
                log_message('info', 'Auto-assigning staff ' . $userId . ' to provider ' . $currentUserId);
                $this->providerStaffModel->insert([
                    'provider_id' => $currentUserId,
                    'staff_id' => $userId,
                    'assigned_by' => $currentUserId,
                    'assigned_at' => date('Y-m-d H:i:s')
                ]);
                
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

            return redirect()->to('/user-management/edit/' . $userId)
                           ->with('success', 'User created successfully. You can now manage assignments and schedules.');
        } else {
            log_message('error', '[UserManagement::store] Failed to create user');
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
            return redirect()->to('/auth/login');
        }

        $user = $this->userModel->find($userId);
        
        if (!$user) {
            return redirect()->to('/user-management')
                           ->with('error', 'User not found.');
        }

        // Check permission to edit this user
        $canManage = $this->userModel->canManageUser($currentUserId, $userId);
        
        if (!$canManage) {
            return redirect()->to('/user-management')
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
                    ->whereIn('role', ['staff', 'receptionist'])
                    ->where('is_active', true)
                    ->orderBy('name', 'ASC')
                    ->findAll();
            }
        } elseif (in_array($user['role'] ?? '', ['staff', 'receptionist'], true)) {
            $assignedProviders = $this->providerStaffModel->getProvidersForStaff($user['id']);

            if ($canManageAssignments) {
                $availableProviders = $this->userModel
                    ->where('role', 'provider')
                    ->where('is_active', true)
                    ->orderBy('name', 'ASC')
                    ->findAll();
            }
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
            'providerSchedule' => $this->prepareScheduleForView($rawSchedule),
            'scheduleDays' => $this->scheduleDays,
            'scheduleErrors' => session()->getFlashdata('schedule_errors') ?? [],
            'assignedStaff' => $assignedStaff,
            'availableStaff' => $availableStaff,
            'assignedProviders' => $assignedProviders,
            'availableProviders' => $availableProviders,
            'canManageAssignments' => $canManageAssignments,
            'localizationContext' => $this->localization->getContext(),
            'timeFormatExample' => $this->localization->getFormatExample(),
        ];

        return view('user_management/edit', $data);
    }

    /**
     * Process edit user form
     */
    public function update(int $userId)
    {
        $currentUserId = session()->get('user_id');
        $currentUser = session()->get('user');

        if (!$currentUserId || !$currentUser) {
            return redirect()->to('/auth/login');
        }

        $user = $this->userModel->find($userId);
        if (!$user) {
            return redirect()->to('/user-management')
                           ->with('error', 'User not found.');
        }

        // Check permission to edit this user
        if (!$this->userModel->canManageUser($currentUserId, $userId)) {
            return redirect()->to('/user-management')
                           ->with('error', 'You do not have permission to edit this user.');
        }

        // Validation rules
        $rules = [
            'name' => 'required|min_length[2]|max_length[255]',
            'email' => "required|valid_email|is_unique[users.email,id,{$userId}]",
            'phone' => 'permit_empty|max_length[20]',
        ];

        // Add password rules if provided
        if ($this->request->getPost('password')) {
            $rules['password'] = 'required|min_length[8]';
            $rules['password_confirm'] = 'required|matches[password]';
        }

        // Add role validation if user can change roles
        if ($this->canChangeUserRole($currentUserId, $userId)) {
            $rules['role'] = 'required|in_list[admin,provider,staff,receptionist]';
        }

        if (!$this->validate($rules)) {
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
            [$scheduleClean, $scheduleErrors] = $this->validateProviderScheduleInput($scheduleInput);
            if (!empty($scheduleErrors)) {
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
            } else {
                $this->providerScheduleModel->deleteByProvider($userId);
            }
            return redirect()->to('/user-management')
                           ->with('success', 'User updated successfully.');
        } else {
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
            return redirect()->to('/auth/login');
        }

        if ($currentUserId === $userId) {
            return redirect()->to('/user-management')
                           ->with('error', 'You cannot deactivate your own account.');
        }

        if ($this->userModel->deactivateUser($userId, $currentUserId)) {
            return redirect()->to('/user-management')
                           ->with('success', 'User deactivated successfully.');
        } else {
            return redirect()->to('/user-management')
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
            return redirect()->to('/auth/login');
        }

        if ($this->userModel->update($userId, ['is_active' => true])) {
            return redirect()->to('/user-management')
                           ->with('success', 'User activated successfully.');
        } else {
            return redirect()->to('/user-management')
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
            return redirect()->to('/auth/login');
        }

        // Only admins can delete users
        if ($currentUser['role'] !== 'admin') {
            return redirect()->to('/user-management')
                           ->with('error', 'Only administrators can delete users.');
        }

        // Cannot delete yourself
        if ($currentUserId === $userId) {
            return redirect()->to('/user-management')
                           ->with('error', 'You cannot delete your own account.');
        }

        $targetUser = $this->userModel->find($userId);
        if (!$targetUser) {
            return redirect()->to('/user-management')
                           ->with('error', 'User not found.');
        }

        // Perform the delete
        if ($this->userModel->delete($userId)) {
            return redirect()->to('/user-management')
                           ->with('success', 'User "' . esc($targetUser['name']) . '" deleted successfully.');
        } else {
            return redirect()->to('/user-management')
                           ->with('error', 'Failed to delete user. Please try again.');
        }
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
                    ->whereIn('role', ['admin', 'provider', 'staff', 'receptionist'])
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
     * Enrich user array with assignment relationships
     */
    private function enrichUsersWithAssignments(array $users): array
    {
        if (empty($users)) {
            return $users;
        }

        // Group users by role for batch queries
        $providerIds = [];
        $staffIds = [];
        
        foreach ($users as $user) {
            if ($user['role'] === 'provider') {
                $providerIds[] = $user['id'];
            } elseif ($user['role'] === 'staff' || $user['role'] === 'receptionist') {
                $staffIds[] = $user['id'];
            }
        }

        // Fetch assignments for providers
        $providerAssignments = [];
        if (!empty($providerIds)) {
            $builder = $this->userModel->db->table('xs_provider_staff_assignments AS psa')
                ->select('psa.provider_id, GROUP_CONCAT(DISTINCT staff.name ORDER BY staff.name SEPARATOR ", ") AS staff_names')
                ->join('xs_users AS staff', 'staff.id = psa.staff_id', 'left')
                ->whereIn('psa.provider_id', $providerIds)
                ->groupBy('psa.provider_id');
            
            $results = $builder->get()->getResultArray();
            foreach ($results as $row) {
                $providerAssignments[$row['provider_id']] = $row['staff_names'];
            }
        }

        // Fetch assignments for staff
        $staffAssignments = [];
        if (!empty($staffIds)) {
            $builder = $this->userModel->db->table('xs_provider_staff_assignments AS psa')
                ->select('psa.staff_id, GROUP_CONCAT(DISTINCT provider.name ORDER BY provider.name SEPARATOR ", ") AS provider_names')
                ->join('xs_users AS provider', 'provider.id = psa.provider_id', 'left')
                ->whereIn('psa.staff_id', $staffIds)
                ->groupBy('psa.staff_id');
            
            $results = $builder->get()->getResultArray();
            foreach ($results as $row) {
                $staffAssignments[$row['staff_id']] = $row['provider_names'];
            }
        }

        // Add assignment info to each user
        foreach ($users as &$user) {
            if ($user['role'] === 'provider') {
                $user['assignments'] = $providerAssignments[$user['id']] ?? null;
            } elseif ($user['role'] === 'staff' || $user['role'] === 'receptionist') {
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
            $rows = $usersForContext ?: $this->userModel->whereIn('role',[ 'admin','provider','staff','receptionist' ])->findAll();
            $admins = 0; $providers = 0; $staff = 0;
            foreach ($rows as $u) {
                $r = $u['role'] ?? '';
                if ($r === 'admin') $admins++;
                elseif ($r === 'provider') $providers++;
                elseif ($r === 'staff' || $r === 'receptionist') $staff++;
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
            $roles[] = 'receptionist'; // Providers and admins can create receptionists
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

    private function canAssignToProvider(int $currentUserId, int $providerId): bool
    {
        $currentUser = $this->userModel->find($currentUserId);
        
        // Admins can assign to any provider
        if ($currentUser['role'] === 'admin') {
            return true;
        }
        
        // Providers can only assign to themselves
        if ($currentUser['role'] === 'provider') {
            return $currentUserId === $providerId;
        }
        
        return false;
    }

    private function canChangeUserRole(int $currentUserId, int $targetUserId): bool
    {
        $currentUser = $this->userModel->find($currentUserId);
        
        // Only admins can change user roles
        return $currentUser['role'] === 'admin';
    }

    private function validateProviderScheduleInput(array $input): array
    {
        $clean = [];
        $errors = [];

        foreach ($this->scheduleDays as $day) {
            if (!isset($input[$day])) {
                continue;
            }

            $row = $input[$day];
            $isActive = $this->toBool($row['is_active'] ?? null);

            if (!$isActive) {
                continue;
            }

            $rawStart = $row['start_time'] ?? null;
            $rawEnd = $row['end_time'] ?? null;
            $rawBreakStart = $row['break_start'] ?? null;
            $rawBreakEnd = $row['break_end'] ?? null;

            $start = $this->normaliseTimeString($rawStart);
            $end   = $this->normaliseTimeString($rawEnd);
            $breakStart = $this->normaliseTimeString($rawBreakStart);
            $breakEnd   = $this->normaliseTimeString($rawBreakEnd);

            if (!$start || !$end) {
                $errors[$day] = 'Start and end times are required. ' . $this->localization->describeExpectedFormat();
                continue;
            }

            if (strtotime($start) >= strtotime($end)) {
                $errors[$day] = 'Start time must be earlier than end time.';
                continue;
            }

            $hasBreakStartInput = is_string($rawBreakStart) && trim($rawBreakStart) !== '';
            $hasBreakEndInput = is_string($rawBreakEnd) && trim($rawBreakEnd) !== '';

            if ($hasBreakStartInput && !$breakStart) {
                $errors[$day] = 'Break start must use the expected time format. ' . $this->localization->describeExpectedFormat();
                continue;
            }

            if ($hasBreakEndInput && !$breakEnd) {
                $errors[$day] = 'Break end must use the expected time format. ' . $this->localization->describeExpectedFormat();
                continue;
            }

            if (($breakStart && !$breakEnd) || (!$breakStart && $breakEnd)) {
                $errors[$day] = 'Provide both break start and end times. ' . $this->localization->describeExpectedFormat();
                continue;
            }

            if ($breakStart && $breakEnd) {
                if (strtotime($breakStart) >= strtotime($breakEnd)) {
                    $errors[$day] = 'Break start must be earlier than break end.';
                    continue;
                }

                if (strtotime($breakStart) < strtotime($start) || strtotime($breakEnd) > strtotime($end)) {
                    $errors[$day] = 'Break must fall within working hours.';
                    continue;
                }
            }

            $clean[$day] = [
                'is_active'   => 1,
                'start_time'  => $start,
                'end_time'    => $end,
                'break_start' => $breakStart,
                'break_end'   => $breakEnd,
            ];
        }

        return [$clean, $errors];
    }

    private function prepareScheduleForView($source): array
    {
        $prepared = [];
        foreach ($this->scheduleDays as $day) {
            $prepared[$day] = [
                'is_active'   => false,
                'start_time'  => '',
                'end_time'    => '',
                'break_start' => '',
                'break_end'   => '',
            ];
        }

        if (!is_array($source)) {
            return $prepared;
        }

        foreach ($this->scheduleDays as $day) {
            if (!isset($source[$day])) {
                continue;
            }

            $row = $source[$day];
            if (!is_array($row)) {
                continue;
            }

            $isActive = $this->toBool($row['is_active'] ?? null);
            $prepared[$day]['is_active'] = $isActive;
            $prepared[$day]['start_time'] = $this->localization->formatTimeForDisplay($row['start_time'] ?? null);
            $prepared[$day]['end_time'] = $this->localization->formatTimeForDisplay($row['end_time'] ?? null);
            $prepared[$day]['break_start'] = $this->localization->formatTimeForDisplay($row['break_start'] ?? null);
            $prepared[$day]['break_end'] = $this->localization->formatTimeForDisplay($row['break_end'] ?? null);
        }

        return $prepared;
    }

    private function normaliseTimeString(?string $time): ?string
    {
        return $this->localization->normaliseTimeInput($time);
    }

    private function toBool($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }
}
