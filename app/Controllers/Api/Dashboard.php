<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\AppointmentModel;
use App\Services\AppointmentDashboardContextService;

class Dashboard extends BaseController
{
    protected AppointmentModel $appointmentModel;
    protected AppointmentDashboardContextService $contextService;

    public function __construct()
    {
        $this->appointmentModel = new AppointmentModel();
        $this->contextService = new AppointmentDashboardContextService();
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

        $currentUser = session()->get('user');
        $currentRole = current_user_role();
        $currentUserId = session()->get('user_id');

        $statusFilter = $this->appointmentModel->normalizeStatusFilter($this->request->getGet('status'));
        $context = $this->contextService->build($currentRole, $currentUserId, $currentUser);
        $stats = $this->appointmentModel->getStats($context, $statusFilter);

        return $this->response->setJSON([
            'data' => [
                'upcoming' => (int) ($stats['upcoming'] ?? 0),
                'completed' => (int) ($stats['completed'] ?? 0),
                'pending' => (int) ($stats['pending'] ?? 0),
            ],
            'meta' => [
                'filters' => [
                    'status' => $statusFilter,
                ],
            ],
        ]);
    }
}
