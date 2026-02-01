<?php

/**
 * =============================================================================
 * APP FLOW CONTROLLER
 * =============================================================================
 * 
 * @file        app/Controllers/AppFlow.php
 * @description Application routing controller that directs users to the
 *              appropriate destination based on setup and auth status.
 * 
 * ROUTES HANDLED:
 * -----------------------------------------------------------------------------
 * GET  /app                          : Main application entry point
 * 
 * PURPOSE:
 * -----------------------------------------------------------------------------
 * Intelligent routing based on application state:
 * 1. Setup incomplete → Redirect to /setup
 * 2. Setup complete + logged in → Redirect to /dashboard
 * 3. Setup complete + not logged in → Redirect to /login
 * 
 * ROUTING LOGIC:
 * -----------------------------------------------------------------------------
 * ┌─────────────────┐
 * │  User visits /app │
 * └────────┬────────┘
 *          │
 *          ▼
 *    Setup complete?
 *    │           │
 *   No           Yes
 *    │           │
 *    ▼           ▼
 * /setup    Logged in?
 *           │       │
 *          Yes      No
 *           │       │
 *           ▼       ▼
 *      /dashboard  /login
 * 
 * SIMILAR TO:
 * -----------------------------------------------------------------------------
 * - Home controller (routes from /)
 * - Provides alternative entry point at /app
 * 
 * @see         app/Controllers/Home.php for root URL handler
 * @see         app/Helpers/setup_helper.php for is_setup_completed()
 * @package     App\Controllers
 * @extends     BaseController
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

namespace App\Controllers;

class AppFlow extends BaseController
{
    /**
     * Handle application entry point and route users correctly
     */
    public function index()
    {
        // Check if setup is completed
        if (!is_setup_completed()) {
            // Setup not completed - redirect to setup
            return redirect()->to('/setup');
        }

        // Setup is completed - check if user is logged in
        if (session()->get('isLoggedIn')) {
            // User is logged in - go to dashboard
            return redirect()->to('/dashboard');
        }

        // User is not logged in - go to login
        return redirect()->to('/login');
    }

}
