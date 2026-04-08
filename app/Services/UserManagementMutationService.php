<?php

namespace App\Services;

use App\Models\AuditLogModel;
use App\Models\BusinessHourModel;
use App\Models\LocationModel;
use App\Models\ProviderScheduleModel;
use App\Models\ProviderStaffModel;
use App\Models\UserModel;

class UserManagementMutationService
{
    private UserModel $userModel;
    private ProviderStaffModel $providerStaffModel;
    private ProviderScheduleModel $providerScheduleModel;
    private BusinessHourModel $businessHourModel;
    private LocationModel $locationModel;
    private AuditLogModel $auditModel;
    private LocalizationSettingsService $localization;
    private ScheduleValidationService $scheduleValidation;
    private UserManagementContextService $contextService;
    private PhoneNumberService $phoneNumberService;

    public function __construct(
        ?UserModel $userModel = null,
        ?ProviderStaffModel $providerStaffModel = null,
        ?ProviderScheduleModel $providerScheduleModel = null,
        ?AuditLogModel $auditModel = null,
        ?ScheduleValidationService $scheduleValidation = null,
        ?UserManagementContextService $contextService = null,
        ?BusinessHourModel $businessHourModel = null,
        ?LocalizationSettingsService $localization = null,
        ?LocationModel $locationModel = null,
        ?PhoneNumberService $phoneNumberService = null,
    ) {
        $this->userModel = $userModel ?? new UserModel();
        $this->providerStaffModel = $providerStaffModel ?? new ProviderStaffModel();
        $this->providerScheduleModel = $providerScheduleModel ?? new ProviderScheduleModel();
        $this->businessHourModel = $businessHourModel ?? new BusinessHourModel();
        $this->auditModel = $auditModel ?? new AuditLogModel();
        $this->localization = $localization ?? new LocalizationSettingsService();
        $this->scheduleValidation = $scheduleValidation ?? new ScheduleValidationService($this->localization);
        $this->locationModel = $locationModel ?? new LocationModel();
        $this->phoneNumberService = $phoneNumberService ?? new PhoneNumberService();
        $this->contextService = $contextService ?? new UserManagementContextService(
            $this->userModel,
            null,
            $this->providerStaffModel,
            $this->providerScheduleModel,
            $this->localization,
            $this->scheduleValidation,
            $this->locationModel,
        );
    }

    public function createUser(int $currentUserId, array $currentUser, array $payload): array
    {
        // Support both 'role' (single) and 'roles' (array) for backward compatibility
        $roles = [];
        if (!empty($payload['roles']) && is_array($payload['roles'])) {
            $roles = array_unique(array_values($payload['roles']));
        } elseif (!empty($payload['role'])) {
            $roles = [$payload['role']];
        }

        if (empty($roles)) {
            return [
                'success' => false,
                'statusCode' => 422,
                'message' => 'At least one role must be selected.',
                'errors' => ['roles' => 'At least one role must be selected.'],
            ];
        }

        $primaryRole = $roles[0]; // First role is primary for xs_users.role
        log_message('info', 'Creating user with roles: ' . json_encode($roles) . ' by user: ' . $currentUserId . ' (role: ' . ($currentUser['role'] ?? '') . ')');

        if (!$this->contextService->canCreateRole($currentUserId, $primaryRole)) {
            log_message('error', 'Permission denied: User ' . $currentUserId . ' cannot create role: ' . $primaryRole);
            return [
                'success' => false,
                'statusCode' => 403,
                'message' => 'You do not have permission to create users with this role.',
            ];
        }

        // Validate all roles
        foreach (array_slice($roles, 1) as $role) {
            if (!$this->contextService->canCreateRole($currentUserId, $role)) {
                log_message('error', 'Permission denied: User ' . $currentUserId . ' cannot create role: ' . $role);
                return [
                    'success' => false,
                    'statusCode' => 403,
                    'message' => 'You do not have permission to create users with one or more of the selected roles.',
                ];
            }
        }

        // Duplicate-email guard: reject at service layer before any DB write
        $emailValue = trim((string) ($payload['email'] ?? ''));
        if ($emailValue !== '' && $this->userModel->findByEmail($emailValue) !== null) {
            return [
                'success'    => false,
                'statusCode' => 409,
                'message'    => 'A user with this email address already exists. To assign additional roles, edit the existing user.',
                'errors'     => ['email' => 'This email address is already registered.'],
            ];
        }

        // Determine primary role from role hierarchy
        $roleHierarchy = ['admin', 'provider', 'staff'];
        $finalRole = null;
        foreach ($roleHierarchy as $hierarchyRole) {
            if (in_array($hierarchyRole, $roles)) {
                $finalRole = $hierarchyRole;
                break;
            }
        }

        $userData = [
            'name' => $payload['name'] ?? null,
            'email' => $payload['email'] ?? null,
            'phone' => $payload['phone'] ?? null,
            'role' => $finalRole ?? 'staff',
            'password' => $payload['password'] ?? null,
        ];

        $userData['phone'] = $this->phoneNumberService->normalize(
            isset($userData['phone']) ? (string) $userData['phone'] : null,
            isset($payload['phone_country_code']) ? (string) $payload['phone_country_code'] : null
        );

        $scheduleInput = $payload['schedule'] ?? [];
        $scheduleClean = [];
        if (in_array('provider', $roles)) {
            $userData['color'] = !empty($payload['color'])
                ? $payload['color']
                : $this->userModel->getAvailableProviderColor();
            log_message('info', 'Assigned color ' . $userData['color'] . ' to new provider');

            [$scheduleClean, $scheduleErrors] = $this->scheduleValidation->validateProviderSchedule($scheduleInput);
            if (!empty($scheduleErrors)) {
                return [
                    'success' => false,
                    'statusCode' => 422,
                    'message' => 'Please fix the highlighted schedule issues.',
                    'errors' => $scheduleErrors,
                    'scheduleErrors' => $scheduleErrors,
                ];
            }
        }

        $userId = $this->userModel->createUser($userData);
        log_message('info', 'User creation returned: ' . var_export($userId, true) . ' (type: ' . gettype($userId) . ')');

        if (!$userId) {
            log_message('error', '[UserManagementMutationService::createUser] Failed to create user');
            return [
                'success' => false,
                'statusCode' => 400,
                'message' => 'Failed to create user. Please try again.',
            ];
        }

        // Sync roles to xs_user_roles table if it exists
        try {
            $db = \Config\Database::connect();
            $userRolesTable = $db->prefixTable('user_roles');
            
            $now = date('Y-m-d H:i:s');
            $roleRows = array_map(
                static fn(string $role): array => [
                    'user_id' => $userId,
                    'role' => $role,
                    'created_at' => $now,
                ],
                $roles
            );
            $db->table($userRolesTable)->insertBatch($roleRows);
        } catch (\Throwable $e) {
            log_message('warning', '[UserManagementMutationService::createUser] Could not sync to xs_user_roles table: ' . $e->getMessage());
            // Continue - xs_users.role will be used as fallback
        }

        $this->auditModel->log(
            'user_created',
            $currentUserId,
            'user',
            $userId,
            null,
            ['role' => $userData['role'] ?? null, 'email' => $userData['email']]
        );

        if (($currentUser['role'] ?? '') === 'provider' && in_array('staff', $roles)) {
            log_message('info', 'Auto-assigning staff ' . $userId . ' to provider ' . $currentUserId);
            $assigned = $this->providerStaffModel->assignStaff($currentUserId, (int) $userId, $currentUserId, 'active');

            if (!$assigned) {
                log_message('error', '[UserManagementMutationService::createUser] Staff created but failed to auto-assign to provider. provider_id=' . $currentUserId . ' staff_id=' . $userId . ' errors=' . json_encode($this->providerStaffModel->errors()));
                return [
                    'success' => false,
                    'statusCode' => 400,
                    'message' => 'User created, but assignment to provider failed. Please contact support or try assigning again.',
                    'userId' => $userId,
                    'redirect' => base_url('user-management/edit/' . $userId),
                ];
            }

            $this->auditModel->log(
                'staff_assigned',
                $currentUserId,
                'assignment',
                $userId,
                null,
                ['provider_id' => $currentUserId, 'staff_id' => $userId]
            );
        }

        if (in_array('provider', $roles) && !empty($scheduleClean)) {
            $this->providerScheduleModel->saveSchedule((int) $userId, $scheduleClean);
            $this->businessHourModel->syncFromProviderSchedule((int) $userId, $scheduleClean);
        }

        return [
            'success' => true,
            'message' => 'User created successfully. You can now manage assignments and schedules.',
            'redirect' => base_url('user-management/edit/' . $userId),
            'userId' => $userId,
        ];
    }

    public function updateUser(int $userId, int $currentUserId, array $currentUser, array $existingUser, array $payload): array
    {
        $updateData = [
            'name' => $payload['name'] ?? null,
            'email' => $payload['email'] ?? null,
            'phone' => $payload['phone'] ?? null,
        ];

        $updateData['phone'] = $this->phoneNumberService->normalize(
            isset($updateData['phone']) ? (string) $updateData['phone'] : null,
            isset($payload['phone_country_code']) ? (string) $payload['phone_country_code'] : null
        );

        $updateData = array_merge($updateData, $this->buildUserActiveUpdatePayload(!empty($payload['is_active'])));

        if (!empty($payload['password'])) {
            $updateData['password'] = $payload['password'];
        }

        // Handle role updates (supports both single 'role' and multiple 'roles' for backward compatibility)
        $newRoles = [];
        $rolesToSync = [];
        if (!empty($payload['roles']) && is_array($payload['roles'])) {
            $newRoles = array_unique(array_values($payload['roles']));
        } elseif (!empty($payload['role'])) {
            $newRoles = [$payload['role']];
        }

        if (!empty($newRoles) && ($currentUser['role'] ?? '') === 'admin' && $this->contextService->canChangeUserRole($currentUserId, $userId)) {
            // Validate all roles
            foreach ($newRoles as $role) {
                if ($role !== ($existingUser['role'] ?? null) && !$this->contextService->canCreateRole($currentUserId, $role)) {
                    return [
                        'success' => false,
                        'statusCode' => 403,
                        'message' => 'You do not have permission to assign one or more roles.',
                    ];
                }
            }

            // Last-admin role-demotion guard
            $currentIsAdmin = ($existingUser['role'] ?? null) === 'admin';
            $newIsAdmin = in_array('admin', $newRoles);
            
            if (
                !$newIsAdmin
                && $currentIsAdmin
                && $this->userModel->countActiveAdmins() <= 1
            ) {
                return [
                    'success'    => false,
                    'statusCode' => 422,
                    'blockCode'  => 'LAST_ADMIN',
                    'message'    => 'Cannot remove the admin role from the last active administrator. Promote another admin first.',
                ];
            }

            // Keep role sync data separate from xs_users update payload.
            $rolesToSync = $newRoles;
        } elseif (!empty($newRoles) && ($currentUser['role'] ?? '') !== 'admin' && (array_key_exists('role', $payload) || array_key_exists('roles', $payload))) {
            log_message('warning', "[UserManagementMutationService::updateUser] Non-admin user {$currentUserId} attempted to change role for user {$userId}");
        }

        // Determine primary role from roles list (for xs_users.role backward compat)
        $roleHierarchy = ['admin', 'provider', 'staff'];
        $finalRole = null;
        if (!empty($rolesToSync)) {
            foreach ($roleHierarchy as $hierarchyRole) {
                if (in_array($hierarchyRole, $rolesToSync, true)) {
                    $finalRole = $hierarchyRole;
                    break;
                }
            }
        } else {
            $finalRole = $updateData['role'] ?? ($existingUser['role'] ?? null);
        }

        // Only update role on xs_users if it changed
        if ($finalRole && $finalRole !== ($existingUser['role'] ?? null)) {
            $updateData['role'] = $finalRole;
        }

        $effectiveRoles = $rolesToSync;
        if (empty($effectiveRoles)) {
            $effectiveRoles = $this->userModel->getRolesForUser($userId);
            if (empty($effectiveRoles) && !empty($existingUser['role'])) {
                $effectiveRoles = [(string) $existingUser['role']];
            }
        }
        $hasProviderRole = in_array('provider', $effectiveRoles, true);

        $scheduleInput = $payload['schedule'] ?? [];
        $scheduleClean = [];
        if ($hasProviderRole) {
            $scheduleInput = $this->mergeMissingScheduleFieldsFromExisting($userId, $scheduleInput);

            if (($currentUser['role'] ?? '') === 'admin' && !empty($payload['color'])) {
                $updateData['color'] = $payload['color'];
            }

            [$scheduleClean, $scheduleErrors] = $this->scheduleValidation->validateProviderSchedule($scheduleInput);
            if (!empty($scheduleErrors)) {
                return [
                    'success' => false,
                    'statusCode' => 422,
                    'message' => 'Please fix the highlighted schedule issues.',
                    'errors' => $scheduleErrors,
                    'scheduleErrors' => $scheduleErrors,
                ];
            }
        }

        $db = \Config\Database::connect();
        $userRolesTable = $db->prefixTable('user_roles');
        $canSyncUserRoles = method_exists($db, 'tableExists')
            ? $db->tableExists('user_roles')
            : true;

        try {
            $db->transException(true)->transStart();

            if (!$this->userModel->updateUser($userId, $updateData, $currentUserId)) {
                $db->transRollback();

                return [
                    'success' => false,
                    'statusCode' => 400,
                    'message' => 'Failed to update user. Please try again.',
                ];
            }

            // Sync roles to xs_user_roles table if it exists and we have roles to update
            if (!empty($rolesToSync) && $canSyncUserRoles) {
                try {
                    // Delete existing roles for this user
                    $db->table($userRolesTable)->where('user_id', $userId)->delete();
                    
                    // Insert new roles
                    $now = date('Y-m-d H:i:s');
                    $roleRows = array_map(
                        static fn(string $role): array => [
                            'user_id' => $userId,
                            'role' => $role,
                            'created_at' => $now,
                        ],
                        $rolesToSync
                    );
                    $db->table($userRolesTable)->insertBatch($roleRows);
                } catch (\Throwable $e) {
                    log_message('warning', '[UserManagementMutationService::updateUser] Could not sync to xs_user_roles table: ' . $e->getMessage());
                    // Continue - xs_users.role will be used as fallback
                }
            }

            $changedFields = array_keys($updateData);
            if (isset($updateData['role']) && $updateData['role'] !== ($existingUser['role'] ?? null)) {
                $this->auditModel->log(
                    'role_changed',
                    $currentUserId,
                    'user',
                    $userId,
                    ['role' => $existingUser['role'] ?? null],
                    ['role' => $updateData['role']]
                );
            }

            if (isset($updateData['password'])) {
                $this->auditModel->log('password_reset', $currentUserId, 'user', $userId);
            }

            $this->auditModel->log(
                'user_updated',
                $currentUserId,
                'user',
                $userId,
                null,
                ['fields' => $changedFields]
            );

            if ($hasProviderRole) {
                $this->providerScheduleModel->saveSchedule($userId, $scheduleClean);
                $this->businessHourModel->syncFromProviderSchedule($userId, $scheduleClean);
                $this->syncLocationDaysFromSchedule($userId, $scheduleInput);
            } else {
                $this->providerScheduleModel->deleteByProvider($userId);
                $this->businessHourModel->syncFromProviderSchedule($userId, []);
            }

            $db->transComplete();
        } catch (\Throwable $exception) {
            if ($db->transStatus() !== false) {
                $db->transRollback();
            }

            log_message('error', '[UserManagementMutationService::updateUser] Exception updating user_id={userId}: {message}', [
                'userId' => $userId,
                'message' => $exception->getMessage(),
            ]);

            return [
                'success' => false,
                'statusCode' => 500,
                'message' => 'Failed to update user because provider schedule or location data could not be saved.',
            ];
        }

        $updatedUser = null;
        if ($currentUserId === $userId) {
            $updatedUser = $this->userModel->find($userId);
        }

        return [
            'success' => true,
            'message' => 'User updated successfully.',
            'redirect' => base_url('user-management/edit/' . $userId),
            'updatedUser' => $updatedUser,
        ];
    }

    public function deactivateUser(int $currentUserId, int $userId): array
    {
        if ($currentUserId === $userId) {
            return [
                'success' => false,
                'statusCode' => 400,
                'message' => 'You cannot deactivate your own account.',
            ];
        }

        $targetUser = $this->userModel->find($userId);
        if (!$targetUser) {
            return [
                'success' => false,
                'statusCode' => 404,
                'message' => 'User not found.',
            ];
        }

        // Last-admin deactivation guard
        if (($targetUser['role'] ?? '') === 'admin' && $this->userModel->countActiveAdmins() <= 1) {
            return [
                'success'    => false,
                'statusCode' => 422,
                'blockCode'  => 'LAST_ADMIN',
                'message'    => 'Cannot deactivate the last active administrator. Promote another admin first.',
            ];
        }

        if (!$this->userModel->deactivateUser($userId, $currentUserId)) {
            return [
                'success' => false,
                'statusCode' => 403,
                'message' => 'Failed to deactivate user or insufficient permissions.',
            ];
        }

        $this->auditModel->log('user_deactivated', $currentUserId, 'user', $userId, null, [
            'role' => $targetUser['role'] ?? null,
        ]);

        return [
            'success' => true,
            'message' => 'User deactivated successfully.',
            'redirect' => base_url('user-management'),
        ];
    }

    public function activateUser(int $currentUserId, int $userId): array
    {
        $targetUser = $this->userModel->find($userId);
        if (!$targetUser) {
            return [
                'success' => false,
                'statusCode' => 404,
                'message' => 'User not found.',
            ];
        }

        if (!$this->userModel->canManageUser($currentUserId, $userId)) {
            return [
                'success' => false,
                'statusCode' => 403,
                'message' => 'Failed to activate user or insufficient permissions.',
            ];
        }

        if (!$this->userModel->update($userId, $this->buildUserActiveUpdatePayload(true))) {
            return [
                'success' => false,
                'statusCode' => 400,
                'message' => 'Failed to activate user.',
            ];
        }

        $this->auditModel->log('user_activated', $currentUserId, 'user', $userId, null, [
            'role' => $targetUser['role'] ?? null,
        ]);

        return [
            'success' => true,
            'message' => 'User activated successfully.',
            'redirect' => base_url('user-management'),
        ];
    }

    private function syncLocationDaysFromSchedule(int $providerId, array $scheduleInput): void
    {
        $providerLocations = $this->locationModel->getProviderLocations($providerId);
        if (empty($providerLocations)) {
            return;
        }

        $locationDaysMap = [];
        foreach ($providerLocations as $location) {
            $locationDaysMap[(int) $location['id']] = [];
        }

        foreach ($scheduleInput as $dayName => $dayData) {
            if (!isset(LocationModel::DAY_NAME_TO_INT[$dayName])) {
                continue;
            }

            $dayInt = LocationModel::DAY_NAME_TO_INT[$dayName];
            $locationIds = $dayData['locations'] ?? [];
            foreach ($locationIds as $locationId) {
                $locationId = (int) $locationId;
                if (isset($locationDaysMap[$locationId])) {
                    $locationDaysMap[$locationId][] = $dayInt;
                }
            }
        }

        foreach ($locationDaysMap as $locationId => $days) {
            $this->locationModel->setLocationDays($locationId, $days, $providerId);
        }
    }

    private function mergeMissingScheduleFieldsFromExisting(int $providerId, array $scheduleInput): array
    {
        $existingSchedule = $this->providerScheduleModel->getByProvider($providerId);
        if ($existingSchedule === []) {
            return $scheduleInput;
        }

        if ($scheduleInput === []) {
            return $existingSchedule;
        }

        foreach ($existingSchedule as $day => $existingRow) {
            if (!isset($scheduleInput[$day]) || !is_array($scheduleInput[$day])) {
                $scheduleInput[$day] = $existingRow;
                continue;
            }

            $row = $scheduleInput[$day];
            $isActive = !empty($row['is_active']) && (string) $row['is_active'] !== '0';
            if (!$isActive) {
                continue;
            }

            foreach (['start_time', 'end_time', 'break_start', 'break_end'] as $field) {
                $submittedValue = $row[$field] ?? null;
                $isBlankString = is_string($submittedValue) && trim($submittedValue) === '';

                if ((!array_key_exists($field, $row) || $isBlankString) && array_key_exists($field, $existingRow)) {
                    $scheduleInput[$day][$field] = $existingRow[$field];
                }
            }
        }

        return $scheduleInput;
    }

    /**
     * Build active-state update payload for current xs_users schema.
     */
    private function buildUserActiveUpdatePayload(bool $isActive): array
    {
        $db = \Config\Database::connect();
        $hasIsActive = method_exists($db, 'fieldExists') ? $db->fieldExists('is_active', 'xs_users') : true;
        $hasStatus = method_exists($db, 'fieldExists') ? $db->fieldExists('status', 'xs_users') : true;

        if ($hasIsActive) {
            return ['is_active' => $isActive ? 1 : 0];
        }

        if ($hasStatus) {
            return ['status' => $isActive ? 'active' : 'inactive'];
        }

        return [];
    }
}