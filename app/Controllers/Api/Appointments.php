<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\AppointmentModel;

class Appointments extends BaseController
{
    public function index()
    {
        $response = $this->response->setHeader('Content-Type', 'application/json');
        
        try {
            // Get query parameters
            $start = $this->request->getGet('start');
            $end = $this->request->getGet('end');
            $providerId = $this->request->getGet('providerId');
            $serviceId = $this->request->getGet('serviceId');
            
            $model = new AppointmentModel();
            $builder = $model->builder();
            
            // Select appointments with related data including provider color
            $builder->select('appointments.*, 
                             CONCAT(c.first_name, " ", c.last_name) as customer_name,
                             s.name as service_name,
                             s.duration_min as service_duration,
                             CONCAT(p.first_name, " ", p.last_name) as provider_name,
                             p.color as provider_color')
                    ->join('customers c', 'c.id = appointments.customer_id', 'left')
                    ->join('services s', 's.id = appointments.service_id', 'left')
                    ->join('users p', 'p.id = appointments.provider_id', 'left')
                    ->orderBy('appointments.start_time', 'ASC');
            
            // Apply date range filter
            if ($start && $end) {
                $builder->where('appointments.start_time >=', $start . ' 00:00:00')
                        ->where('appointments.start_time <=', $end . ' 23:59:59');
            }
            
            // Apply optional filters
            if ($providerId) {
                $builder->where('appointments.provider_id', (int)$providerId);
            }
            
            if ($serviceId) {
                $builder->where('appointments.service_id', (int)$serviceId);
            }
            
            $appointments = $builder->get()->getResultArray();
            
            // Transform data for FullCalendar with provider colors
            $events = array_map(function($appointment) {
                return [
                    'id' => $appointment['id'],
                    'title' => $appointment['customer_name'] ?? 'Appointment #' . $appointment['id'],
                    'start' => $appointment['start_time'],
                    'end' => $appointment['end_time'],
                    'providerId' => $appointment['provider_id'],
                    'serviceId' => $appointment['service_id'],
                    'status' => $appointment['status'],
                    'name' => $appointment['customer_name'] ?? null,
                    'serviceName' => $appointment['service_name'] ?? null,
                    'providerName' => $appointment['provider_name'] ?? null,
                    'provider_color' => $appointment['provider_color'] ?? '#3B82F6', // Default blue
                    'serviceDuration' => $appointment['service_duration'] ?? null,
                    'notes' => $appointment['notes'] ?? null,
                ];
            }, $appointments);
            
            return $response->setJSON([
                'data' => $events,
                'meta' => [
                    'total' => count($events),
                    'filters' => [
                        'start' => $start,
                        'end' => $end,
                        'providerId' => $providerId,
                        'serviceId' => $serviceId
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            return $response->setStatusCode(500)->setJSON([
                'error' => [
                    'message' => 'Failed to fetch appointments',
                    'details' => $e->getMessage()
                ]
            ]);
        }
    }
}
