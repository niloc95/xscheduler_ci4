<?php

/**
 * Backward-compatible entry point for shared settings helpers.
 *
 * Prefer helper('app') for new code.
 */

require_once APPPATH . 'Helpers/app_helper.php';

if (!function_exists('get_setting')) {
    function get_setting(string $key, $default = null)
    {
        return setting($key, $default);
    }
}
