<?php

namespace App\Services;

use App\Models\LocationModel;
use App\Models\ProviderScheduleModel;
use App\Models\ProviderStaffModel;
use App\Models\UserModel;
use App\Models\UserPermissionModel;

class UserManagementContextService
{
    private UserModel $userModel;
    private UserPermissionModel $permissionModel;
    private ProviderStaffModel $providerStaffModel;
    private ProviderScheduleModel $providerScheduleModel;
    private LocalizationSettingsService $localization;
    private ScheduleValidationService $scheduleValidation;

    public function __construct(
        ?UserModel $userModel = null,
        ?UserPermissionModel $permissionModel = null,
        ?ProviderStaffModel $providerStaffModel = null,
        ?ProviderScheduleModel $providerScheduleModel = null,
        ?LocalizationSettingsService $localization = null,
        ?ScheduleValidationService $scheduleValidation = null,
    ) {
        $this->userModel = $userModel ?? new UserModel();
        $this->permissionModel = $permissionModel ?? new UserPermissionModel();
        $this->providerStaffModel = $providerStaffModel ?? new ProviderStaffModel();
        $this->providerScheduleModel = $providerScheduleModel ?? new ProviderScheduleModel();
        $this->localization = $localization ?? new LocalizationSettingsService();
        $this->scheduleValidation = $scheduleValidation ?? new ScheduleValidationService($this->localization);
    }

    public function buildIndexViewData(int $currentUserId, array $currentUser): array
    {
        $users = [];
        $stats = ['total' => 0, 'admins' => 0, 'providers' => 0, 'staff' => 0, 'recent' => 0];

        try {
            $users = $this->getUsersBasedOnRole($currentUserId);
            $stats = $this->getUserStatsBasedOnRole($currentUserId, $users);
        } catch (\Throwable $e) {
            log_message('warning', 'UserManagementContextService::buildIndexViewData failed: ' . $e->getMessage());
        }

        return [
            'title' => 'User Management - WebSchedulr',
            'currentUser' => $currentUser,
            'users' => $users,
            'stats' => $stats,
            'canCreateAdmin' => $this->permissionModel->hasPermission($currentUserId, 'create_admin'),
            'canCreateProvider' => $this->permissionModel->hasPermission($currentUserId, 'create_provider'),
            'canCreateStaff' => $this->permissionModel->hasPermission($currentUserId, 'create_staff'),
        ];
    }

    public function buildCreateViewData(
        int $currentUserId,
        array $currentUser,
        array $scheduleInput,
        array $scheduleErrors,
        mixed $validation,
        array $scheduleDays,
    ): array {
        $availableRoles = $this->getAvailableRolesForUser($currentUserId);
        $providers = [];
        if (in_array('staff', $availableRoles, true)) {
            $providers = $this->userModel->getProviders();
        }

        $availableStaff = [];
        if (in_array('provider', $availableRoles, true)) {
            $availableStaff = $this->userModel
                ->where('role', 'staff')
                ->where('is_active', true)
                ->orderBy('name', 'ASC')
                ->findAll();
        }

        return [
            'title' => 'Create User - WebSchedulr',
            'currentUser' => $currentUser,
            'availableRoles' => $availableRoles,
            'providers' => $providers,
            'availableStaff' => $availableStaff,
            'assignedStaff' => [],
            'assignedProviders' => [],
            'canManageAssignments' => ($currentUser['role'] ?? '') === 'admin',
            'stats' => $this->getUserStatsBasedOnRole($currentUserId),
            'validation' => $validation,
            'providerSchedule' => $this->scheduleValidation->prepareScheduleForView($scheduleInput),
            'scheduleDays' => $scheduleDays,
            'scheduleErrors' => $scheduleErrors,
            'localizationContext' => $this->localization->getContext(),
            'timeFormatExample' => $this->localization->getFormatExample(),
        ];
    }

    public function buildEditViewData(
        int $currentUserId,
        array $currentUser,
        array $user,
        array $scheduleInput,
        array $scheduleErrors,
        mixed $validation,
        array $scheduleDays,
    ): array {
        $availableRoles = $this->getAvailableRolesForUser($currentUserId, $user);
        $providers = [];
        if (in_array('staff', $availableRoles, true) || ($user['role'] ?? '') === 'staff') {
            $providers = $this->userModel->getProviders();
        }

        $assignedStaff = [];
        $availableStaff = [];
        $assignedProviders = [];
        $availableProviders = [];
        $canManageAssignments = ($currentUser['role'] ?? '') === 'admin';

        if (($user['role'] ?? '') === 'provider'
            && ($currentUser['role'] ?? '') === 'provider'
            && (int) $currentUserId === (int) ($user['id'] ?? 0)) {
            $canManageAssignments = true;
        }

        if (($user['role'] ?? '') === 'provider') {
            $assignedStaff = $this->providerStaffModel->getStaffByProvider((int) $user['id']);

            if ($canManageAssignments) {
                $availableStaff = $this->userModel
                    ->where('role', 'staff')
                    ->where('is_active', true)
                    ->orderBy('name', 'ASC')
                    ->findAll();
            }
        } elseif (($user['role'] ?? '') === 'staff') {
            $assignedProviders = $this->providerStaffModel->getProvidersForStaff((int) $user['id']);

            if ($canManageAssignments) {
                $availableProviders = $this->userModel
                    ->where('role', 'provider')
                    ->where('is_active', true)
                    ->orderBy('name', 'ASC')
                    ->findAll();
            }
        }

        $providerLocations = [];
        if (($user['role'] ?? '') === 'provider') {
            $providerLocations = (new LocationModel())->getProviderLocationsWithDays((int) $user['id']);
        }

        return [
            'title' => 'Edit User - WebSchedulr',
            'currentUser' => $currentUser,
            'user' => $user,
            'userId' => (int) $user['id'],
            'providerId' => (int) $user['id'],
            'staffId' => (int) $user['id'],
            'availableRoles' => $availableRoles,
            'providers' => $providers,
            'validation' => $validation,
            'providerSchedule' => $this->scheduleValidation->prepareScheduleForView($scheduleInput),
            'scheduleDays' => $scheduleDays,
            'scheduleErrors' => $scheduleErrors,
            'assignedStaff' => $assignedStaff,
            'availableStaff' => $availableStaff,
            'assignedProviders' => $assignedProviders,
            'availableProviders' => $availableProviders,
            'canManageAssignments' => $canManageAssignments,
            'providerLocations' => $providerLocations,
            'localizationContext' => $this->localization->getContext(),
            'timeFormatExample' => $this->localization->getFormatExample(),
        ];
    }

    public function getUserStats(int $currentUserId): array
    {
        try {
            return $this->getUserStatsBasedOnRole($currentUserId);
        } catch (\Throwable $e) {
            log_message('warning', 'UserManagementContextService::getUserStats failed: ' . $e->getMessage());
            return ['total' => 0, 'admins' => 0, 'providers' => 0, 'staff' => 0, 'recent' => 0];
        }
    }

    public function getApiUsers(int $currentUserId, ?string $role = null): array
    {
        try {
            $users = $this->getUsersBasedOnRole($currentUserId);
        } catch (\Throwable $e) {
            log_message('warning', 'UserManagementContextService::getApiUsers failed: ' . $e->getMessage());
            return ['items' => [], 'total' => 0];
        }

        if ($role && in_array($role, ['admin', 'provider', 'staff'], true)) {
            $users = array_values(array_filter($users, static fn($user) => ($user['role'] ?? '') === $role));
        }

        return [
            'items' => $users,
            'total' => count($users),
        ];
    }

    public function getAvailableRolesForUser(int $currentUserId, ?array $targetUser = null): array
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

        return $roles;
    }

    public function canCreateRole(int $currentUserId, string $role): bool
    {
        return match ($role) {
            'admin' => $this->permissionModel->hasPermission($currentUserId, 'create_admin'),
            'provider' => $this->permissionModel->hasPermission($currentUserId, 'create_provider'),
            'staff' => $this->permissionModel->hasPermission($currentUserId, 'create_staff'),
            default => false,
        };
    }

    public function canChangeUserRole(int $currentUserId, int $targetUserId): bool
    {
        $currentUser = $this->userModel->find($currentUserId);
        return ($currentUser['role'] ?? null) === 'admin';
    }

    public function getStoreValidationRules(): array
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

    public function getUpdateValidationRules(int $userId, bool $includePasswordRules, bool $canChangeRole): array
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

    public function resolveManageableUser(
        int $currentUserId,
        int $targetUserId,
        string $missingMessage = 'User not found.',
        string $forbiddenMessage = 'You do not have permission to edit this user.'
    ): array {
        $user = $this->userModel->find($targetUserId);
        if (!$user) {
            return [
                'success' => false,
                'statusCode' => 404,
                'message' => $missingMessage,
            ];
        }

        if (!$this->userModel->canManageUser($currentUserId, $targetUserId)) {
            return [
                'success' => false,
                'statusCode' => 403,
                'message' => $forbiddenMessage,
            ];
        }

        return [
            'success' => true,
            'user' => $user,
        ];
    }

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
                $users = [$currentUser];
                break;

            default:
                return [];
        }

        return $this->enrichUsersWithAssignments($users);
    }

    private function enrichUsersWithAssignments(array $users): array
    {
        if (empty($users)) {
            return $users;
        }

        $db = $this->userModel->db;
        $providerIds = [];
        $staffIds = [];

        foreach ($users as $user) {
            if (($user['role'] ?? null) === 'provider') {
                $providerIds[] = $user['id'];
            } elseif (($user['role'] ?? null) === 'staff') {
                $staffIds[] = $user['id'];
            }
        }

        $staffConcat = "GROUP_CONCAT(DISTINCT staff.name ORDER BY staff.name SEPARATOR ', ') AS staff_names";

        $providerConcat = "GROUP_CONCAT(DISTINCT provider.name ORDER BY provider.name SEPARATOR ', ') AS provider_names";

        $providerAssignments = [];
        if (!empty($providerIds)) {
            try {
                $results = $db->table('xs_provider_staff_assignments AS psa')
                    ->select("psa.provider_id, {$staffConcat}", false)
                    ->join('xs_users AS staff', 'staff.id = psa.staff_id', 'left')
                    ->whereIn('psa.provider_id', $providerIds)
                    ->groupBy('psa.provider_id')
                    ->get()
                    ->getResultArray();

                foreach ($results as $row) {
                    $providerAssignments[$row['provider_id']] = $row['staff_names'];
                }
            } catch (\Throwable $e) {
                log_message('warning', 'UserManagementContextService::enrichUsersWithAssignments provider query failed: ' . $e->getMessage());
            }
        }

        $staffAssignments = [];
        if (!empty($staffIds)) {
            try {
                $results = $db->table('xs_provider_staff_assignments AS psa')
                    ->select("psa.staff_id, {$providerConcat}", false)
                    ->join('xs_users AS provider', 'provider.id = psa.provider_id', 'left')
                    ->whereIn('psa.staff_id', $staffIds)
                    ->groupBy('psa.staff_id')
                    ->get()
                    ->getResultArray();

                foreach ($results as $row) {
                    $staffAssignments[$row['staff_id']] = $row['provider_names'];
                }
            } catch (\Throwable $e) {
                log_message('warning', 'UserManagementContextService::enrichUsersWithAssignments staff query failed: ' . $e->getMessage());
            }
        }

        foreach ($users as &$user) {
            if (($user['role'] ?? null) === 'provider') {
                $user['assignments'] = $providerAssignments[$user['id']] ?? null;
            } elseif (($user['role'] ?? null) === 'staff') {
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

        if (($currentUser['role'] ?? null) === 'admin') {
            $rows = $usersForContext ?: $this->userModel->whereIn('role', ['admin', 'provider', 'staff'])->findAll();
            $admins = 0;
            $providers = 0;
            $staff = 0;

            foreach ($rows as $user) {
                $role = $user['role'] ?? '';
                if ($role === 'admin') {
                    $admins++;
                } elseif ($role === 'provider') {
                    $providers++;
                } elseif ($role === 'staff') {
                    $staff++;
                }
            }

            return [
                'total' => $admins + $providers + $staff,
                'admins' => $admins,
                'providers' => $providers,
                'staff' => $staff,
                'recent' => 0,
            ];
        }

        if (($currentUser['role'] ?? null) === 'provider') {
            $staff = $this->userModel->getStaffForProvider($currentUserId);
            return [
                'total' => count($staff) + 1,
                'staff' => count($staff),
                'providers' => 1,
                'admins' => 0,
                'recent' => 0,
            ];
        }

        return ['total' => 1, 'staff' => 0, 'providers' => 0, 'admins' => 0, 'recent' => 0];
    }
}