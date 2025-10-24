<?php

namespace App\Models;

use CodeIgniter\Model;
use App\Models\ProviderStaffModel;

class UserPermissionModel extends Model
{
    protected $table            = 'xs_users';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    
    // Define role-based permissions
    const ROLE_PERMISSIONS = [
        'admin' => [
            'system_settings',
            'user_management', 
            'create_admin',
            'create_provider',
            'create_staff',
            'manage_services',
            'manage_categories',
            'view_all_appointments',
            'manage_all_calendars',
            'system_analytics',
            'backup_restore'
        ],
        'provider' => [
            'manage_own_calendar',
            'create_staff',
            'manage_own_staff',
            'manage_services',
            'manage_categories', 
            'view_own_appointments',
            'manage_own_appointments',
            'view_staff_calendars',
            'manage_staff_calendars',
            'provider_analytics'
        ],
        'staff' => [
            'manage_own_calendar',
            'view_own_appointments',
            'manage_own_appointments',
            'create_appointments',
            'view_assigned_services',
            'basic_profile_edit'
        ]
    ];

    /**
     * Get user permissions based on role
     */
    public function getUserPermissions(int $userId): array
    {
        $user = $this->find($userId);
        if (!$user) {
            return [];
        }

        $rolePermissions = self::ROLE_PERMISSIONS[$user['role']] ?? [];
        
        // Add any custom permissions from the permissions JSON field
        $customPermissions = [];
        if (!empty($user['permissions'])) {
            $customPermissions = json_decode($user['permissions'], true) ?? [];
        }

        return array_unique(array_merge($rolePermissions, $customPermissions));
    }

    /**
     * Check if user has specific permission
     */
    public function hasPermission(int $userId, string $permission): bool
    {
        $permissions = $this->getUserPermissions($userId);
        return in_array($permission, $permissions);
    }

    /**
     * Check if user has any of the specified permissions
     */
    public function hasAnyPermission(int $userId, array $permissions): bool
    {
        $userPermissions = $this->getUserPermissions($userId);
        return !empty(array_intersect($permissions, $userPermissions));
    }

    /**
     * Get users by role
     */
    public function getUsersByRole(string $role): array
    {
        return $this->where('role', $role)
                    ->where('is_active', true)
                    ->findAll();
    }

    /**
     * Get staff members for a provider
     */
    public function getStaffForProvider(int $providerId): array
    {
        $assignments = new ProviderStaffModel();
        return $assignments->getStaffByProvider($providerId);
    }

    /**
     * Get providers (excluding staff and customers)
     */
    public function getProviders(): array
    {
        return $this->whereIn('role', ['admin', 'provider'])
                    ->where('is_active', true)
                    ->findAll();
    }

    /**
     * Check if user can manage another user
     */
    public function canManageUser(int $managerId, int $targetUserId): bool
    {
        $manager = $this->find($managerId);
        $target = $this->find($targetUserId);

        if (!$manager || !$target) {
            return false;
        }

        // Admins can manage everyone
        if ($manager['role'] === 'admin') {
            return true;
        }

        // Providers can manage their own staff
        if ($manager['role'] === 'provider' && $target['role'] === 'staff') {
            $assignments = new ProviderStaffModel();
            return $assignments->isStaffAssignedToProvider($targetUserId, $managerId);
        }

        // Users can manage themselves (limited)
        if ($managerId === $targetUserId) {
            return true;
        }

        return false;
    }

    /**
     * Get user hierarchy (who can see/manage whom)
     */
    public function getUserHierarchy(int $userId): array
    {
        $user = $this->find($userId);
        if (!$user) {
            return [];
        }

        $managedUsers = [];

        switch ($user['role']) {
            case 'admin':
                // Admins can see everyone
                $managedUsers = $this->where('is_active', true)->findAll();
                break;
                
            case 'provider':
                // Providers can see their staff and themselves
                $assignments = new ProviderStaffModel();
                $staff = $assignments->getStaffByProvider($userId);
                $managedUsers = array_merge([$user], $staff);
                break;
                
            case 'staff':
                // Staff and customers can only see themselves
                $managedUsers = [$user];
                break;
        }

        return $managedUsers;
    }

    /**
     * Add custom permission to user
     */
    public function addPermission(int $userId, string $permission): bool
    {
        $user = $this->find($userId);
        if (!$user) {
            return false;
        }

        $currentPermissions = json_decode($user['permissions'] ?? '[]', true) ?? [];
        
        if (!in_array($permission, $currentPermissions)) {
            $currentPermissions[] = $permission;
            return $this->update($userId, [
                'permissions' => json_encode($currentPermissions)
            ]);
        }

        return true;
    }

    /**
     * Remove custom permission from user
     */
    public function removePermission(int $userId, string $permission): bool
    {
        $user = $this->find($userId);
        if (!$user) {
            return false;
        }

        $currentPermissions = json_decode($user['permissions'] ?? '[]', true) ?? [];
        $updatedPermissions = array_filter($currentPermissions, fn($p) => $p !== $permission);

        return $this->update($userId, [
            'permissions' => json_encode(array_values($updatedPermissions))
        ]);
    }
}
