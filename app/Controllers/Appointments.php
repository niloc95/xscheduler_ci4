<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Models\ServiceModel;
use App\Services\BookingSettingsService;
use App\Services\LocalizationSettingsService;
use CodeIgniter\Controller;

class Appointments extends BaseController
{
    protected $userModel;
    protected $serviceModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
        $this->serviceModel = new ServiceModel();
        helper('permissions');
    }

    /**
     * Display appointments list
     */
    public function index()
    {
        // Check authentication
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        $currentUser = session()->get('user');
        $currentUserId = session()->get('user_id');
        $currentRole = current_user_role();

        // Mock appointment data - in real implementation, this would come from AppointmentModel
        $appointments = $this->getMockAppointments($currentRole, $currentUserId);
        
        // Get active providers with colors for legend
        $activeProviders = $this->userModel
            ->where('role', 'provider')
            ->where('is_active', true)
            ->where('color IS NOT NULL')
            ->where('color !=', '')
            ->orderBy('name', 'ASC')
            ->findAll();
        
        $data = [
            'title' => $currentRole === 'customer' ? 'My Appointments' : 'Appointments',
            'current_page' => 'appointments',
            'appointments' => $appointments,
            'user_role' => $currentRole,
            'user' => $currentUser,
            'stats' => $this->getAppointmentStats($currentRole, $currentUserId),
            'activeProviders' => $activeProviders
        ];

        return view('appointments/index', $data);
    }

    /**
     * View specific appointment
     */
    public function view($appointmentId = null)
    {
        // Check authentication
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        if (!$appointmentId) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Appointment not found');
        }

        // Mock appointment data
        $appointment = $this->getMockAppointment($appointmentId);
        
        $data = [
            'title' => 'Appointment Details',
            'current_page' => 'appointments',
            'appointment' => $appointment,
            'user_role' => current_user_role()
        ];

        return view('appointments/view', $data);
    }

    /**
     * Create new appointment (customers and staff)
     */
    public function create()
    {
        // Check authentication and permissions
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        if (!has_role(['customer', 'staff', 'provider', 'admin'])) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Access denied');
        }

        // Initialize services
        $bookingService = new BookingSettingsService();
        $localizationService = new LocalizationSettingsService();

        // Get field configuration from settings
        $fieldConfig = $bookingService->getFieldConfiguration();
        $customFields = $bookingService->getCustomFieldConfiguration();
        $localizationContext = $localizationService->getContext();

        // Fetch real providers and services from database
        $providers = $this->userModel->getProviders();
        $services = $this->serviceModel->findAll();

        // Format providers for dropdown
        $providersFormatted = array_map(function($provider) {
            return [
                'id' => $provider['id'],
                'name' => $provider['name'],
                'speciality' => 'Provider' // TODO: Add speciality field to users table
            ];
        }, $providers);

        // Format services for dropdown
        $servicesFormatted = array_map(function($service) {
            return [
                'id' => $service['id'],
                'name' => $service['name'],
                'duration' => $service['duration'],
                'price' => $service['price']
            ];
        }, $services);

        $data = [
            'title' => 'Book Appointment',
            'current_page' => 'appointments',
            'services' => $servicesFormatted,
            'providers' => $providersFormatted,
            'user_role' => current_user_role(),
            'fieldConfig' => $fieldConfig,
            'customFields' => $customFields,
            'localization' => $localizationContext,
        ];

        return view('appointments/create', $data);
    }

    /**
     * Get mock appointment data based on role
     */
    private function getMockAppointments($role, $userId)
    {
        $baseAppointments = [
            [
                'id' => 1,
                'customer_name' => 'John Smith',
                'customer_email' => 'john@example.com',
                'service' => 'Hair Cut',
                'provider' => 'Sarah Johnson',
                'date' => '2025-09-05',
                'time' => '10:00',
                'duration' => 60,
                'status' => 'confirmed',
                'notes' => 'Regular trim'
            ],
            [
                'id' => 2,
                'customer_name' => 'Emma Davis',
                'customer_email' => 'emma@example.com',
                'service' => 'Color Treatment',
                'provider' => 'Sarah Johnson',
                'date' => '2025-09-05',
                'time' => '14:30',
                'duration' => 120,
                'status' => 'pending',
                'notes' => 'Full highlights'
            ],
            [
                'id' => 3,
                'customer_name' => 'Mike Wilson',
                'customer_email' => 'mike@example.com',
                'service' => 'Beard Trim',
                'provider' => 'Alex Brown',
                'date' => '2025-09-06',
                'time' => '11:30',
                'duration' => 30,
                'status' => 'completed',
                'notes' => ''
            ]
        ];

        // Filter based on role
        if ($role === 'customer') {
            $userEmail = session()->get('user')['email'];
            return array_filter($baseAppointments, function($apt) use ($userEmail) {
                return $apt['customer_email'] === $userEmail;
            });
        }

        return $baseAppointments;
    }

    /**
     * Get mock appointment by ID
     */
    private function getMockAppointment($id)
    {
        $appointments = $this->getMockAppointments('admin', null);
        foreach ($appointments as $appointment) {
            if ($appointment['id'] == $id) {
                return $appointment;
            }
        }
        return null;
    }

    /**
     * Get appointment statistics
     */
    private function getAppointmentStats($role, $userId)
    {
        return [
            'total' => 24,
            'today' => 3,
            'pending' => 5,
            'completed' => 18,
            'cancelled' => 1
        ];
    }

    /**
     * Get mock services
     */
    private function getMockServices()
    {
        return [
            ['id' => 1, 'name' => 'Hair Cut', 'duration' => 60, 'price' => 35.00],
            ['id' => 2, 'name' => 'Color Treatment', 'duration' => 120, 'price' => 85.00],
            ['id' => 3, 'name' => 'Beard Trim', 'duration' => 30, 'price' => 20.00],
            ['id' => 4, 'name' => 'Hair Wash & Style', 'duration' => 45, 'price' => 25.00]
        ];
    }

    /**
     * Get mock providers
     */
    private function getMockProviders()
    {
        return [
            ['id' => 1, 'name' => 'Sarah Johnson', 'speciality' => 'Hair Styling'],
            ['id' => 2, 'name' => 'Alex Brown', 'speciality' => 'Barber Services'],
            ['id' => 3, 'name' => 'Maria Garcia', 'speciality' => 'Color Specialist']
        ];
    }
}
