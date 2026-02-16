<?php

/**
 * =============================================================================
 * SETUP AUTH FILTER
 * =============================================================================
 * 
 * @file        app/Filters/SetupAuthFilter.php
 * @description Combined filter that checks both setup completion AND user
 *              authentication in a single filter.
 * 
 * FILTER ALIAS: 'setup_auth'
 * 
 * PURPOSE:
 * -----------------------------------------------------------------------------
 * Combines two checks in one filter:
 * 1. Setup completion check (like SetupFilter)
 * 2. Authentication check (like AuthFilter)
 * 
 * BEHAVIOR:
 * -----------------------------------------------------------------------------
 * Before Request:
 * 1. Check if setup is completed
 *    - If not: redirect to /setup
 * 2. Check if user is logged in
 *    - If not: store intended URL, redirect to /auth/login
 * 3. If both pass: continue to controller
 * 
 * USE CASE:
 * -----------------------------------------------------------------------------
 * For routes that need both checks without nesting filters:
 *     $routes->get('/profile', 'Profile::index', ['filter' => 'setup_auth']);
 * 
 * Instead of:
 *     ['filter' => ['setup', 'auth']]
 * 
 * REDIRECT HANDLING:
 * -----------------------------------------------------------------------------
 * Stores intended URL in session before redirecting to login,
 * allowing return to original destination after successful login.
 * 
 * @see         app/Filters/SetupFilter.php for setup-only check
 * @see         app/Filters/AuthFilter.php for auth-only check
 * @package     App\Filters
 * @implements  FilterInterface
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class SetupAuthFilter implements FilterInterface
{
    /**
     * Check if both setup is completed and user is authenticated
     *
     * @param RequestInterface $request
     * @param array|null       $arguments
     *
     * @return mixed
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        // First check if setup is completed
        if (!is_setup_completed()) {
            return redirect()->to(base_url('setup'))->with('info', 'Please complete the initial setup first.');
        }

        // Then check if user is logged in
        if (!session()->get('isLoggedIn')) {
            // Store intended URL for redirect after login
            session()->set('redirect_url', current_url());
            return redirect()->to(base_url('auth/login'))->with('error', 'Please log in to access this page.');
        }
    }

    /**
     * Allows After filters to inspect and modify the response
     * object as needed. This method does not allow any way
     * to stop execution of other after filters, short of
     * throwing an Exception or Error.
     *
     * @param RequestInterface  $request
     * @param ResponseInterface $response
     * @param array|null        $arguments
     *
     * @return mixed
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Do nothing
    }

}
