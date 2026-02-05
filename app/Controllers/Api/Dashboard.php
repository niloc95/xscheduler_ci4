<?php

/**
 * =============================================================================
 * DASHBOARD API CONTROLLER
 * =============================================================================
 * 
 * @file        app/Controllers/Api/Dashboard.php
 * @description API for dashboard statistics, metrics, and widget data
 *              used by the SPA dashboard components.
 * 
 * API ENDPOINTS:
 * -----------------------------------------------------------------------------
 * GET  /api/dashboard/appointment-stats : Appointment statistics summary
 * GET  /api/dashboard/revenue-stats     : Revenue metrics (if enabled)
 * GET  /api/dashboard/upcoming          : Upcoming appointments list
 * GET  /api/dashboard/recent-activity   : Recent system activity
 * 
 * APPOINTMENT STATS RESPONSE:
 * -----------------------------------------------------------------------------
 * {
 *   "data": {
 *     "upcoming": 12,      // Future appointments
 *     "completed": 145,    // Past completed
 *     "pending": 3,        // Awaiting confirmation
 *     "today": 5,          // Today's appointments
 *     "total": 160         // All-time total
 *   },
 *   "meta": {
 *     "timestamp": "2025-01-15T10:30:00+00:00"
 *   }
 * }
 * 
 * PURPOSE:
 * -----------------------------------------------------------------------------
 * Provides real-time data for dashboard widgets:
 * - KPI cards (counts, summaries)
 * - Quick statistics for at-a-glance view
 * - Data formatted for SPA consumption
 * 
 * ACCESS CONTROL:
 * -----------------------------------------------------------------------------
 * - Requires authentication (requireAuth check)
 * - Admin: Full statistics
 * - Provider: Own statistics only
 * 
 * @see         app/Models/AppointmentModel::getStats() for calculations
 * @see         resources/js/components/dashboard-stats.js for frontend
 * @package     App\Controllers\Api
 * @extends     BaseApiController
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

namespace App\Controllers\Api;

use App\Models\AppointmentModel;

/**
 * Dashboard API Controller
 * 
 * Provides dashboard statistics and metrics.
 */
class Dashboard extends BaseApiController
{
    protected AppointmentModel $appointmentModel;

    public function __construct()
    {
        $this->appointmentModel = new AppointmentModel();
    }

    /**
     * GET /api/dashboard/appointment-stats
     * 
     * Return appointment stats for the SPA dashboard.
     * Supports date range filtering via query parameters.
     * 
     * Query Parameters:
     * - view: 'day'|'week'|'month' (optional, defaults to 'day')
     * - date: 'YYYY-MM-DD' (optional, defaults to today)
     * - provider_id: int (optional, filter by provider)
     */
    public function appointmentStats()
    {
        if ($authError = $this->requireAuth()) {
            return $authError;
        }

        try {
            // Parse query parameters
            $view = $this->request->getGet('view') ?? 'day';
            $dateParam = $this->request->getGet('date') ?? date('Y-m-d');
            $providerId = $this->request->getGet('provider_id');
            
            // Calculate date range based on view
            $startDate = $dateParam;
            $endDate = $dateParam;
            
            switch ($view) {
                case 'week':
                    // Get start (Monday) and end (Sunday) of the week
                    $date = new \DateTime($dateParam);
                    $dayOfWeek = (int) $date->format('N'); // 1=Monday, 7=Sunday
                    $date->modify('-' . ($dayOfWeek - 1) . ' days');
                    $startDate = $date->format('Y-m-d');
                    $date->modify('+6 days');
                    $endDate = $date->format('Y-m-d');
                    break;
                    
                case 'month':
                    $date = new \DateTime($dateParam);
                    $startDate = $date->format('Y-m-01');
                    $endDate = $date->format('Y-m-t');
                    break;
                    
                case 'day':
                default:
                    // Single day - startDate and endDate are the same
                    break;
            }
            
            // Get date-range aware stats
            $stats = $this->appointmentModel->getStatsForDateRange($startDate, $endDate, $providerId);
            
            // Get providers with appointments in this date range
            $activeProviders = $this->appointmentModel->getProvidersWithAppointments($startDate, $endDate);

            return $this->ok([
                'upcoming' => (int) ($stats['upcoming'] ?? 0),
                'completed' => (int) ($stats['completed'] ?? 0),
                'pending' => (int) ($stats['pending'] ?? 0),
                'confirmed' => (int) ($stats['confirmed'] ?? 0),
                'cancelled' => (int) ($stats['cancelled'] ?? 0),
                'noshow' => (int) ($stats['noshow'] ?? 0),
                'total' => (int) ($stats['total'] ?? 0),
                'active_providers' => $activeProviders,
            ], [
                'timestamp' => date('c'),
                'view' => $view,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);
        } catch (\Exception $e) {
            log_message('error', 'Dashboard API Error: ' . $e->getMessage());
            return $this->serverError('Unable to fetch appointment stats', $e->getMessage());
        }
    }
}
