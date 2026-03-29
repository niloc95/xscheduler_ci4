<?php

/**
 * =============================================================================
 * USER PERMISSION MODEL
 * =============================================================================
 * 
 * @file        app/Models/UserPermissionModel.php
 * @description Permission management for role-based access control (RBAC).
 *              Defines and checks user permissions based on roles.
 * 
 * DATABASE TABLE: xs_users (permissions stored as JSON in permissions column)
 * 
 * ROLE HIERARCHY:
 * -----------------------------------------------------------------------------
 * admin > provider > staff > (customer)
 * 
 * ADMIN PERMISSIONS:
 * -----------------------------------------------------------------------------
 * - system_settings      : Configure system settings
 * - user_management      : Manage all users
 * - create_admin         : Create admin accounts
 * - create_provider      : Create provider accounts
 * - create_staff         : Create staff accounts
 * - manage_services      : Manage services/categories
 * - view_all_appointments: See all appointments
 * - manage_all_calendars : Edit any calendar
 * - system_analytics     : View analytics
 * - backup_restore       : Database backup/restore
 * 
 * PROVIDER PERMISSIONS:
 * -----------------------------------------------------------------------------
 * - manage_own_calendar  : Edit own schedule
 * - create_staff         : Create staff for self
 * - manage_own_staff     : Manage assigned staff
 * - manage_services      : Manage services offered
 * - view_own_appointments: See own appointments
 * - view_staff_calendars : See assigned staff calendars
 * 
 * STAFF PERMISSIONS:
 * -----------------------------------------------------------------------------
 * - view_own_calendar    : View own assignments
 * - view_own_appointments: View own appointments
 * - book_appointments    : Create appointments
 * - view_assigned_providers: See provider info
 * 
 * KEY METHODS:
 * -----------------------------------------------------------------------------
 * - hasPermission(userId, permission) : Check single permission
 * - getPermissions(userId)            : Get all user permissions
 * - getRolePermissions(role)          : Get role's default permissions
 * - can(permission)                   : Helper for current user
 * 
 * @see         app/Helpers/permissions_helper.php for helper functions
 * @see         app/Filters/RoleFilter.php for route protection
 * @package     App\Models
 * @extends     CodeIgniter\Model
 * @author      Nilesh Nagin Cara
 * @copyright   2024-2026 Nilesh Nagin Cara
 * =============================================================================
 */

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
        ],
        'customer' => [
            'view_own_appointments',
            'basic_profile_edit'
        ]
    ];

    /**
     * Normalize legacy or aliased role names to canonical RBAC roles.
     */
    private function normalizeRole(?string $role): string
    {
        $value = strtolower(trim((string) $role));

        return match ($value) {
            'owner', 'superadmin', 'super_admin' => 'admin',
            'employee' => 'staff',
            default => $value,
        };
    }

    /**
     * Return a builder filtered to active users across mixed xs_users schemas.
     */
    private function applyActiveUserFilter($builder)
    {
        $hasIsActive = method_exists($this->db, 'fieldExists') ? $this->db->fieldExists('is_active', $this->table) : true;
        $hasStatus = method_exists($this->db, 'fieldExists') ? $this->db->fieldExists('status', $this->table) : true;

        if ($hasIsActive) {
            $builder->where('is_active', true);
        } elseif ($hasStatus) {
            $builder->where('status', 'active');
        }

        return $builder;
    }

    /**
     * Get user permissions based on role
     */
    public function getUserPermissions(int $userId): array
    {
        $user = $this->find($userId);
        if (!$user) {
            return [];
        }

        $role = $this->normalizeRole($user['role'] ?? '');
        $rolePermissions = self::ROLE_PERMISSIONS[$role] ?? [];
        
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
        $builder = $this->where('role', $this->normalizeRole($role));
        $this->applyActiveUserFilter($builder);

        return $builder->findAll();
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
        $builder = $this->whereIn('role', ['admin', 'provider']);
        $this->applyActiveUserFilter($builder);

        return $builder->findAll();
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
        $managerRole = $this->normalizeRole($manager['role'] ?? '');
        $targetRole = $this->normalizeRole($target['role'] ?? '');

        if ($managerRole === 'admin') {
            return true;
        }

        // Providers can manage their own staff
        if ($managerRole === 'provider' && $targetRole === 'staff') {
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

        switch ($this->normalizeRole($user['role'] ?? '')) {
            case 'admin':
                // Admins can see everyone
            $builder = $this->builder();
            $this->applyActiveUserFilter($builder);
            $managedUsers = $builder->get()->getResultArray();
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
