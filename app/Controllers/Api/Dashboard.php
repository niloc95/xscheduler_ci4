<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\AppointmentModel;

class Dashboard extends BaseController
{
    protected AppointmentModel $appointmentModel;

    public function __construct()
    {
        $this->appointmentModel = new AppointmentModel();
    }

    /**
     * Return appointment stats for the SPA dashboard.
     */
    public function appointmentStats()
    {
        if (!session()->get('isLoggedIn')) {
            return $this->response->setStatusCode(401)->setJSON([
                'error' => [
                    'message' => 'Authentication required'
                ]
            ]);
        }

        try {
            $stats = $this->appointmentModel->getStats();

            return $this->response->setJSON([
                'data' => [
                    'upcoming' => (int) ($stats['upcoming'] ?? 0),
                    'completed' => (int) ($stats['completed'] ?? 0),
                    'pending' => (int) ($stats['today'] ?? 0),
                    'today' => (int) ($stats['today'] ?? 0),
                    'total' => (int) ($stats['total'] ?? 0),
                ],
                'meta' => [
                    'timestamp' => date('Y-m-d H:i:s'),
                ],
            ]);
        } catch (\Exception $e) {
            log_message('error', 'Dashboard API Error: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON([
                'error' => [
                    'message' => 'Unable to fetch appointment stats'
                ]
            ]);
        }
    }
}
