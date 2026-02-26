<?php

/**
 * =============================================================================
 * APP HELPER
 * =============================================================================
 * 
 * @file        app/Helpers/app_helper.php
 * @description Core application helper functions for settings and asset URLs.
 *              Provides convenient access to application settings from anywhere.
 * 
 * LOADING:
 * -----------------------------------------------------------------------------
 * Loaded automatically via BaseController or manually:
 *     helper('app');
 * 
 * AVAILABLE FUNCTIONS:
 * -----------------------------------------------------------------------------
 * setting($key, $default)
 *   Get a setting value by key with caching
 *   Example: setting('general.business_name', 'WebSchedulr')
 * 
 * settings_by_prefix($prefix)
 *   Get all settings matching a prefix
 *   Example: settings_by_prefix('booking.') => ['booking.enabled' => '1', ...]
 * 
 * setting_url($key, $default)
 *   Build URL for writable-stored asset path from settings
 *   Example: setting_url('general.logo') => '/writable/uploads/logo.png'
 * 
 * provider_image_url($path)
 *   Build URL for provider profile images
 *   Example: provider_image_url('uploads/providers/photo.jpg')
 * 
 * get_setting($key, $default)  [deprecated]
 *   Legacy wrapper for setting()
 * 
 * CACHING:
 * -----------------------------------------------------------------------------
 * The setting() function uses static caching within a request to avoid
 * repeated database queries for the same setting key.
 * 
 * @see         app/Models/SettingModel.php
 * @see         app/Helpers/settings_helper.php (duplicate - prefer this file)
 * @package     App\Helpers
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

use App\Models\SettingModel;

// ─────────────────────────────────────────────────────────────
// Timezone helpers for views
// ─────────────────────────────────────────────────────────────

if (!function_exists('utc_to_local')) {
    /**
     * Convert a UTC datetime string (from DB) to a local-timezone timestamp.
     *
     * Usage in views:
     *   date('M j, Y', utc_to_local($appt['start_at']))
     *   date('g:i A', utc_to_local($appt['start_at']))
     *
     * @param  string|null $utcDatetime  Y-m-d H:i:s in UTC (from DB)
     * @return int|false   Unix timestamp in the local timezone, or false if null
     */
    function utc_to_local(?string $utcDatetime)
    {
        if (empty($utcDatetime)) {
            return false;
        }
        $local = \App\Services\TimezoneService::toDisplay($utcDatetime);
        return strtotime($local);
    }
}

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
        if (!$path || !is_string($path)) {
            if (is_string($default) && $default !== null) {
                $default = ltrim($default, '/');
                if (preg_match('~^https?://~i', $default)) {
                    return $default;
                }
                return base_url($default);
            }

            $fallback = setting_asset_fallback_path($key);
            if ($fallback) {
                return base_url($fallback);
            }

            return null;
        }
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

if (!function_exists('setting_asset_fallback_path')) {
    /**
     * Provide filesystem fallback for settings-backed assets when no DB entry exists yet.
     */
    function setting_asset_fallback_path(string $key): ?string
    {
        static $directories = [
            'general.company_logo' => 'assets/settings',
        ];

        $relativeDir = $directories[$key] ?? null;
        if ($relativeDir === null) {
            return null;
        }

        $fullDir = rtrim(FCPATH, '/\\') . '/' . trim($relativeDir, '/');
        if (!is_dir($fullDir) || !is_readable($fullDir)) {
            return null;
        }

        if ($key === 'general.company_logo') {
            $defaultLogo = $fullDir . '/default-logo.svg';
            if (is_file($defaultLogo)) {
                return trim($relativeDir, '/') . '/default-logo.svg';
            }
        }

        $candidates = [];
        foreach (['png', 'jpg', 'jpeg', 'webp', 'svg'] as $ext) {
            $pattern = $fullDir . '/*.' . $ext;
            $matches = glob($pattern) ?: [];
            foreach ($matches as $file) {
                if (is_file($file)) {
                    $candidates[] = $file;
                }
            }
        }

        if (empty($candidates)) {
            return null;
        }

        usort($candidates, static fn(string $a, string $b) => filemtime($b) <=> filemtime($a));

        $newest = $candidates[0] ?? null;
        if (!$newest) {
            return null;
        }

        return trim($relativeDir, '/') . '/' . basename($newest);
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
