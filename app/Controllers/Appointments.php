<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Models\ServiceModel;
use App\Models\AppointmentModel;
use App\Models\CustomerModel;
use App\Services\AppointmentDashboardContextService;
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
    protected $dashboardContextService;

    public function __construct()
    {
        $this->userModel = new UserModel();
        $this->serviceModel = new ServiceModel();
        $this->appointmentModel = new AppointmentModel();
        $this->customerModel = new CustomerModel();
        $this->dashboardContextService = new AppointmentDashboardContextService();
        helper(['permissions', 'ui']);
    }

    /**
     * Get formatted providers list for dropdown
     */
    private function getFormattedProviders(): array
    {
        $providers = $this->userModel->getProviders();
        
        return array_map(function($provider) {
            return [
                'id' => $provider['id'],
                'name' => $provider['name'],
                'speciality' => 'Provider' // TODO: Add speciality field to users table
            ];
        }, $providers);
    }

    /**
     * Get booking configuration (field config, custom fields, localization)
     */
    private function getBookingConfiguration(): array
    {
        $bookingService = new BookingSettingsService();
        $localizationService = new LocalizationSettingsService();
        
        return [
            'fieldConfig' => $bookingService->getFieldConfiguration(),
            'customFields' => $bookingService->getCustomFieldConfiguration(),
            'localization' => $localizationService->getContext()
        ];
    }

    /**
     * Extract custom fields from POST data
     */
    private function extractCustomFields(): ?string
    {
        $customFieldsData = [];
        for ($i = 1; $i <= 6; $i++) {
            $fieldValue = $this->request->getPost("custom_field_{$i}");
            if ($fieldValue !== null && $fieldValue !== '') {
                $customFieldsData["custom_field_{$i}"] = $fieldValue;
            }
        }
        
        return !empty($customFieldsData) ? json_encode($customFieldsData) : null;
    }

    /**
     * Calendar prototype feature is archived - always return disabled
     */
    private function resolveCalendarPrototypeContext(): array
    {
        return ['enabled' => false];
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

        $statusFilter = $this->appointmentModel->normalizeStatusFilter($this->request->getGet('status'));
        $providerFilter = $this->request->getGet('provider_id');
        $serviceFilter = $this->request->getGet('service_id');
        
        $context = $this->dashboardContextService->build($currentRole, $currentUserId, $currentUser);

        $appointments = $this->appointmentModel->getDashboardAppointments($statusFilter, $context);
        
        // Get active providers with colors for legend
        $activeProviders = $this->userModel
            ->where('role', 'provider')
            ->where('is_active', true)
            ->where('color IS NOT NULL')
            ->where('color !=', '')
            ->orderBy('name', 'ASC')
            ->findAll();
        
        // Get all providers for filter dropdown
        $allProviders = $this->userModel
            ->where('role', 'provider')
            ->where('is_active', true)
            ->orderBy('name', 'ASC')
            ->findAll();
        
        // Get all services for filter dropdown
        $allServices = $this->serviceModel
            ->where('is_active', true)
            ->orderBy('name', 'ASC')
            ->findAll();
        
        $stats = $this->appointmentModel->getStats($context, $statusFilter);
        $calendarPrototype = $this->resolveCalendarPrototypeContext();
        
        // Build current filters array for the view
        $currentFilters = [
            'status' => $statusFilter,
            'provider_id' => $providerFilter,
            'service_id' => $serviceFilter,
        ];

        $data = [
            'title' => $currentRole === 'customer' ? 'My Appointments' : 'Appointments',
            'current_page' => 'appointments',
            'appointments' => $appointments,
            'user_role' => $currentRole,
            'user' => $currentUser,
            'stats' => $stats,
            'upcomingCount' => $stats['upcoming'] ?? 0,
            'completedCount' => $stats['completed'] ?? 0,
            'activeProviders' => $activeProviders,
            'allProviders' => $allProviders,
            'allServices' => $allServices,
            'currentFilters' => $currentFilters,
            'activeStatusFilter' => $statusFilter,
            'calendarPrototype' => $calendarPrototype,
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

        // Get booking configuration and providers
        $config = $this->getBookingConfiguration();
        $providersFormatted = $this->getFormattedProviders();

        $data = [
            'title' => 'Book Appointment',
            'current_page' => 'appointments',
            'providers' => $providersFormatted,
            'user_role' => current_user_role(),
            'appointment' => [], // Empty for create mode
            'formAction' => base_url('appointments/store'),
        ] + $config;

        return view('appointments/form', $data);
    }

    /**
     * Store new appointment
     */
    public function store()
    {
        log_message('info', '[Appointments::store] ========== STORE METHOD CALLED ==========');
        log_message('info', '[Appointments::store] POST data: ' . json_encode($this->request->getPost()));
        
        // Check authentication and permissions
        if (!session()->get('isLoggedIn')) {
            log_message('error', '[Appointments::store] Not logged in');
            return redirect()->to('/auth/login')->with('error', 'Please log in to book an appointment');
        }

        if (!has_role(['customer', 'staff', 'provider', 'admin'])) {
            log_message('error', '[Appointments::store] Access denied - role check failed');
            return redirect()->back()->with('error', 'Access denied');
        }

        $validation = \Config\Services::validation();
        
        // Check if customer_id is provided (from search)
        $customerId = $this->request->getPost('customer_id');
        
        // Validation rules - adjust based on whether customer is selected or new
        if ($customerId) {
            // Customer selected from search - minimal validation
            $rules = [
                'provider_id' => 'required|is_natural_no_zero',
                'service_id' => 'required|is_natural_no_zero',
                'appointment_date' => 'required|valid_date',
                'appointment_time' => 'required',
                'customer_id' => 'required|is_natural_no_zero',
                'notes' => 'permit_empty|max_length[1000]'
            ];
        } else {
            // New customer - full validation
            // Phone formats supported: +27 82 529 7070 (international) or 082 529 2242 (local)
            $rules = [
                'provider_id' => 'required|is_natural_no_zero',
                'service_id' => 'required|is_natural_no_zero',
                'appointment_date' => 'required|valid_date',
                'appointment_time' => 'required',
                'customer_first_name' => 'required|min_length[2]|max_length[120]',
                'customer_last_name' => 'permit_empty|max_length[160]',
                'customer_email' => 'required|valid_email|max_length[255]',
                'customer_phone' => 'required|min_length[10]|max_length[20]',
                'customer_address' => 'permit_empty|max_length[255]',
                'notes' => 'permit_empty|max_length[1000]'
            ];
        }

        if (!$this->validate($rules)) {
            log_message('error', '[Appointments::store] Validation failed: ' . json_encode($validation->getErrors()));
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
        
        // Create DateTime WITHOUT timezone to keep values as-is (already in local time)
        // The form sends times in the user's local timezone, so we store them as-is
        $startDateTime = new \DateTime($startTimeLocal);

        // Calculate local end time based on service duration
        $endDateTime = clone $startDateTime;
        $endDateTime->modify('+' . (int) $service['duration_min'] . ' minutes');

        // Format times for storage (database stores in LOCAL timezone as-is)
        $startTimeForDb = $startDateTime->format('Y-m-d H:i:s');
        $endTimeForDb = $endDateTime->format('Y-m-d H:i:s');

        // Handle customer - either use existing or create new
        if ($customerId) {
            // Customer selected from search
            $customer = $this->customerModel->find($customerId);
            
            if (!$customer) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'Selected customer not found');
            }
        } else {
            // Check if customer exists by email or create new one
            $customerEmail = $this->request->getPost('customer_email');
            $customer = $this->customerModel->where('email', $customerEmail)->first();

            if (!$customer) {
                // Create new customer
                $now = date('Y-m-d H:i:s');
                $customerData = [
                    'first_name' => $this->request->getPost('customer_first_name'),
                    'last_name' => $this->request->getPost('customer_last_name'),
                    'email' => $customerEmail,
                    'phone' => $this->request->getPost('customer_phone'),
                    'address' => $this->request->getPost('customer_address'),
                    'notes' => $this->request->getPost('notes'),
                    'created_at' => $now,
                    'address' => $this->request->getPost('customer_address')
                ];

                // Handle custom fields if provided
                $customFieldsJson = $this->extractCustomFields();
                if ($customFieldsJson) {
                    $customerData['custom_fields'] = $customFieldsJson;
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
        }

        // Validate availability before creating appointment
        // NOTE: AvailabilityService expects LOCAL times (database stores local times)
        $availabilityService = new \App\Services\AvailabilityService();
        $availabilityCheck = $availabilityService->isSlotAvailable(
            $providerId,
            $startTimeForDb,
            $endTimeForDb,
            $clientTimezone
        );

        if (!$availabilityCheck['available']) {
            log_message('warning', '[Appointments::store] Slot not available: ' . $availabilityCheck['reason']);
            return redirect()->back()
                ->withInput()
                ->with('error', 'This time slot is not available. ' . $availabilityCheck['reason']);
        }

        // Create appointment
        $appointmentData = [
            'customer_id' => $customerId,
            'provider_id' => $providerId,
            'service_id' => $serviceId,
            'start_time' => $startTimeForDb,  // Store in local timezone
            'end_time' => $endTimeForDb,      // Store in local timezone
            'status' => 'pending', // Valid enum values: pending, confirmed, completed, cancelled, no-show
            'notes' => $this->request->getPost('notes') ?? ''
        ];

        $appointmentId = $this->appointmentModel->insert($appointmentData);

        if (!$appointmentId) {
            $errors = $this->appointmentModel->errors();
            log_message('error', '[Appointments::store] Failed to insert: ' . json_encode($errors));
            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to create appointment. Please try again.');
        }        // Success - redirect to appointments calendar with refresh parameter
        // The timestamp parameter forces the calendar to bypass cache and reload
        return redirect()->to('/appointments?refresh=' . time())
            ->with('success', 'Appointment booked successfully! The appointment is now visible in your calendar.');
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
    public function edit($appointmentHash = null)
    {
        // Check authentication and permissions
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        if (!has_role(['staff', 'provider', 'admin'])) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Access denied');
        }

        if (!$appointmentHash) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Appointment not found');
        }

        // Load appointment by hash with related data
        $appointment = $this->appointmentModel
            ->select('xs_appointments.*, 
                     c.first_name as customer_first_name,
                     c.last_name as customer_last_name,
                     c.email as customer_email,
                     c.phone as customer_phone,
                     c.address as customer_address,
                     c.notes as customer_notes,
                     c.custom_fields as customer_custom_fields,
                     c.hash as customer_hash')
            ->join('xs_customers c', 'c.id = xs_appointments.customer_id', 'left')
            ->where('xs_appointments.hash', $appointmentHash)
            ->first();

        if (!$appointment) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Appointment not found');
        }

        // Get booking configuration and providers
        $config = $this->getBookingConfiguration();
        $providersFormatted = $this->getFormattedProviders();

        // Parse custom fields from JSON
        if (!empty($appointment['customer_custom_fields'])) {
            $customFieldsData = json_decode($appointment['customer_custom_fields'], true);
            foreach ($customFieldsData as $key => $value) {
                $appointment[$key] = $value;
            }
        }

        // Database stores times in LOCAL timezone (not UTC)
        // Simply extract date and time for form display
        if (!empty($appointment['start_time'])) {
            $startDateTime = new \DateTime($appointment['start_time']);
            $appointment['date'] = $startDateTime->format('Y-m-d');
            $appointment['time'] = $startDateTime->format('H:i');
        }

        $data = [
            'title' => 'Edit Appointment',
            'current_page' => 'appointments',
            'appointment' => $appointment,
            'providers' => $providersFormatted,
            'user_role' => current_user_role(),
            'formAction' => base_url('appointments/update/' . $appointmentHash),
        ] + $config;

        return view('appointments/form', $data);
    }

    /**
     * Update existing appointment (full form submission)
     * PUT /appointments/update/:hash
     * 
     * PURPOSE: Handle full appointment edits from edit.php form.
     * Updates ALL appointment fields including customer information.
     * This is the comprehensive edit flow, unlike the quick API status updates.
     * 
     * Flow: Edit form → This method → Validate all fields → Update customer + appointment
     * 
     * @param string|null $appointmentHash Appointment hash for security
     * @return RedirectResponse
     */
    public function update($appointmentHash = null)
    {
        // Check authentication and permissions
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login')->with('error', 'Please log in to continue');
        }

        if (!has_role(['staff', 'provider', 'admin'])) {
            return redirect()->back()->with('error', 'Access denied');
        }

        if (!$appointmentHash) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Appointment not found');
        }

        // Load existing appointment by hash
        $existingAppointment = $this->appointmentModel->findByHash($appointmentHash);
        if (!$existingAppointment) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Appointment not found');
        }

        $appointmentId = $existingAppointment['id'];

        $validation = \Config\Services::validation();
        
        // Validation rules (status values must match API)
        // Phone formats supported: +27 82 529 7070 (international) or 082 529 2242 (local)
        $rules = [
            'provider_id' => 'required|is_natural_no_zero',
            'service_id' => 'required|is_natural_no_zero',
            'appointment_date' => 'required|valid_date',
            'appointment_time' => 'required',
            'status' => 'required|in_list[pending,confirmed,completed,cancelled,no-show]',
            'customer_first_name' => 'required|min_length[2]|max_length[120]',
            'customer_last_name' => 'permit_empty|max_length[160]',
            'customer_email' => 'required|valid_email|max_length[255]',
            'customer_phone' => 'required|min_length[10]|max_length[20]',
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
        
        // Create DateTime WITHOUT timezone to keep values as-is (already in local time)
        // The form sends times in the user's local timezone, so we store them as-is
        $startDateTime = new \DateTime($startTimeLocal);

        // Calculate end time based on service duration
        $endDateTime = clone $startDateTime;
        $endDateTime->modify('+' . (int) $service['duration_min'] . ' minutes');

        // Format times for storage (database stores in LOCAL timezone as-is)
        $startTimeForDb = $startDateTime->format('Y-m-d H:i:s');
        $endTimeForDb = $endDateTime->format('Y-m-d H:i:s');

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
        $customFieldsJson = $this->extractCustomFields();
        if ($customFieldsJson) {
            $customerData['custom_fields'] = $customFieldsJson;
        }

        $this->customerModel->update($customerId, $customerData);

        // Update appointment
        $appointmentData = [
            'provider_id' => $providerId,
            'service_id' => $serviceId,
            'start_time' => $startTimeForDb,
            'end_time' => $endTimeForDb,
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

}
