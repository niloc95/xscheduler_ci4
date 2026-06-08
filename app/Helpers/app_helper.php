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
 *   Example: setting('general.business_name', 'WebScheduler')
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
 * @author      Nilesh Nagin Cara
 * @copyright   2024-2026 Nilesh Nagin Cara
 * =============================================================================
 */

use App\Models\SettingModel;
use App\Models\UserModel;

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
        if (str_starts_with($path, 'assets/profile/')) return base_url($path);
        if (str_starts_with($path, 'uploads/providers/')) return base_url('assets/p/' . basename($path));
        return base_url('writable/' . $path);
    }
}

if (!function_exists('avatar_upper')) {
    function avatar_upper(string $value): string
    {
        return function_exists('mb_strtoupper') ? mb_strtoupper($value, 'UTF-8') : strtoupper($value);
    }
}

if (!function_exists('mask_sensitive_value')) {
    /**
     * Mask a value while keeping only the last N characters visible.
     */
    function mask_sensitive_value($value, int $visible = 4): string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return '';
        }

        $len = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
        $keep = max(1, min($visible, $len));
        $maskedCount = max(0, $len - $keep);

        $suffix = function_exists('mb_substr')
            ? mb_substr($text, $len - $keep, null, 'UTF-8')
            : substr($text, -$keep);

        return str_repeat('*', $maskedCount > 0 ? $maskedCount : 4) . $suffix;
    }
}

if (!function_exists('avatar_slice')) {
    function avatar_slice(string $value, int $start, int $length): string
    {
        if (function_exists('mb_substr')) {
            return (string) mb_substr($value, $start, $length, 'UTF-8');
        }

        return (string) substr($value, $start, $length);
    }
}

if (!function_exists('avatar_display_name')) {
    function avatar_display_name(array $entity): string
    {
        $name = trim((string) ($entity['name'] ?? ''));

        if ($name !== '') {
            return $name;
        }

        return trim((string) (($entity['first_name'] ?? '') . ' ' . ($entity['last_name'] ?? '')));
    }
}

if (!function_exists('avatar_initials')) {
    function avatar_initials(?string $name, string $default = 'U'): string
    {
        $candidate = trim((string) $name);
        if ($candidate === '') {
            return $default;
        }

        $candidate = preg_replace('/^(dr|mr|mrs|ms|prof|rev)\.?\s+/i', '', $candidate) ?? $candidate;

        // Remove one or more trailing credentials/suffixes.
        do {
            $updated = preg_replace('/(?:,?\s+|\.\s*)(md|phd|dds|do|rn|np|pa|dvm|jr|sr|ii|iii|iv)\.?$/i', '', $candidate);
            if (!is_string($updated) || $updated === $candidate) {
                break;
            }
            $candidate = trim($updated);
        } while ($candidate !== '');

        $parts = preg_split('/\s+/u', trim($candidate)) ?: [];
        $parts = array_values(array_filter($parts, static fn($part) => $part !== ''));

        if (empty($parts)) {
            return $default;
        }

        if (count($parts) === 1) {
            return avatar_upper(avatar_slice($parts[0], 0, 2));
        }

        $first = avatar_slice((string) $parts[0], 0, 1);
        $last = avatar_slice((string) $parts[count($parts) - 1], 0, 1);

        return avatar_upper($first . $last);
    }
}

if (!function_exists('avatar_profile_image_url')) {
    function avatar_profile_image_url(array $entity, string $imageField = 'profile_image'): ?string
    {
        $storedImage = $entity[$imageField] ?? null;

        if (!$storedImage || !is_string($storedImage)) {
            return null;
        }

        $normalized = ltrim($storedImage, '/');

        if (str_starts_with($normalized, 'assets/')) {
            $assetPath = rtrim(FCPATH, '/') . '/' . $normalized;
            return is_file($assetPath) ? base_url($normalized) : null;
        }

        if (str_starts_with($normalized, 'uploads/')) {
            $uploadPath = rtrim(WRITEPATH, '/') . '/' . $normalized;
            return is_file($uploadPath) ? base_url('writable/' . $normalized) : null;
        }

        $filePath = WRITEPATH . 'uploads/profile_images/' . $normalized;

        if (!is_file($filePath)) {
            return null;
        }

        return base_url('uploads/profile_images/' . $normalized);
    }
}

if (!function_exists('avatar_data')) {
    function avatar_data(array $entity, string $default = 'U', string $imageField = 'profile_image'): array
    {
        $name = avatar_display_name($entity);

        return [
            'name' => $name,
            'image_url' => avatar_profile_image_url($entity, $imageField),
            'initials' => avatar_initials($name, $default),
        ];
    }
}

if (!function_exists('avatar_resolve_urls')) {
    function avatar_resolve_urls(array $entities, string $imageField = 'profile_image'): array
    {
        foreach ($entities as &$entity) {
            $entity['profile_image_url'] = avatar_profile_image_url($entity, $imageField) ?? '';
        }
        return $entities;
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
        $toSameOriginPath = static function (string $path): string {
            $url = base_url($path);

            return parse_url($url, PHP_URL_PATH) ?: $url;
        };

        $path = setting($key, $default);
        if (!$path || !is_string($path)) {
            if (is_string($default) && $default !== null) {
                $default = ltrim($default, '/');
                if (preg_match('~^https?://~i', $default)) {
                    return $default;
                }
                return $toSameOriginPath($default);
            }

            $fallback = setting_asset_fallback_path($key);
            if ($fallback) {
                return $toSameOriginPath($fallback);
            }

            return null;
        }
        $path = ltrim((string) $path, '/');
        if (strpos($path, 'uploads/settings/') === 0) {
            $filename = basename($path);
            return $toSameOriginPath('assets/s/' . $filename);
        }
        if (strpos($path, 'assets/settings/') === 0 || strpos($path, 'assets/providers/') === 0) {
            return $toSameOriginPath($path);
        }
        return $toSameOriginPath('writable/' . $path);
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

if (!function_exists('analytics_head_html')) {
    /**
     * Returns the analytics script block for injection into <head>.
     *
     * Reads integrations.analytics (provider) and integrations.analytics_id
     * from xs_settings. Returns an empty string if not configured.
     *
     * GA4 : integrations.analytics_id = 'G-XXXXXXXXXX'
     * Matomo: integrations.analytics_id = 'https://your-matomo.com'
     *         integrations.analytics_site_id = numeric site ID
     */
    function analytics_head_html(): string
    {
        try {
            $settings = (new \App\Models\SettingModel())->getByKeys([
                'integrations.analytics',
                'integrations.analytics_id',
                'integrations.analytics_site_id',
            ]);

            $provider = (string) ($settings['integrations.analytics']         ?? 'none');
            $id       = trim((string) ($settings['integrations.analytics_id'] ?? ''));
            $siteId   = (int) ($settings['integrations.analytics_site_id']    ?? 1);
        } catch (\Throwable $e) {
            return '';
        }

        if ($provider === 'none' || $id === '') {
            return '';
        }

        if ($provider === 'google') {
            $eid = esc($id, 'html');
            return <<<HTML
<script async src="https://www.googletagmanager.com/gtag/js?id={$eid}"></script>
<script>
window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}
gtag('js',new Date());
gtag('config','{$eid}',{send_page_view:true});
window.__xsAnalyticsProvider='google';window.__xsAnalyticsId='{$eid}';
</script>
HTML;
        }

        if ($provider === 'matomo') {
            $url    = rtrim($id, '/');
            $eurl   = esc($url, 'html');
            $esid   = (int) $siteId;
            return <<<HTML
<script>
var _paq=window._paq=window._paq||[];
_paq.push(['trackPageView']);_paq.push(['enableLinkTracking']);
(function(){var u="{$eurl}/";_paq.push(['setTrackerUrl',u+'matomo.php']);_paq.push(['setSiteId','{$esid}']);
var d=document,g=d.createElement('script'),s=d.getElementsByTagName('script')[0];
g.async=true;g.src=u+'matomo.js';s.parentNode.insertBefore(g,s);})();
window.__xsAnalyticsProvider='matomo';
</script>
HTML;
        }

        return '';
    }
}
