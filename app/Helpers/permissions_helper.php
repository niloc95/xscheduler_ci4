<?php

/**
 * =============================================================================
 * PERMISSIONS HELPER
 * =============================================================================
 * 
 * @file        app/Helpers/permissions_helper.php
 * @description Global helper functions for checking user roles and permissions.
 *              Provides convenient access to authorization checks in views.
 * 
 * LOADING:
 * -----------------------------------------------------------------------------
 * Loaded automatically via BaseController or manually:
 *     helper('permissions');
 * 
 * AVAILABLE FUNCTIONS:
 * -----------------------------------------------------------------------------
 * current_user_role()          : Get current user's role string
 * current_user_id()            : Get current user's ID
 * current_business_id()        : Get current business ID
 * has_role($roles)             : Check if user has specific role(s)
 * has_permission($perms)       : Check if user has permission(s)
 * is_admin()                   : Check if user is admin
 * is_provider()                : Check if user is provider
 * is_staff()                   : Check if user is staff
 * can_access_user($targetId)   : Check if can access user's data
 * can_manage_appointment($appt): Check if can manage appointment
 * 
 * USAGE IN VIEWS:
 * -----------------------------------------------------------------------------
 *     <?php if (has_role('admin')): ?>
 *         <a href="/admin">Admin Panel</a>
 *     <?php endif; ?>
 * 
 *     <?php if (has_permission('manage_users')): ?>
 *         <button>Add User</button>
 *     <?php endif; ?>
 * 
 * USAGE IN CONTROLLERS:
 * -----------------------------------------------------------------------------
 *     helper('permissions');
 *     if (!has_role(['admin', 'provider'])) {
 *         return redirect()->to(base_url('dashboard'));
 *     }
 * 
 * @see         app/Models/UserPermissionModel.php for permission definitions
 * @see         app/Services/AuthorizationService.php for complex checks
 * @package     App\Helpers
 * @author      Nilesh Nagin Cara
 * @copyright   2024-2026 Nilesh Nagin Cara
 * =============================================================================
 */

require_once APPPATH . 'Helpers/app_helper.php';

if (!function_exists('current_user_role')) {
    /**
     * Get current user's active role
     * Falls back to primary role for backward compatibility
     */
    function current_user_role(): ?string
    {
        $user = session()->get('user');
        return $user['active_role'] ?? $user['role'] ?? null;
    }
}

if (!function_exists('current_user_id')) {
    /**
     * Get current user's ID
     */
    function current_user_id(): ?int
    {
        return session()->get('user_id');
    }
}

if (!function_exists('current_business_id')) {
    /**
     * Get the active business ID from request or session context.
     */
    function current_business_id(?int $default = null): int
    {
        $fallback = $default ?? \App\Services\NotificationCatalog::BUSINESS_ID_DEFAULT;

        $request = null;
        try {
            $request = service('request');
        } catch (\Throwable $e) {
            $request = null;
        }

        $sessionUser = session()->get('user');
        $sessionUser = is_array($sessionUser) ? $sessionUser : [];

        $candidates = [
            $request?->getGet('business_id'),
            $request?->getGet('businessId'),
            $request?->getPost('business_id'),
            $request?->getPost('businessId'),
            session()->get('business_id'),
            session()->get('active_business_id'),
            $sessionUser['business_id'] ?? null,
            $sessionUser['active_business_id'] ?? null,
            $sessionUser['businessId'] ?? null,
            $sessionUser['activeBusinessId'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_numeric($candidate) && (int) $candidate > 0) {
                return (int) $candidate;
            }
        }

        return max(1, (int) $fallback);
    }
}

if (!function_exists('has_role')) {
    /**
     * Check if current user has a specific role.
     * Reads the authoritative 'roles' array from session first (§4.4 Canonical RBAC Pattern).
     * Falls back to active_role / role for compatibility with older session payloads.
     */
    function has_role(string|array $roles): bool
    {
        $user = session()->get('user');
        if (!$user) {
            return false;
        }

        // §4.4: read authoritative roles array; fall back to single-role for compatibility
        $userRoles = $user['roles'] ?? [$user['active_role'] ?? $user['role'] ?? ''];
        $requiredRoles = is_string($roles) ? [$roles] : $roles;

        return !empty(array_intersect($requiredRoles, $userRoles));
    }
}

if (!function_exists('has_permission')) {
    /**
     * Check if current user has a specific permission
     */
    function has_permission(string|array $permissions): bool
    {
        $userId = current_user_id();
        if (!$userId) {
            return false;
        }

        $permissionModel = new \App\Models\UserPermissionModel();
        
        if (is_string($permissions)) {
            return $permissionModel->hasPermission($userId, $permissions);
        }

        return $permissionModel->hasAnyPermission($userId, $permissions);
    }
}

if (!function_exists('is_admin')) {
    /**
     * Check if current user is an admin
     */
    function is_admin(): bool
    {
        return has_role('admin');
    }
}

if (!function_exists('is_provider')) {
    /**
     * Check if current user is a provider
     */
    function is_provider(): bool
    {
        return has_role('provider');
    }
}

if (!function_exists('is_staff')) {
    /**
     * Check if current user is staff
     */
    function is_staff(): bool
    {
        return has_role('staff');
    }
}

// Deprecated: customers are no longer users; do not check is_customer()

if (!function_exists('can_manage_users')) {
    /**
     * Check if current user can manage other users
     */
    function can_manage_users(): bool
    {
        return has_permission('user_management');
    }
}

if (!function_exists('can_manage_settings')) {
    /**
     * Check if current user can manage system settings
     */
    function can_manage_settings(): bool
    {
        return has_permission('system_settings');
    }
}

if (!function_exists('can_create_role')) {
    /**
     * Check if current user can create users with a specific role
     */
    function can_create_role(string $role): bool
    {
        switch ($role) {
            case 'admin':
                return has_permission('create_admin');
            case 'provider':
                return has_permission('create_provider');
            case 'staff':
                return has_permission('create_staff');
            default:
                return false;
        }
    }
}

if (!function_exists('role_badge_class')) {
    /**
     * Get Bootstrap badge class for a role
     */
    function role_badge_class(string $role): string
    {
        $classes = [
            'admin' => 'bg-danger',
            'provider' => 'bg-info',
            'staff' => 'bg-warning'
        ];

        return $classes[$role] ?? 'bg-secondary';
    }
}

if (!function_exists('get_role_badge_tailwind_class')) {
    /**
     * Get Tailwind CSS classes for role badge
     */
    function get_role_badge_tailwind_class(string $role): string
    {
        $classes = [
            'admin' => 'bg-red-500',
            'provider' => 'bg-blue-500',
            'staff' => 'bg-green-500'
        ];

        return $classes[$role] ?? 'bg-gray-400';
    }
}

if (!function_exists('role_icon')) {
    /**
     * Get FontAwesome icon for a role
     */
    function role_icon(string $role): string
    {
        $icons = [
            'admin' => 'fas fa-user-shield',
            'provider' => 'fas fa-user-tie',
            'staff' => 'fas fa-user-friends'
        ];

        return $icons[$role] ?? 'fas fa-user';
    }
}
