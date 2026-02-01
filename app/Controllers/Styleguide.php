<?php

/**
 * =============================================================================
 * STYLE GUIDE CONTROLLER
 * =============================================================================
 * 
 * @file        app/Controllers/Styleguide.php
 * @description Developer documentation for UI components, design patterns,
 *              and styling guidelines used throughout the application.
 * 
 * ROUTES HANDLED:
 * -----------------------------------------------------------------------------
 * GET  /styleguide                   : Style guide overview
 * GET  /styleguide/components        : UI component library
 * GET  /styleguide/scheduler         : Scheduler-specific components
 * 
 * PURPOSE:
 * -----------------------------------------------------------------------------
 * Development reference for maintaining consistent UI:
 * - Typography scale and font usage
 * - Color palette (light and dark mode)
 * - Button styles and states
 * - Form elements and validation
 * - Card and panel layouts
 * - Icons and iconography
 * - Spacing and layout grid
 * 
 * SECTIONS:
 * -----------------------------------------------------------------------------
 * - index: Overview, colors, typography
 * - components: Buttons, forms, cards, modals, alerts
 * - scheduler: Calendar views, appointment cards, time pickers
 * 
 * DEVELOPMENT ONLY:
 * -----------------------------------------------------------------------------
 * This controller is intended for development/staging environments.
 * Consider disabling in production via route filters or environment check.
 * 
 * @see         app/Views/styleguide/ for view templates
 * @see         resources/scss/ for source styles
 * @package     App\Controllers
 * @extends     BaseController
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

namespace App\Controllers;

class Styleguide extends BaseController
{
    public function index()
    {
        $data = [
            'title' => 'Style Guide - WebSchedulr'
        ];
    return view('styleguide/index', $data);
    }
    
    public function components()
    {
        $data = [
            'title' => 'Components - Style Guide'
        ];
    return view('styleguide/components', $data);
    }
    
    public function scheduler()
    {
        $data = [
            'title' => 'Scheduler Components - Style Guide'
        ];
    return view('styleguide/scheduler', $data);
    }
}