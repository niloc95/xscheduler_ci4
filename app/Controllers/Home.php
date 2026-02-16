<?php

/**
 * =============================================================================
 * HOME CONTROLLER (ENTRY POINT)
 * =============================================================================
 * 
 * @file        app/Controllers/Home.php
 * @description Application entry point that routes users to appropriate
 *              destination based on setup status and authentication.
 * 
 * ROUTES HANDLED:
 * -----------------------------------------------------------------------------
 * GET  /                             : Root URL, redirects based on state
 * 
 * PURPOSE:
 * -----------------------------------------------------------------------------
 * Acts as the application's front door:
 * - New installation → Redirects to /setup
 * - Setup complete, not logged in → Redirects to /dashboard (login required)
 * - Setup complete, logged in → Redirects to /dashboard
 * 
 * ROUTING LOGIC:
 * -----------------------------------------------------------------------------
 * 1. Check if setup_complete.flag exists
 * 2. If not: redirect to /setup wizard
 * 3. If yes: redirect to /dashboard
 * 
 * The dashboard handles authentication via filters, so unauthenticated users
 * will be redirected to /auth/login from there.
 * 
 * @see         app/Helpers/setup_helper.php for is_setup_completed()
 * @see         app/Controllers/Setup.php for setup wizard
 * @see         app/Controllers/Dashboard.php for main dashboard
 * @package     App\Controllers
 * @extends     BaseController
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

namespace App\Controllers;

class Home extends BaseController
{
    public function index()
    {
        // Check if setup is completed using centralized helper (prod-safe)
        helper('setup');
        if (!is_setup_completed()) {
            return redirect()->to(base_url('setup'));
        }

        // If setup is completed, redirect to dashboard
        return redirect()->to(base_url('dashboard'));
    }
}
