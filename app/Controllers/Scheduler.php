<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Models\ServiceModel;
use App\Models\AppointmentModel;
use App\Models\BlockedTimeModel;

class Scheduler extends BaseController
{
    protected $userModel;
    protected $serviceModel;
    protected $appointmentModel;
    protected $blockedTimeModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
        $this->serviceModel = new ServiceModel();
        $this->appointmentModel = new AppointmentModel();
        $this->blockedTimeModel = new BlockedTimeModel();
        helper(['form', 'url']);
    }

    /**
     * Main calendar view - shows appointments in calendar format
     */
    public function index()
    {
        // Get current date or requested date
        $date = $this->request->getGet('date') ?? date('Y-m-d');
        $view = $this->request->getGet('view') ?? 'month';

        try {
            // Get appointments for the current period
            $appointments = $this->getAppointmentsForPeriod($date, $view);
            
            // Get all services for the booking form
            $services = $this->serviceModel->findAll();
            
            // Get all providers
            $providers = $this->userModel->where('role', 'provider')->findAll();

            $data = [
                'title' => 'Scheduler - Calendar View',
                'current_date' => $date,
                'view_type' => $view,
                'appointments' => $appointments,
                'services' => $services,
                'providers' => $providers,
                'user' => session()->get('user') ?? ['name' => 'Admin', 'role' => 'admin']
            ];

            return view('scheduler/calendar', $data);
        } catch (\Exception $e) {
            log_message('error', 'Scheduler Error: ' . $e->getMessage());
            
            // Fallback data if database is not available
            $data = [
                'title' => 'Scheduler - Calendar View',
                'current_date' => $date,
                'view_type' => $view,
                'appointments' => [],
                'services' => [],
                'providers' => [],
                'user' => ['name' => 'Admin', 'role' => 'admin'],
                'error' => 'Unable to load scheduler data. Please check your database connection.'
            ];

            return view('scheduler/calendar', $data);
        }
    }

    /**
     * Show booking form for creating new appointments
     */
    public function book()
    {
        try {
            // Get all services for selection
            $services = $this->serviceModel->findAll();
            
            // Get all providers
            $providers = $this->userModel->where('role', 'provider')->findAll();

            $data = [
                'title' => 'Book Appointment',
                'services' => $services,
                'providers' => $providers,
                'selected_date' => $this->request->getGet('date'),
                'selected_time' => $this->request->getGet('time')
            ];

            return view('scheduler/book', $data);
        } catch (\Exception $e) {
            return redirect()->to('/scheduler')->with('error', 'Unable to load booking form: ' . $e->getMessage());
        }
    }

    /**
     * Process appointment booking
     */
    public function processBooking()
    {
        // CSRF validation
        if (!$this->validate(['csrf_test_name' => 'required'])) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Invalid request. Please try again.'
            ])->setStatusCode(400);
        }

        $rules = [
            'service_id' => 'required|integer',
            'provider_id' => 'required|integer',
            'appointment_date' => 'required|valid_date',
            'appointment_time' => 'required',
            'customer_name' => 'required|min_length[2]|max_length[100]',
            'customer_email' => 'required|valid_email|max_length[100]',
            'customer_phone' => 'permit_empty|max_length[20]'
        ];

        if (!$this->validate($rules)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $this->validator->getErrors()
            ])->setStatusCode(400);
        }

        try {
            $data = $this->request->getPost();
            
            // Combine date and time
            $startTime = $data['appointment_date'] . ' ' . $data['appointment_time'];
            
            // Get service to calculate end time
            $service = $this->serviceModel->find($data['service_id']);
            if (!$service) {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Selected service not found.'
                ])->setStatusCode(400);
            }

            $endTime = date('Y-m-d H:i:s', strtotime($startTime . ' +' . $service['duration_min'] . ' minutes'));

            // Check if time slot is available
            if (!$this->isTimeSlotAvailable($data['provider_id'], $startTime, $endTime)) {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'The selected time slot is not available. Please choose a different time.'
                ])->setStatusCode(400);
            }

            // Create or find customer user
            $customerId = $this->findOrCreateCustomer($data);

            // Create appointment
            $appointmentData = [
                'user_id' => $customerId,
                'provider_id' => $data['provider_id'],
                'service_id' => $data['service_id'],
                'start_time' => $startTime,
                'end_time' => $endTime,
                'status' => 'booked',
                'notes' => $data['notes'] ?? ''
            ];

            $appointmentId = $this->appointmentModel->insert($appointmentData);

            if ($appointmentId) {
                return $this->response->setJSON([
                    'success' => true,
                    'message' => 'Appointment booked successfully!',
                    'appointment_id' => $appointmentId,
                    'redirect' => '/scheduler'
                ]);
            } else {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Failed to create appointment. Please try again.'
                ])->setStatusCode(500);
            }

        } catch (\Exception $e) {
            log_message('error', 'Appointment booking failed: ' . $e->getMessage());
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Booking failed: ' . $e->getMessage()
            ])->setStatusCode(500);
        }
    }

    /**
     * Get available time slots for a specific date and provider
     */
    public function getAvailableSlots()
    {
        $providerId = $this->request->getGet('provider_id');
        $date = $this->request->getGet('date');
        $serviceId = $this->request->getGet('service_id');

        if (!$providerId || !$date || !$serviceId) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Provider ID, date, and service ID are required.'
            ])->setStatusCode(400);
        }

        try {
            $service = $this->serviceModel->find($serviceId);
            if (!$service) {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Service not found.'
                ])->setStatusCode(404);
            }

            $availableSlots = $this->calculateAvailableSlots($providerId, $date, $service['duration_min']);

            return $this->response->setJSON([
                'success' => true,
                'slots' => $availableSlots
            ]);

        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Unable to fetch available slots: ' . $e->getMessage()
            ])->setStatusCode(500);
        }
    }

    /**
     * Get appointments for a specific period (day, week, month)
     */
    protected function getAppointmentsForPeriod($date, $view)
    {
        switch ($view) {
            case 'day':
                $startDate = $date . ' 00:00:00';
                $endDate = $date . ' 23:59:59';
                break;
            case 'week':
                $startDate = date('Y-m-d 00:00:00', strtotime('monday this week', strtotime($date)));
                $endDate = date('Y-m-d 23:59:59', strtotime('sunday this week', strtotime($date)));
                break;
            case 'month':
            default:
                $startDate = date('Y-m-01 00:00:00', strtotime($date));
                $endDate = date('Y-m-t 23:59:59', strtotime($date));
                break;
        }

        return $this->appointmentModel
                   ->where('start_time >=', $startDate)
                   ->where('start_time <=', $endDate)
                   ->orderBy('start_time', 'ASC')
                   ->findAll();
    }

    /**
     * Check if a time slot is available
     */
    protected function isTimeSlotAvailable($providerId, $startTime, $endTime)
    {
        // Check for existing appointments
        $existingAppointments = $this->appointmentModel
                                    ->where('provider_id', $providerId)
                                    ->where('status !=', 'cancelled')
                                    ->groupStart()
                                        ->where('start_time <', $endTime)
                                        ->where('end_time >', $startTime)
                                    ->groupEnd()
                                    ->findAll();

        if (!empty($existingAppointments)) {
            return false;
        }

        // Check for blocked times
        return !$this->blockedTimeModel->isTimeBlocked($providerId, $startTime, $endTime);
    }

    /**
     * Calculate available time slots for a date
     */
    protected function calculateAvailableSlots($providerId, $date, $durationMinutes)
    {
        $slots = [];
        
        // Business hours (9 AM to 5 PM)
        $startHour = 9;
        $endHour = 17;
        
        for ($hour = $startHour; $hour < $endHour; $hour++) {
            for ($minute = 0; $minute < 60; $minute += 30) { // 30-minute intervals
                $timeSlot = sprintf('%02d:%02d', $hour, $minute);
                $startTime = $date . ' ' . $timeSlot . ':00';
                $endTime = date('Y-m-d H:i:s', strtotime($startTime . ' +' . $durationMinutes . ' minutes'));
                
                // Don't offer slots that would extend past business hours
                if (date('H', strtotime($endTime)) >= $endHour) {
                    continue;
                }
                
                if ($this->isTimeSlotAvailable($providerId, $startTime, $endTime)) {
                    $slots[] = [
                        'time' => $timeSlot,
                        'display' => date('g:i A', strtotime($timeSlot)),
                        'available' => true
                    ];
                }
            }
        }
        
        return $slots;
    }

    /**
     * Find existing customer or create new one
     */
    protected function findOrCreateCustomer($data)
    {
        // Try to find existing customer
        $existingUser = $this->userModel->where('email', $data['customer_email'])->first();
        
        if ($existingUser) {
            return $existingUser['id'];
        }

        // Create new customer
        $customerData = [
            'name' => $data['customer_name'],
            'email' => $data['customer_email'],
            'phone' => $data['customer_phone'] ?? '',
            'role' => 'customer',
            'password_hash' => '' // Customers don't need passwords for booking
        ];

        return $this->userModel->insert($customerData);
    }

    /**
     * Check if setup has been completed
     */
    private function isSetupCompleted(): bool
    {
        $flagPath = WRITEPATH . 'setup_completed.flag';
        return file_exists($flagPath);
    }
}