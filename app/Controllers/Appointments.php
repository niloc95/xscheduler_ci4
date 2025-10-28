<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Models\ServiceModel;
use App\Models\AppointmentModel;
use App\Models\CustomerModel;
use App\Services\BookingSettingsService;
use App\Services\LocalizationSettingsService;
use App\Services\TimezoneService;
use CodeIgniter\Controller;

class Appointments extends BaseController
{
    protected $userModel;
    protected $serviceModel;
    protected $appointmentModel;
    protected $customerModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
        $this->serviceModel = new ServiceModel();
        $this->appointmentModel = new AppointmentModel();
        $this->customerModel = new CustomerModel();
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
     * Redirects to calendar - viewing is handled by JavaScript modal
     */
    public function view($appointmentId = null)
    {
        // Check authentication
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        // Redirect to appointments index
        // Viewing is now handled by the JavaScript modal in the calendar
        return redirect()->to('/appointments')->with('message', 'Please click the appointment in the calendar to view details.');
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
                'duration' => $service['duration_min'], // Map duration_min to duration for consistency
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
     * Store new appointment
     */
    public function store()
    {
        // Check authentication and permissions
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login')->with('error', 'Please log in to book an appointment');
        }

        if (!has_role(['customer', 'staff', 'provider', 'admin'])) {
            return redirect()->back()->with('error', 'Access denied');
        }

        $validation = \Config\Services::validation();
        
        // Validation rules
        $rules = [
            'provider_id' => 'required|is_natural_no_zero',
            'service_id' => 'required|is_natural_no_zero',
            'appointment_date' => 'required|valid_date',
            'appointment_time' => 'required',
            'customer_first_name' => 'required|min_length[2]|max_length[120]',
            'customer_last_name' => 'permit_empty|max_length[160]',
            'customer_email' => 'required|valid_email|max_length[255]',
            'customer_phone' => 'required|min_length[10]|max_length[32]',
            'customer_address' => 'permit_empty|max_length[255]',
            'notes' => 'permit_empty|max_length[1000]'
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()
                ->withInput()
                ->with('errors', $validation->getErrors());
        }

        // Get form data
        $providerId = $this->request->getPost('provider_id');
        $serviceId = $this->request->getPost('service_id');
        $appointmentDate = $this->request->getPost('appointment_date');
        $appointmentTime = $this->request->getPost('appointment_time');
        
        // Get service to calculate end time
        $service = $this->serviceModel->find($serviceId);
        if (!$service) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Invalid service selected');
        }

        // Determine client timezone context
        $clientTimezone = $this->resolveClientTimezone();

        // Construct local start DateTime in client timezone
        $startTimeLocal = $appointmentDate . ' ' . $appointmentTime . ':00';
        
        log_message('info', '[Appointments::store] ========== APPOINTMENT CREATION ==========');
        log_message('info', '[Appointments::store] Input from form:', [
            'provider_id' => $providerId,
            'service_id' => $serviceId,
            'date' => $appointmentDate,
            'time' => $appointmentTime,
            'duration' => $service['duration_min'] . ' minutes'
        ]);
        log_message('info', '[Appointments::store] Client timezone: ' . $clientTimezone);
        log_message('info', '[Appointments::store] Local datetime: ' . $startTimeLocal);
        
        try {
            $startDateTime = new \DateTime($startTimeLocal, new \DateTimeZone($clientTimezone));
        } catch (\Exception $e) {
            log_message('error', '[Appointments::store] Failed to create DateTime with timezone ' . $clientTimezone . ': ' . $e->getMessage());
            $startDateTime = new \DateTime($startTimeLocal, new \DateTimeZone('UTC'));
            $clientTimezone = 'UTC';
        }

        // Calculate local end time based on service duration
        $endDateTime = clone $startDateTime;
        $endDateTime->modify('+' . (int) $service['duration_min'] . ' minutes');

        // Convert to UTC for storage
        $startTimeUtc = TimezoneService::toUTC($startDateTime->format('Y-m-d H:i:s'), $clientTimezone);
        $endTimeUtc = TimezoneService::toUTC($endDateTime->format('Y-m-d H:i:s'), $clientTimezone);
        
        log_message('info', '[Appointments::store] Timezone conversion:', [
            'local_start' => $startDateTime->format('Y-m-d H:i:s'),
            'local_end' => $endDateTime->format('Y-m-d H:i:s'),
            'utc_start' => $startTimeUtc,
            'utc_end' => $endTimeUtc,
            'timezone' => $clientTimezone
        ]);
        log_message('info', '[Appointments::store] Will store in database as UTC');
        log_message('info', '[Appointments::store] =============================================');

        // Check if customer exists or create new one
        $customerEmail = $this->request->getPost('customer_email');
        $customer = $this->customerModel->where('email', $customerEmail)->first();

        if (!$customer) {
            // Create new customer
            $customerData = [
                'first_name' => $this->request->getPost('customer_first_name'),
                'last_name' => $this->request->getPost('customer_last_name'),
                'email' => $customerEmail,
                'phone' => $this->request->getPost('customer_phone'),
                'address' => $this->request->getPost('customer_address'),
                'notes' => $this->request->getPost('notes')
            ];

            // Handle custom fields if provided
            $customFieldsData = [];
            for ($i = 1; $i <= 6; $i++) {
                $fieldValue = $this->request->getPost("custom_field_{$i}");
                if ($fieldValue !== null && $fieldValue !== '') {
                    $customFieldsData["field_{$i}"] = $fieldValue;
                }
            }
            if (!empty($customFieldsData)) {
                $customerData['custom_fields'] = json_encode($customFieldsData);
            }

            $customerId = $this->customerModel->insert($customerData);
            
            if (!$customerId) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'Failed to create customer record');
            }
        } else {
            $customerId = $customer['id'];
        }

        // Create appointment
        $appointmentData = [
            'customer_id' => $customerId,
            'provider_id' => $providerId,
            'service_id' => $serviceId,
            'start_time' => $startTimeUtc,
            'end_time' => $endTimeUtc,
            'status' => 'booked',
            'notes' => $this->request->getPost('notes') ?? ''
        ];

        $appointmentId = $this->appointmentModel->insert($appointmentData);

        if (!$appointmentId) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to create appointment. Please try again.');
        }

        // Success - redirect to appointments list or view
        return redirect()->to('/appointments')
            ->with('success', 'Appointment booked successfully! Confirmation email will be sent shortly.');
    }

    private function resolveClientTimezone(): string
    {
        $session = session();

        $headerTimezone = trim((string) $this->request->getHeaderLine('X-Client-Timezone'));
        $headerOffset = trim((string) $this->request->getHeaderLine('X-Client-Offset'));

        $postTimezone = (string) $this->request->getPost('client_timezone');
        $postOffset = (string) $this->request->getPost('client_offset');

        $timezoneCandidate = $headerTimezone ?: $postTimezone;

        if ($timezoneCandidate && TimezoneService::isValidTimezone($timezoneCandidate)) {
            if ($session) {
                $session->set('client_timezone', $timezoneCandidate);
            }
        } elseif ($session && $session->has('client_timezone')) {
            $timezoneCandidate = (string) $session->get('client_timezone');
        } else {
            $timezoneCandidate = (new LocalizationSettingsService())->getTimezone();
        }

        $offsetCandidate = $headerOffset !== '' ? $headerOffset : $postOffset;
        if ($offsetCandidate !== '' && is_numeric($offsetCandidate) && $session) {
            $session->set('client_timezone_offset', (int) $offsetCandidate);
        }

        return $timezoneCandidate;
    }

    /**
     * Edit existing appointment (staff, provider, admin only)
     */
    public function edit($appointmentId = null)
    {
        // Check authentication and permissions
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        if (!has_role(['staff', 'provider', 'admin'])) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Access denied');
        }

        if (!$appointmentId) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Appointment not found');
        }

        // Load appointment with related data
        $appointment = $this->appointmentModel
            ->select('xs_appointments.*, 
                     c.first_name as customer_first_name,
                     c.last_name as customer_last_name,
                     c.email as customer_email,
                     c.phone as customer_phone,
                     c.address as customer_address,
                     c.notes as customer_notes,
                     c.custom_fields as customer_custom_fields')
            ->join('xs_customers c', 'c.id = xs_appointments.customer_id', 'left')
            ->find($appointmentId);

        if (!$appointment) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Appointment not found');
        }

        // Initialize services
        $bookingService = new BookingSettingsService();
        $localizationService = new LocalizationSettingsService();

        // Get field configuration from settings
        $fieldConfig = $bookingService->getFieldConfiguration();
        $customFields = $bookingService->getCustomFieldConfiguration();

        // Parse custom fields from JSON
        if (!empty($appointment['customer_custom_fields'])) {
            $customFieldsData = json_decode($appointment['customer_custom_fields'], true);
            foreach ($customFieldsData as $key => $value) {
                $appointment[$key] = $value;
            }
        }

        // Convert UTC times to local for display
        $clientTimezone = $this->resolveClientTimezone();
        if (!empty($appointment['start_time'])) {
            $startDateTime = new \DateTime($appointment['start_time'], new \DateTimeZone('UTC'));
            $startDateTime->setTimezone(new \DateTimeZone($clientTimezone));
            $appointment['date'] = $startDateTime->format('Y-m-d');
            $appointment['time'] = $startDateTime->format('H:i');
        }

        // Fetch providers and services
        $providers = $this->userModel->getProviders();
        $services = $this->serviceModel->findAll();

        // Format providers for dropdown
        $providersFormatted = array_map(function($provider) {
            return [
                'id' => $provider['id'],
                'name' => $provider['name'],
                'speciality' => 'Provider'
            ];
        }, $providers);

        // Format services for dropdown
        $servicesFormatted = array_map(function($service) {
            return [
                'id' => $service['id'],
                'name' => $service['name'],
                'duration' => $service['duration_min'],
                'price' => $service['price']
            ];
        }, $services);

        $data = [
            'title' => 'Edit Appointment',
            'current_page' => 'appointments',
            'appointment' => $appointment,
            'services' => $servicesFormatted,
            'providers' => $providersFormatted,
            'user_role' => current_user_role(),
            'fieldConfig' => $fieldConfig,
            'customFields' => $customFields,
            'localization' => $localizationService->getContext(),
        ];

        return view('appointments/edit', $data);
    }

    /**
     * Update existing appointment
     */
    public function update($appointmentId = null)
    {
        // Check authentication and permissions
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login')->with('error', 'Please log in to continue');
        }

        if (!has_role(['staff', 'provider', 'admin'])) {
            return redirect()->back()->with('error', 'Access denied');
        }

        if (!$appointmentId) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Appointment not found');
        }

        // Load existing appointment
        $existingAppointment = $this->appointmentModel->find($appointmentId);
        if (!$existingAppointment) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Appointment not found');
        }

        $validation = \Config\Services::validation();
        
        // Validation rules
        $rules = [
            'provider_id' => 'required|is_natural_no_zero',
            'service_id' => 'required|is_natural_no_zero',
            'appointment_date' => 'required|valid_date',
            'appointment_time' => 'required',
            'status' => 'required|in_list[booked,pending,confirmed,completed,cancelled,no-show]',
            'customer_first_name' => 'required|min_length[2]|max_length[120]',
            'customer_last_name' => 'permit_empty|max_length[160]',
            'customer_email' => 'required|valid_email|max_length[255]',
            'customer_phone' => 'required|min_length[10]|max_length[32]',
            'customer_address' => 'permit_empty|max_length[255]',
            'notes' => 'permit_empty|max_length[1000]'
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()
                ->withInput()
                ->with('errors', $validation->getErrors());
        }

        // Get form data
        $providerId = $this->request->getPost('provider_id');
        $serviceId = $this->request->getPost('service_id');
        $appointmentDate = $this->request->getPost('appointment_date');
        $appointmentTime = $this->request->getPost('appointment_time');
        $status = $this->request->getPost('status');
        
        // Get service to calculate end time
        $service = $this->serviceModel->find($serviceId);
        if (!$service) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Invalid service selected');
        }

        // Determine client timezone
        $clientTimezone = $this->resolveClientTimezone();

        // Construct local start DateTime
        $startTimeLocal = $appointmentDate . ' ' . $appointmentTime . ':00';
        
        log_message('info', '[Appointments::update] Updating appointment #' . $appointmentId);
        log_message('info', '[Appointments::update] Input: date=' . $appointmentDate . ', time=' . $appointmentTime);
        log_message('info', '[Appointments::update] Client timezone: ' . $clientTimezone);
        
        try {
            $startDateTime = new \DateTime($startTimeLocal, new \DateTimeZone($clientTimezone));
        } catch (\Exception $e) {
            log_message('error', '[Appointments::update] DateTime error: ' . $e->getMessage());
            $startDateTime = new \DateTime($startTimeLocal, new \DateTimeZone('UTC'));
            $clientTimezone = 'UTC';
        }

        // Calculate end time based on service duration
        $endDateTime = clone $startDateTime;
        $endDateTime->modify('+' . (int) $service['duration_min'] . ' minutes');

        // Convert to UTC for storage
        $startTimeUtc = TimezoneService::toUTC($startDateTime->format('Y-m-d H:i:s'), $clientTimezone);
        $endTimeUtc = TimezoneService::toUTC($endDateTime->format('Y-m-d H:i:s'), $clientTimezone);
        
        log_message('info', '[Appointments::update] UTC times: start=' . $startTimeUtc . ', end=' . $endTimeUtc);

        // Update customer record
        $customerId = $existingAppointment['customer_id'];
        $customerData = [
            'first_name' => $this->request->getPost('customer_first_name'),
            'last_name' => $this->request->getPost('customer_last_name'),
            'email' => $this->request->getPost('customer_email'),
            'phone' => $this->request->getPost('customer_phone'),
            'address' => $this->request->getPost('customer_address')
        ];

        // Handle custom fields
        $customFieldsData = [];
        for ($i = 1; $i <= 6; $i++) {
            $fieldValue = $this->request->getPost("custom_field_{$i}");
            if ($fieldValue !== null && $fieldValue !== '') {
                $customFieldsData["field_{$i}"] = $fieldValue;
            }
        }
        if (!empty($customFieldsData)) {
            $customerData['custom_fields'] = json_encode($customFieldsData);
        }

        $this->customerModel->update($customerId, $customerData);

        // Update appointment
        $appointmentData = [
            'provider_id' => $providerId,
            'service_id' => $serviceId,
            'start_time' => $startTimeUtc,
            'end_time' => $endTimeUtc,
            'status' => $status,
            'notes' => $this->request->getPost('notes') ?? ''
        ];

        $updated = $this->appointmentModel->update($appointmentId, $appointmentData);

        if (!$updated) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to update appointment. Please try again.');
        }

        log_message('info', '[Appointments::update] Successfully updated appointment #' . $appointmentId);

        // Success
        return redirect()->to('/appointments')
            ->with('success', 'Appointment updated successfully!');
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
