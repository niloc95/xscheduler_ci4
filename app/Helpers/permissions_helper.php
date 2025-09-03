<?php

if (!function_exists('current_user_role')) {
    /**
     * Get current user's role
     */
    function current_user_role(): ?string
    {
        $user = session()->get('user');
        return $user['role'] ?? null;
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

if (!function_exists('has_role')) {
    /**
     * Check if current user has a specific role
     */
    function has_role(string|array $roles): bool
    {
        $currentRole = current_user_role();
        if (!$currentRole) {
            return false;
        }

        if (is_string($roles)) {
            return $currentRole === $roles;
        }

        return in_array($currentRole, $roles);
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

if (!function_exists('is_customer')) {
    /**
     * Check if current user is a customer
     */
    function is_customer(): bool
    {
        return has_role('customer');
    }
}

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
            case 'customer':
                return has_permission('user_management');
            default:
                return false;
        }
    }
}

if (!function_exists('get_role_display_name')) {
    /**
     * Get display name for a role
     */
    function get_role_display_name(string $role): string
    {
        $names = [
            'admin' => 'Administrator',
            'provider' => 'Service Provider',
            'staff' => 'Staff Member',
            'customer' => 'Customer'
        ];

        return $names[$role] ?? ucfirst($role);
    }
}

if (!function_exists('get_role_permissions_description')) {
    /**
     * Get description of permissions for a role
     */
    function get_role_permissions_description(string $role): string
    {
        $descriptions = [
            'admin' => 'Full system access including settings, user management, and all features.',
            'provider' => 'Can manage own calendar, create staff, manage services and categories.',
            'staff' => 'Limited to managing own calendar and assigned appointments.',
            'customer' => 'Can book appointments and view own booking history.'
        ];

        return $descriptions[$role] ?? 'Unknown role';
    }
}

if (!function_exists('get_user_hierarchy')) {
    /**
     * Get users that the current user can manage/view
     */
    function get_user_hierarchy(): array
    {
        $userId = current_user_id();
        if (!$userId) {
            return [];
        }

        $permissionModel = new \App\Models\UserPermissionModel();
        return $permissionModel->getUserHierarchy($userId);
    }
}

if (!function_exists('can_access_route')) {
    /**
     * Check if current user can access a specific route/controller action
     */
    function can_access_route(string $route, array $requiredRoles = [], array $requiredPermissions = []): bool
    {
        // Check role requirements
        if (!empty($requiredRoles) && !has_role($requiredRoles)) {
            return false;
        }

        // Check permission requirements
        if (!empty($requiredPermissions) && !has_permission($requiredPermissions)) {
            return false;
        }

        return true;
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
            'staff' => 'bg-warning',
            'customer' => 'bg-secondary'
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
            'staff' => 'bg-green-500',
            'customer' => 'bg-gray-500'
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
            'staff' => 'fas fa-user-friends',
            'customer' => 'fas fa-user'
        ];

        return $icons[$role] ?? 'fas fa-user';
    }
}
