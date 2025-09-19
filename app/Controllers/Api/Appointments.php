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
            $builder = $model->orderBy('start_time', 'ASC');
            
            // Apply date range filter
            if ($start && $end) {
                $builder->where('start_time >=', $start . ' 00:00:00')
                        ->where('start_time <=', $end . ' 23:59:59');
            }
            
            // Apply optional filters
            if ($providerId) {
                $builder->where('provider_id', (int)$providerId);
            }
            
            if ($serviceId) {
                $builder->where('service_id', (int)$serviceId);
            }
            
            $appointments = $builder->findAll();
            
            // Transform data for FullCalendar
            $events = array_map(function($appointment) {
                return [
                    'id' => $appointment['id'],
                    'title' => 'Appointment #' . $appointment['id'],
                    'start' => $appointment['start_time'],
                    'end' => $appointment['end_time'],
                    'providerId' => $appointment['provider_id'],
                    'serviceId' => $appointment['service_id'],
                    'status' => $appointment['status']
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
