<?php

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
     */
    public function appointmentStats()
    {
        if ($authError = $this->requireAuth()) {
            return $authError;
        }

        try {
            $stats = $this->appointmentModel->getStats();

            return $this->ok([
                'upcoming' => (int) ($stats['upcoming'] ?? 0),
                'completed' => (int) ($stats['completed'] ?? 0),
                'pending' => (int) ($stats['today'] ?? 0),
                'today' => (int) ($stats['today'] ?? 0),
                'total' => (int) ($stats['total'] ?? 0),
            ], [
                'timestamp' => date('c'),
            ]);
        } catch (\Exception $e) {
            log_message('error', 'Dashboard API Error: ' . $e->getMessage());
            return $this->serverError('Unable to fetch appointment stats', $e->getMessage());
        }
    }
}
