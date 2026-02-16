<?php

/**
 * =============================================================================
 * SETUP FILTER
 * =============================================================================
 * 
 * @file        app/Filters/SetupFilter.php
 * @description HTTP middleware that ensures initial setup is complete before
 *              allowing access to application routes.
 * 
 * FILTER ALIAS: 'setup'
 * 
 * PURPOSE:
 * -----------------------------------------------------------------------------
 * Enforces setup completion:
 * - Applied to all authenticated routes
 * - Checks for setup_complete.flag file
 * - Redirects to /setup if not complete
 * - Prevents access to app without configuration
 * 
 * BEHAVIOR:
 * -----------------------------------------------------------------------------
 * Before Request:
 * 1. Call is_setup_completed() helper
 * 2. If setup incomplete: redirect to /setup
 * 3. If setup complete: continue to controller
 * 
 * SETUP COMPLETION CHECK:
 * -----------------------------------------------------------------------------
 * Checks for existence of: writable/setup_complete.flag
 * This file is created after successful setup wizard completion.
 * 
 * ROUTE CONFIGURATION:
 * -----------------------------------------------------------------------------
 * Applied in Config/Routes.php to protected route groups:
 *     $routes->group('/', ['filter' => 'setup'], function($routes) {
 *         $routes->get('dashboard', 'Dashboard::index');
 *     });
 * 
 * The /setup route itself is NOT protected by this filter.
 * 
 * @see         app/Helpers/setup_helper.php for is_setup_completed()
 * @see         app/Controllers/Setup.php for setup wizard
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

class SetupFilter implements FilterInterface
{
    /**
     * Check if setup is completed before allowing access to protected routes
     *
     * @param RequestInterface $request
     * @param array|null       $arguments
     *
     * @return mixed
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        // Use the global helper for consistent setup checking
        helper('setup');
        
        // Check if setup is completed
        if (!is_setup_completed()) {
            // Redirect to setup page
            return redirect()->to(base_url('setup'))->with('info', 'Please complete the initial setup first.');
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
