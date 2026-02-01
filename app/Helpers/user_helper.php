<?php

/**
 * =============================================================================
 * USER HELPER
 * =============================================================================
 * 
 * @file        app/Helpers/user_helper.php
 * @description Helper functions for user-related display formatting.
 *              Provides consistent role and status badge rendering.
 * 
 * LOADING:
 * -----------------------------------------------------------------------------
 * Loaded automatically via BaseController or manually:
 *     helper('user');
 * 
 * AVAILABLE FUNCTIONS:
 * -----------------------------------------------------------------------------
 * get_role_display_name($role)
 *   Convert role key to human-readable name
 *   Example: get_role_display_name('admin') => 'Admin'
 * 
 * get_role_badge_classes($role)
 *   Get Tailwind CSS classes for role badge styling
 *   Example: get_role_badge_classes('admin') => 'bg-red-100 text-red-800 ...'
 * 
 * get_status_badge_classes($isActive)
 *   Get Tailwind CSS classes for active/inactive status
 *   Example: get_status_badge_classes(true) => 'bg-green-100 text-green-800 ...'
 * 
 * ROLE COLORS:
 * -----------------------------------------------------------------------------
 * - admin    : Red (high privilege indicator)
 * - provider : Blue (service provider)
 * - staff    : Green (team member)
 * - customer : Gray (default/external)
 * 
 * USAGE IN VIEWS:
 * -----------------------------------------------------------------------------
 *     <span class="badge <?= get_role_badge_classes($user['role']) ?>">
 *         <?= get_role_display_name($user['role']) ?>
 *     </span>
 * 
 *     <span class="badge <?= get_status_badge_classes($user['is_active']) ?>">
 *         <?= $user['is_active'] ? 'Active' : 'Inactive' ?>
 *     </span>
 * 
 * DARK MODE:
 * -----------------------------------------------------------------------------
 * All badge classes include dark mode variants (dark:bg-*, dark:text-*)
 * for consistent appearance in both light and dark themes.
 * 
 * @see         app/Views/users/*.php for usage examples
 * @package     App\Helpers
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

if (!function_exists('get_role_display_name')) {
    /**
     * Get display name for user role
     * 
     * @param string $role User role key
     * @return string Display name
     */
    function get_role_display_name(string $role): string
    {
        $roleNames = [
            'admin' => 'Admin',
            'provider' => 'Provider',
            'staff' => 'Staff',
            'customer' => 'Customer',
        ];

        return $roleNames[$role] ?? ucfirst($role);
    }
}

if (!function_exists('get_role_badge_classes')) {
    /**
     * Get Tailwind CSS classes for role badge
     * 
     * @param string $role User role key
     * @return string CSS classes
     */
    function get_role_badge_classes(string $role): string
    {
        $badgeColors = [
            'admin' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
            'provider' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
            'staff' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
            'customer' => 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
        ];

        return $badgeColors[$role] ?? 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200';
    }
}

if (!function_exists('get_status_badge_classes')) {
    /**
     * Get Tailwind CSS classes for status badge
     * 
     * @param bool $isActive Status active/inactive
     * @return string CSS classes
     */
    function get_status_badge_classes(bool $isActive): string
    {
        return $isActive
            ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
            : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
    }
}
