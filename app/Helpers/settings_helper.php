<?php

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
        // If the file is stored in DB, prefer streaming from DB
        try {
            $sfm = new \App\Models\SettingFileModel();
            $row = $sfm->getByKey($key);
            if ($row && !empty($row['data'])) {
                return base_url('assets/db/' . rawurlencode($key));
            }
        } catch (\Throwable $e) {}

        $path = setting($key, $default);
        if (!$path || !is_string($path)) {
            $fallback = setting_asset_fallback_path($key);
            if ($fallback) {
                return base_url($fallback);
            }

            if (is_string($default) && $default !== null) {
                $default = ltrim($default, '/');
                if (preg_match('~^https?://~i', $default)) {
                    return $default;
                }

                return base_url($default);
            }

            return null;
        }
        // If value indicates DB-backed (e.g., 'db://...'), stream from DB
        if (is_string($path) && str_starts_with($path, 'db://')) {
            return base_url('assets/db/' . rawurlencode($key));
        }
        $path = ltrim((string) $path, '/');
        // Prefer dedicated route for legacy writable uploads
        if (strpos($path, 'uploads/settings/') === 0) {
            $filename = basename($path);
            return base_url('assets/s/' . $filename);
        }
        // New convention: files saved under public/assets/** are directly web-accessible
        if (strpos($path, 'assets/settings/') === 0 || strpos($path, 'assets/providers/') === 0) {
            return base_url($path);
        }
        // Fallback to public/writable mapping if present
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
