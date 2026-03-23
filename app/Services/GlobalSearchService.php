<?php

namespace App\Services;

use App\Models\AppointmentModel;
use App\Models\CustomerModel;

class GlobalSearchService
{
    private const DEFAULT_LIMIT = 10;
    private const MAX_LIMIT = 50;

    private CustomerModel $customerModel;
    private AppointmentModel $appointmentModel;

    public function __construct(
        ?CustomerModel $customerModel = null,
        ?AppointmentModel $appointmentModel = null
    ) {
        $this->customerModel = $customerModel ?? new CustomerModel();
        $this->appointmentModel = $appointmentModel ?? new AppointmentModel();
    }

    public function search(string $query, int $limit = self::DEFAULT_LIMIT): array
    {
        $query = trim($query);
        $limit = max(1, min(self::MAX_LIMIT, $limit));

        if ($query === '') {
            return [
                'customers' => [],
                'appointments' => [],
                'counts' => [
                    'customers' => 0,
                    'appointments' => 0,
                    'total' => 0,
                ],
            ];
        }

        $customers = $this->customerModel->search([
            'q' => $query,
            'limit' => $limit,
        ]);

        $appointments = $this->searchAppointments($query, $limit);

        return [
            'customers' => $customers,
            'appointments' => $appointments,
            'counts' => [
                'customers' => count($customers),
                'appointments' => count($appointments),
                'total' => count($customers) + count($appointments),
            ],
        ];
    }

    private function searchAppointments(string $query, int $limit): array
    {
        $appointments = $this->appointmentModel
            ->select('xs_appointments.*, 
                     CONCAT(xs_customers.first_name, " ", xs_customers.last_name) as customer_name,
                     xs_customers.email as customer_email,
                     xs_services.name as service_name')
            ->join('xs_customers', 'xs_customers.id = xs_appointments.customer_id', 'left')
            ->join('xs_services', 'xs_services.id = xs_appointments.service_id', 'left')
            ->groupStart()
                ->like('xs_customers.first_name', $query)
                ->orLike('xs_customers.last_name', $query)
                ->orLike('xs_customers.email', $query)
                ->orLike('xs_services.name', $query)
                ->orLike('xs_appointments.notes', $query)
            ->groupEnd()
            ->orderBy('xs_appointments.start_at', 'DESC')
            ->limit($limit)
            ->findAll();

        foreach ($appointments as &$appointment) {
            if (!empty($appointment['start_at'])) {
                $appointment['start_at'] = TimezoneService::toDisplayIso($appointment['start_at']);
            }

            if (!empty($appointment['end_at'])) {
                $appointment['end_at'] = TimezoneService::toDisplayIso($appointment['end_at']);
            }
        }
        unset($appointment);

        return $appointments;
    }
}