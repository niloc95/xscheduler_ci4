<?php

/**
 * =============================================================================
 * ANALYTICS CONTROLLER
 * =============================================================================
 * 
 * @file        app/Controllers/Analytics.php
 * @description Business intelligence and reporting dashboard with charts,
 *              metrics, and exportable data for appointments, revenue, and trends.
 * 
 * ROUTES HANDLED:
 * -----------------------------------------------------------------------------
 * GET  /analytics                    : Main analytics dashboard
 * GET  /analytics/appointments       : Appointment statistics and trends
 * GET  /analytics/revenue            : Revenue reports and projections
 * GET  /analytics/services           : Service popularity analysis
 * GET  /analytics/export             : Export data to CSV/PDF
 * 
 * PURPOSE:
 * -----------------------------------------------------------------------------
 * Provides comprehensive business analytics:
 * - Appointment volume over time (daily/weekly/monthly)
 * - Revenue tracking and forecasting
 * - Service popularity rankings
 * - Provider performance metrics
 * - Customer acquisition and retention rates
 * - Peak hours and booking patterns
 * 
 * DATA VISUALIZATIONS:
 * -----------------------------------------------------------------------------
 * - Line charts: Trends over time
 * - Bar charts: Comparisons (services, providers)
 * - Pie charts: Distribution (services, statuses)
 * - KPI cards: Key metrics summary
 * 
 * ACCESS CONTROL:
 * -----------------------------------------------------------------------------
 * - Admin: Full access to all analytics
 * - Provider: Access to own metrics only
 * 
 * @see         app/Views/analytics/ for dashboard templates
 * @see         resources/js/analytics.js for chart rendering
 * @package     App\Controllers
 * @extends     BaseController
 * @author      Nilesh Nagin Cara
 * @copyright   2024-2026 Nilesh Nagin Cara
 * =============================================================================
 */

namespace App\Controllers;

use App\Services\AnalyticsService;

class Analytics extends BaseController
{
    protected AnalyticsService $analytics;

    public function __construct()
    {
        $this->analytics = new AnalyticsService();
        helper('permissions');
    }

    /**
     * Display analytics dashboard
     */
    public function index()
    {
        // Check authentication
        if (!session()->get('isLoggedIn')) {
            return redirect()->to(base_url('auth/login'));
        }

                // Check permissions - admin and provider can access analytics
        if (!has_role(['admin', 'provider'])) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Access denied');
        }

        $currentUser = session()->get('user');
        $currentRole = current_user_role();
        $timeframe = $this->request->getGet('timeframe') ?? '30d';
        $tab = $this->request->getGet('tab') ?? 'overview';
        $providerId = $currentRole === 'provider'
            ? (int) current_user_id()
            : $this->sanitizeOptionalInt($this->request->getGet('provider_id'));
        $providerServiceFilterId = $this->sanitizeOptionalInt($this->request->getGet('provider_service_id'));
        $providerLocationFilterId = $this->sanitizeOptionalInt($this->request->getGet('provider_location_id'));

        $data = array_merge(
            [
                'title' => $currentRole === 'admin' ? 'Analytics Dashboard' : 'My Analytics',
                'current_page' => 'analytics',
                'user_role' => $currentRole,
                'user' => $currentUser,
                'timeframe' => $timeframe,
                'tab' => in_array($tab, ['overview', 'revenue', 'customers', 'providers'], true) ? $tab : 'overview',
            ],
            $this->analytics->getDashboardData(
                (string) $currentRole,
                (string) $timeframe,
                $providerId,
                $providerServiceFilterId,
                $providerLocationFilterId
            )
        );

        return view('analytics/index', $data);
    }

    /**
     * Revenue analytics
     */
    public function revenue()
    {
        $timeframe = $this->request->getGet('timeframe') ?? '30d';
        return redirect()->to(base_url('analytics?' . http_build_query([
            'tab' => 'revenue',
            'timeframe' => $timeframe,
        ])));
    }

    /**
     * Customer analytics
     */
    public function customers()
    {
        $timeframe = $this->request->getGet('timeframe') ?? '30d';
        return redirect()->to(base_url('analytics?' . http_build_query([
            'tab' => 'customers',
            'timeframe' => $timeframe,
        ])));
    }

    private function sanitizeOptionalInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $intValue = (int) $value;
        return $intValue > 0 ? $intValue : null;
    }
}
