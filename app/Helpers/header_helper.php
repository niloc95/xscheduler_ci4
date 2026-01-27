<?php
/**
 * Header Helper Functions
 * 
 * Standardized functions for setting page title, subtitle, and actions
 * throughout the application. These are used by controllers to pass
 * data to the global header component.
 * 
 * Usage in Controller:
 * $this->data = [
 *     'page_title' => get_page_title('Customers'),
 *     'page_subtitle' => get_page_subtitle('Manage all customer profiles'),
 *     'page_actions' => [
 *         create_action_button('New Customer', '/customer-management/create'),
 *     ],
 *     'page_breadcrumbs' => [
 *         ['label' => 'Dashboard', 'url' => base_url('/dashboard')],
 *         ['label' => 'Customers'],
 *     ],
 * ];
 */

if (!function_exists('set_page_header')) {
    /**
     * Helper to standardize page header data in controllers.
     * 
     * @param string $title       Page title
     * @param string $subtitle    Optional page subtitle
     * @param array  $actions     Optional action buttons array
     * @param array  $breadcrumbs Optional breadcrumb trail
     * @return array Header data ready for view
     */
    function set_page_header($title = 'Dashboard', $subtitle = '', $actions = [], $breadcrumbs = []) {
        return [
            'page_title' => $title,
            'page_subtitle' => $subtitle,
            'page_actions' => $actions,
            'page_breadcrumbs' => $breadcrumbs,
        ];
    }
}

if (!function_exists('create_action_button')) {
    /**
     * Create a standardized action button for page headers.
     * 
     * @param string $label     Button label
     * @param string $url       Target URL
     * @param string $style     Button style: 'primary', 'secondary', 'ghost'
     * @param string $icon      Optional Material icon name
     * @param array  $attrs     Optional additional HTML attributes
     * @return string HTML button
     */
    function create_action_button($label, $url = '#', $style = 'primary', $icon = '', $attrs = []) {
        $btnClass = 'xs-btn xs-btn-' . $style;
        $attrStr = '';
        foreach ($attrs as $key => $value) {
            $attrStr .= ' ' . esc($key) . '="' . esc($value) . '"';
        }
        
        $iconHtml = $icon ? '<span class="material-symbols-outlined">' . esc($icon) . '</span>' : '';
        
        return sprintf(
            '<a href="%s" class="%s"%s>%s %s</a>',
            esc($url),
            $btnClass,
            $attrStr,
            $iconHtml,
            esc($label)
        );
    }
}

if (!function_exists('create_breadcrumb_item')) {
    /**
     * Create a breadcrumb item.
     * 
     * @param string $label   Breadcrumb label
     * @param string $url     Optional URL (if not provided, treated as current page)
     * @return array Breadcrumb item
     */
    function create_breadcrumb_item($label, $url = null) {
        return [
            'label' => $label,
            'url' => $url,
        ];
    }
}

if (!function_exists('get_role_display_name')) {
    /**
     * Get human-readable display name for user role.
     * 
     * @param string $role User role (admin, provider, staff, customer)
     * @return string Display name
     */
    function get_role_display_name($role = '') {
        if (empty($role)) {
            $role = session()->get('user')['role'] ?? 'customer';
        }
        
        $displayNames = [
            'admin' => 'Administrator',
            'provider' => 'Service Provider',
            'staff' => 'Staff Member',
            'customer' => 'Customer',
            'user' => 'User',
        ];
        
        return $displayNames[strtolower($role)] ?? ucfirst($role);
    }
}
