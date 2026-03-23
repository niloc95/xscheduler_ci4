<?php

namespace App\Services;

use App\Models\AppointmentModel;
use App\Models\ServiceModel;
use App\Models\UserModel;

class DashboardApiService
{
    protected UserModel $userModel;
    protected ServiceModel $serviceModel;
    protected AppointmentModel $appointmentModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
        $this->serviceModel = new ServiceModel();
        $this->appointmentModel = new AppointmentModel();
    }

    public function normalizePeriod(?string $period): string
    {
        $validPeriods = ['day', 'week', 'month', 'year'];

        return in_array($period, $validPeriods, true) ? $period : 'month';
    }

    public function getChartsPayload(?string $period): array
    {
        $resolvedPeriod = $this->normalizePeriod($period);

        return [
            'appointmentGrowth' => $this->appointmentModel->getAppointmentGrowth($resolvedPeriod),
            'servicesByProvider' => $this->appointmentModel->getProviderServicesByPeriod($resolvedPeriod),
            'statusDistribution' => $this->appointmentModel->getStatusStats([
                'format' => 'chart',
                'includeColors' => true,
            ]),
            'period' => $resolvedPeriod,
        ];
    }

    public function getChartsFallbackPayload(string $message): array
    {
        return [
            'appointmentGrowth' => [
                'labels' => ['No Data'],
                'data' => [0],
            ],
            'servicesByProvider' => [
                'labels' => ['No Data'],
                'data' => [0],
            ],
            'statusDistribution' => [
                'labels' => ['No Data'],
                'data' => [0],
                'colors' => ['#9aa0a6'],
            ],
            'error' => $message,
        ];
    }

    public function getAnalyticsPayload(): array
    {
        return [
            'users' => [
                'total' => $this->userModel->getStats(),
                'recent' => $this->userModel->getRecentUsers(10),
                'growth' => $this->userModel->getUserGrowthData(12),
            ],
            'appointments' => [
                'stats' => $this->appointmentModel->getStats(),
                'recent' => $this->appointmentModel->getRecentAppointments(20),
                'weekly_data' => $this->appointmentModel->getAppointmentGrowth('week'),
                'monthly_data' => $this->appointmentModel->getAppointmentGrowth('month'),
                'status_distribution' => $this->appointmentModel->getStatusStats(['format' => 'chart']),
            ],
            'services' => [
                'stats' => $this->serviceModel->getStats(),
                'popular' => $this->serviceModel->getPopularServices(10),
            ],
            'revenue' => [
                'today' => $this->appointmentModel->getRealRevenue('today'),
                'week' => $this->appointmentModel->getRealRevenue('week'),
                'month' => $this->appointmentModel->getRealRevenue('month'),
            ],
        ];
    }

    public function getStatusPayload(): array
    {
        $db = \Config\Database::connect();
        $tables = ['users', 'services', 'appointments'];
        $tableStatus = [];

        foreach ($tables as $table) {
            $tableStatus[$table] = $db->tableExists($table);
        }

        $counts = [];
        if ($tableStatus['users']) {
            $counts['users'] = $this->userModel->countAll();
        }
        if ($tableStatus['services']) {
            $counts['services'] = $this->serviceModel->countAll();
        }
        if ($tableStatus['appointments']) {
            $counts['appointments'] = $this->appointmentModel->countAll();
        }

        return [
            'database_connected' => true,
            'tables' => $tableStatus,
            'counts' => $counts,
        ];
    }

    public function getStatusErrorPayload(string $message): array
    {
        return [
            'database_connected' => false,
            'error' => $message,
        ];
    }
}