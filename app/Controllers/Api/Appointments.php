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
    
    /**
     * Get a single appointment by ID with full details
     * GET /api/appointments/:id
     */
    public function show($id = null)
    {
        $response = $this->response->setHeader('Content-Type', 'application/json');
        
        if (!$id) {
            return $response->setStatusCode(400)->setJSON([
                'error' => [
                    'message' => 'Appointment ID is required'
                ]
            ]);
        }
        
        try {
            $model = new AppointmentModel();
            $builder = $model->builder();
            
            // Select appointment with all related data
            $builder->select('appointments.*, 
                             CONCAT(c.first_name, " ", c.last_name) as customer_name,
                             c.email as customer_email,
                             c.phone_number as customer_phone,
                             s.name as service_name,
                             s.duration_min as duration,
                             s.price as price,
                             CONCAT(p.first_name, " ", p.last_name) as provider_name,
                             p.color as provider_color')
                    ->join('customers c', 'c.id = appointments.customer_id', 'left')
                    ->join('services s', 's.id = appointments.service_id', 'left')
                    ->join('users p', 'p.id = appointments.provider_id', 'left')
                    ->where('appointments.id', (int)$id);
            
            $appointment = $builder->get()->getRowArray();
            
            if (!$appointment) {
                return $response->setStatusCode(404)->setJSON([
                    'error' => [
                        'message' => 'Appointment not found'
                    ]
                ]);
            }
            
            // Format response
            $data = [
                'id' => $appointment['id'],
                'customer_id' => $appointment['customer_id'],
                'customer_name' => $appointment['customer_name'] ?? 'N/A',
                'customer_email' => $appointment['customer_email'] ?? '',
                'customer_phone' => $appointment['customer_phone'] ?? '',
                'provider_id' => $appointment['provider_id'],
                'provider_name' => $appointment['provider_name'] ?? 'N/A',
                'provider_color' => $appointment['provider_color'] ?? '#3B82F6',
                'service_id' => $appointment['service_id'],
                'service_name' => $appointment['service_name'] ?? 'N/A',
                'start_time' => $appointment['start_time'],
                'end_time' => $appointment['end_time'],
                'start' => $appointment['start_time'], // FullCalendar compatibility
                'end' => $appointment['end_time'],     // FullCalendar compatibility
                'duration' => $appointment['duration'],
                'price' => $appointment['price'],
                'status' => $appointment['status'],
                'notes' => $appointment['notes'] ?? '',
                'location' => $appointment['location'] ?? '',
                'is_paid' => $appointment['is_paid'] ?? false,
                'created_at' => $appointment['created_at'] ?? null,
                'updated_at' => $appointment['updated_at'] ?? null,
            ];
            
            return $response->setJSON([
                'data' => $data
            ]);
            
        } catch (\Exception $e) {
            return $response->setStatusCode(500)->setJSON([
                'error' => [
                    'message' => 'Failed to fetch appointment',
                    'details' => $e->getMessage()
                ]
            ]);
        }
    }
    
    /**
     * Update appointment status
     * PATCH /api/appointments/:id/status
     */
    public function updateStatus($id = null)
    {
        $response = $this->response->setHeader('Content-Type', 'application/json');
        
        if (!$id) {
            return $response->setStatusCode(400)->setJSON([
                'error' => [
                    'message' => 'Appointment ID is required'
                ]
            ]);
        }
        
        try {
            // Get JSON input
            $json = $this->request->getJSON(true);
            $newStatus = $json['status'] ?? null;
            
            if (!$newStatus) {
                return $response->setStatusCode(400)->setJSON([
                    'error' => [
                        'message' => 'Status is required'
                    ]
                ]);
            }
            
            // Validate status
            $validStatuses = ['pending', 'confirmed', 'completed', 'cancelled', 'no-show'];
            if (!in_array($newStatus, $validStatuses)) {
                return $response->setStatusCode(400)->setJSON([
                    'error' => [
                        'message' => 'Invalid status',
                        'valid_statuses' => $validStatuses
                    ]
                ]);
            }
            
            // Update appointment
            $model = new AppointmentModel();
            $appointment = $model->find($id);
            
            if (!$appointment) {
                return $response->setStatusCode(404)->setJSON([
                    'error' => [
                        'message' => 'Appointment not found'
                    ]
                ]);
            }
            
            $updateData = [
                'status' => $newStatus,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $updated = $model->update($id, $updateData);
            
            if (!$updated) {
                return $response->setStatusCode(500)->setJSON([
                    'error' => [
                        'message' => 'Failed to update appointment status'
                    ]
                ]);
            }
            
            return $response->setJSON([
                'data' => [
                    'id' => $id,
                    'status' => $newStatus,
                    'updated_at' => $updateData['updated_at']
                ],
                'message' => 'Appointment status updated successfully'
            ]);
            
        } catch (\Exception $e) {
            return $response->setStatusCode(500)->setJSON([
                'error' => [
                    'message' => 'Failed to update appointment status',
                    'details' => $e->getMessage()
                ]
            ]);
        }
    }
}
