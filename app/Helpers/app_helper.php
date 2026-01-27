<?php
// app/Helpers/app_helper.php

use App\Models\SettingModel;

if (!function_exists('setting')) {
    /**
     * Get a single setting value by key with optional default.
     */
    function setting(string $key, $default = null)
    {
        static $cache = [];
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        try {
            $model = new SettingModel();
            $vals = $model->getByKeys([$key]);
            $val = $vals[$key] ?? $default;
            $cache[$key] = $val;
            return $val;
        } catch (\Throwable $e) {
            return $default;
        }
    }
}

if (!function_exists('provider_image_url')) {
    function provider_image_url(?string $path): ?string
    {
        if (!$path) return null;
        $path = ltrim($path, '/');
        if (str_starts_with($path, 'assets/providers/')) return base_url($path);
        if (str_starts_with($path, 'uploads/providers/')) return base_url('assets/p/' . basename($path));
        return base_url('writable/' . $path);
    }
}

if (!function_exists('settings_by_prefix')) {
    /**
     * Get settings by prefix (e.g., 'general.') returning associative array.
     */
    function settings_by_prefix(string $prefix): array
    {
        try {
            $model = new SettingModel();
            return $model->getByPrefix($prefix);
        } catch (\Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('setting_url')) {
    /**
     * Build a public URL for a writable-stored asset path saved in settings.
     * E.g., setting value 'uploads/settings/logo.png' => base_url('writable/uploads/settings/logo.png')
     */
    function setting_url(string $key, $default = null): ?string
    {
        try {
            $sfm = new \App\Models\SettingFileModel();
            $row = $sfm->getByKey($key);
            if ($row && !empty($row['data'])) {
                return base_url('assets/db/' . rawurlencode($key));
            }
        } catch (\Throwable $e) {
            // Silently fall back to file-based lookup
        }

        $path = setting($key, $default);
        if (!$path || !is_string($path)) return null;
        if (is_string($path) && str_starts_with($path, 'db://')) {
            return base_url('assets/db/' . rawurlencode($key));
        }
        $path = ltrim((string) $path, '/');
        if (strpos($path, 'uploads/settings/') === 0) {
            $filename = basename($path);
            return base_url('assets/s/' . $filename);
        }
        if (strpos($path, 'assets/settings/') === 0 || strpos($path, 'assets/providers/') === 0) {
            return base_url($path);
        }
        return base_url('writable/' . $path);
    }
}

if (!function_exists('get_role_display_name')) {
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
    function can_access_route(string $route, array $requiredRoles = [], array $requiredPermissions = []): bool
    {
        if (!empty($requiredRoles) && !has_role($requiredRoles)) {
            return false;
        }
        if (!empty($requiredPermissions) && !has_permission($requiredPermissions)) {
            return false;
        }
        return true;
    }
}
if (!function_exists('role_badge_class')) {
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
