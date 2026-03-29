<?php

/**
 * =============================================================================
 * DASHBOARD CONTROLLER
 * =============================================================================
 * 
 * @file        app/Controllers/Dashboard.php
 * @description Main dashboard controller for WebScheduler. Displays the landing
 *              page with metrics, today's schedule, alerts, and quick actions.
 * 
 * ROUTES HANDLED:
 * -----------------------------------------------------------------------------
 * GET  /dashboard           : Main dashboard landing page
 * GET  /dashboard/api       : AJAX data refresh endpoint
 * GET  /dashboard/api/metrics : Metrics data for dashboard widgets
 * GET  /dashboard/charts    : Chart data for analytics widgets
 * GET  /dashboard/status    : System status information
 * 
 * PURPOSE:
 * -----------------------------------------------------------------------------
 * Provides authenticated users with an overview of their scheduling business:
 * - Key metrics (appointments today, pending confirmations, revenue)
 * - Today's schedule grouped by provider
 * - Actionable alerts (pending approvals, missing data, etc.)
 * - Quick navigation to common tasks
 * 
 * ROLE-BASED DATA:
 * -----------------------------------------------------------------------------
 * - Admin     : Sees all data across all providers
 * - Provider  : Sees own appointments and assigned staff
 * - Staff     : Sees assigned provider's appointments only
 * - Customer  : Redirected to customer portal (not this dashboard)
 * 
 * DEPENDENCIES:
 * -----------------------------------------------------------------------------
 * - DashboardService     : Business logic for metrics and data aggregation
 * - AuthorizationService : Role-based access control checks
 * - UserModel, AppointmentModel, CustomerModel, ServiceModel
 * 
 * @see         app/Services/DashboardService.php for business logic
 * @see         app/Views/dashboard/landing.php for view template
 * @package     App\Controllers
 * @extends     BaseController
 * @author      Nilesh Nagin Cara
 * @copyright   2024-2026 Nilesh Nagin Cara
 * =============================================================================
 */

namespace App\Controllers;

use App\Services\DashboardApiService;
use App\Services\DashboardPageService;

class Dashboard extends BaseController
{
    protected DashboardApiService $dashboardApiService;
    protected DashboardPageService $dashboardPageService;

    public function __construct(?DashboardApiService $dashboardApiService = null, ?DashboardPageService $dashboardPageService = null)
    {
        $this->dashboardApiService = $dashboardApiService ?? new DashboardApiService();
        $this->dashboardPageService = $dashboardPageService ?? new DashboardPageService();
    }

    /**
     * Dashboard landing page
     */
    public function index()
    {
        try {
            $sessionResult = $this->dashboardPageService->resolveLandingSession();
            
            if ($sessionResult instanceof \CodeIgniter\HTTP\RedirectResponse) {
                return $sessionResult;
            }

            return view('dashboard/landing', $this->dashboardPageService->buildLandingViewData($sessionResult));
            
        } catch (\RuntimeException $e) {
            log_message('warning', 'Dashboard Authorization Error: ' . $e->getMessage() . ' | User: ' . json_encode(session()->get('user')));

            return $this->response
                ->setStatusCode(403)
                ->setBody(view('errors/html/error_403', $this->dashboardPageService->getAccessDeniedViewData($e->getMessage())));
            
        } catch (\Exception $e) {
            log_message('error', 'Dashboard Error: ' . $e->getMessage() . ' | File: ' . $e->getFile() . ' | Line: ' . $e->getLine());
            
            if (ENVIRONMENT === 'development') {
                throw $e;
            }
            
            return view('dashboard/landing', $this->dashboardPageService->getFallbackLandingViewData());
        }
    }

    /**
     * Real-time stats API endpoint for AJAX requests
     */
    public function api()
    {
        try {
            return $this->response->setJSON($this->dashboardPageService->getStatsSummary());
        } catch (\Exception $e) {
            return $this->response->setJSON($this->dashboardPageService->getStatsFallbackPayload());
        }
    }

    /**
     * API endpoint for dashboard metrics
     * Used by landing view for real-time updates
     */
    public function apiMetrics()
    {
        $this->response->setHeader('Content-Type', 'application/json');
        
        try {
            $result = $this->dashboardPageService->getMetricsEndpointResponse();

            return $this->response
                ->setStatusCode($result['statusCode'])
                ->setJSON($result['payload']);
            
        } catch (\Exception $e) {
            log_message('error', 'Dashboard API Metrics Error: ' . $e->getMessage() . ' | File: ' . $e->getFile() . ' | Line: ' . $e->getLine());
            
            return $this->response
                ->setStatusCode(500)
                ->setJSON($this->dashboardPageService->getMetricsErrorPayload($e->getMessage()));
        }
    }

    public function charts()
    {
        try {
            return $this->response->setJSON(
                $this->dashboardApiService->getChartsPayload($this->request->getGet('period'))
            );
        } catch (\Exception $e) {
            return $this->response->setJSON($this->dashboardApiService->getChartsFallbackPayload($e->getMessage()));
        }
    }

    /**
     * Database status check for dashboard
     */
    public function status()
    {
        try {
            return $this->response->setJSON($this->dashboardApiService->getStatusPayload());
            
        } catch (\Exception $e) {
            return $this->response->setJSON($this->dashboardApiService->getStatusErrorPayload($e->getMessage()));
        }
    }
}
