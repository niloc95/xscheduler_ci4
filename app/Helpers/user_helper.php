<?php

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
