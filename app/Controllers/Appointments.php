<?php

/**
 * =============================================================================
 * APPOINTMENTS CONTROLLER
 * =============================================================================
 * 
 * @file        app/Controllers/Appointments.php
 * @description Handles all appointment management operations for authenticated
 *              users including listing, creating, editing, viewing, and
 *              status management of appointments.
 * 
 * ROUTES HANDLED:
 * -----------------------------------------------------------------------------
 * GET  /appointments              : List all appointments (filtered by role)
 * GET  /appointments/create       : Show appointment creation form
 * POST /appointments/store        : Create new appointment
 * GET  /appointments/edit/:hash   : Show edit form for appointment
 * POST /appointments/update/:hash : Update existing appointment
 * GET  /appointments/view/:hash   : View appointment details (redirects to calendar)
 * POST /appointments/delete/:hash : Soft delete appointment
 * 
 * PURPOSE:
 * -----------------------------------------------------------------------------
 * Provides the primary interface for appointment management:
 * - CRUD operations for appointments
 * - Hash-based URLs for security (non-enumerable IDs)
 * - Role-based filtering (admins see all, providers see own)
 * - Integration with calendar/scheduler views
 * 
 * SECURITY:
 * -----------------------------------------------------------------------------
 * - Uses hash identifiers instead of numeric IDs in URLs
 * - Role-based access control for all operations
 * - CSRF protection on all POST requests
 * - Provider can only access their own appointments
 * 
 * DEPENDENCIES:
 * -----------------------------------------------------------------------------
 * - AppointmentModel       : Database operations for appointments
 * - CustomerModel          : Customer lookup and management
 * - UserModel              : Provider information
 * - ServiceModel           : Service details and duration
 * - BookingSettingsService : Booking rules and validation
 * - LocalizationSettingsService : Date/time formatting
 * - TimezoneService        : Timezone conversion
 * 
 * @see         app/Views/appointments/ for view templates
 * @see         app/Controllers/Api/Appointments.php for API endpoints
 * @package     App\Controllers
 * @extends     BaseController
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

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
            return redirect()->to(base_url('auth/login'));
        }

        $currentUser = session()->get('user');
        $currentUserId = session()->get('user_id');
        $currentRole = current_user_role();

        // Build context for role-based filtering
        $context = [];
        if ($currentRole === 'provider') {
            $context['provider_id'] = $currentUserId;
        } elseif ($currentRole === 'staff') {
            // Staff sees appointments for providers they're assigned to
            $providerStaffModel = new \App\Models\ProviderStaffModel();
            $assignedProviders = $providerStaffModel->getProvidersForStaff($currentUserId);
            if (!empty($assignedProviders)) {
                $context['provider_ids'] = array_column($assignedProviders, 'provider_id');
            }
        }
        // Admin sees all appointments (no context filter)
        
        // Get real appointments from database
        $appointmentModel = new \App\Models\AppointmentModel();
        $appointments = $appointmentModel->getDashboardAppointments(null, $context, 100);
        
        // Get active providers with colors for legend
        $activeProviders = $this->userModel
            ->where('role', 'provider')
            ->where('is_active', true)
            ->where('color IS NOT NULL')
            ->where('color !=', '')
            ->orderBy('name', 'ASC')
            ->findAll();
        
        // Get ALL providers for filter dropdown (including those without colors)
        $allProviders = $this->userModel
            ->where('role', 'provider')
            ->where('is_active', true)
            ->orderBy('name', 'ASC')
            ->findAll();
        
        // Get ALL services for filter dropdown
        // Note: xs_services table uses 'active' column, not 'is_active'
        $allServices = $this->serviceModel
            ->where('active', 1)
            ->orderBy('name', 'ASC')
            ->findAll();
        
        // Get current filter values from request
        $currentFilters = [
            'status' => $this->request->getGet('status') ?? '',
            'provider_id' => $this->request->getGet('provider_id') ?? '',
            'service_id' => $this->request->getGet('service_id') ?? '',
        ];
        
        // Get real stats from database
        $stats = $appointmentModel->getStats($context);
        
        $data = [
            'title' => $currentRole === 'customer' ? 'My Appointments' : 'Appointments',
            'current_page' => 'appointments',
            'appointments' => $appointments,
            'user_role' => $currentRole,
            'user' => $currentUser,
            'stats' => $stats,
            'activeProviders' => $activeProviders,
            'allProviders' => $allProviders,
            'allServices' => $allServices,
            'currentFilters' => $currentFilters
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
            return redirect()->to(base_url('auth/login'));
        }

        if (!$appointmentId) {
            return redirect()->to(base_url('appointments'))->with('error', 'Appointment not found.');
        }

        // Redirect to appointments page with query parameter to open modal
        // The JavaScript will detect this and open the appointment modal
        return redirect()->to(base_url('appointments?open=' . $appointmentId));
    }

    /**
     * Create new appointment (customers and staff)
     */
    public function create()
    {
        // Check authentication and permissions
        if (!session()->get('isLoggedIn')) {
            return redirect()->to(base_url('auth/login'));
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
            return redirect()->to(base_url('auth/login'))->with('error', 'Please log in to book an appointment');
        }

        if (!has_role(['customer', 'staff', 'provider', 'admin'])) {
            log_message('error', '[Appointments::store] Access denied - role check failed');
            return redirect()->back()->with('error', 'Access denied');
        }

        // Check if user is trying to book in the past
        $appointmentDate = $this->request->getPost('appointment_date');
        $appointmentTime = $this->request->getPost('appointment_time');
        
        if ($appointmentDate && $appointmentTime) {
            $clientTimezone = $this->resolveClientTimezone();
            $appointmentDateTime = new \DateTime($appointmentDate . ' ' . $appointmentTime, new \DateTimeZone($clientTimezone));
            $now = new \DateTime('now', new \DateTimeZone($clientTimezone));
            
            if ($appointmentDateTime < $now) {
                $errorMsg = 'Cannot book appointments in the past. Please select a future date and time.';
                log_message('error', '[Appointments::store] Attempted to book in the past: ' . $appointmentDate . ' ' . $appointmentTime);
                if ($this->request->isAJAX()) {
                    return $this->response->setStatusCode(422)->setJSON([
                        'success' => false,
                        'message' => $errorMsg
                    ]);
                }
                return redirect()->back()
                    ->withInput()
                    ->with('error', $errorMsg);
            }
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
        }

        if (!$this->validate($rules)) {
            log_message('error', '[Appointments::store] Validation failed: ' . json_encode($validation->getErrors()));
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(422)->setJSON([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validation->getErrors()
                ]);
            }
            return redirect()->back()
                ->withInput()
                ->with('errors', $validation->getErrors());
        }

        log_message('info', '[Appointments::store] Validation passed');

        // Prepare booking data from form
        $bookingData = [
            'provider_id' => $this->request->getPost('provider_id'),
            'service_id' => $this->request->getPost('service_id'),
            'appointment_date' => $this->request->getPost('appointment_date'),
            'appointment_time' => $this->request->getPost('appointment_time'),
            'customer_id' => $customerId,
            'customer_first_name' => $this->request->getPost('customer_first_name'),
            'customer_last_name' => $this->request->getPost('customer_last_name'),
            'customer_email' => $this->request->getPost('customer_email'),
            'customer_phone' => $this->request->getPost('customer_phone'),
            'customer_address' => $this->request->getPost('customer_address'),
            'customer_notes' => $this->request->getPost('notes'),
            'notes' => $this->request->getPost('notes'),
            'notification_types' => ['email', 'whatsapp']
        ];

        // Add custom fields if provided
        for ($i = 1; $i <= 6; $i++) {
            $fieldValue = $this->request->getPost("custom_field_{$i}");
            if ($fieldValue !== null && $fieldValue !== '') {
                $bookingData["custom_field_{$i}"] = $fieldValue;
            }
        }

        // Use AppointmentBookingService for all booking logic
        $bookingService = new \App\Services\AppointmentBookingService();
        $clientTimezone = $this->resolveClientTimezone();
        $result = $bookingService->createAppointment($bookingData, $clientTimezone);

        // Handle result
        if (!$result['success']) {
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(400)->setJSON([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => $result['errors'] ?? []
                ]);
            }
            return redirect()->back()
                ->withInput()
                ->with('error', $result['message']);
        }

        // Success - redirect to appointments list or view
        if ($this->request->isAJAX()) {
            return $this->response->setJSON([
                'success' => true,
                'message' => $result['message'],
                'redirect' => '/appointments',
                'appointmentId' => $result['appointmentId']
            ]);
        }
        return redirect()->to(base_url('appointments'))
            ->with('success', $result['message']);
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
            return redirect()->to(base_url('auth/login'));
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

        // Check if appointment is in the past - only allow status changes for past appointments
        $appointmentTime = new \DateTime($appointment['start_time'], new \DateTimeZone('UTC'));
        $now = new \DateTime('now', new \DateTimeZone('UTC'));
        $isPastAppointment = $appointmentTime < $now;

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
            'isPastAppointment' => $isPastAppointment,
        ];

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
            return redirect()->to(base_url('auth/login'))->with('error', 'Please log in to continue');
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

        // Get the new appointment date/time from form
        $newAppointmentDate = $this->request->getPost('appointment_date');
        $newAppointmentTime = $this->request->getPost('appointment_time');
        
        // Check if user is trying to schedule in the past
        if ($newAppointmentDate && $newAppointmentTime) {
            $clientTimezone = $this->resolveClientTimezone();
            $newDateTime = new \DateTime($newAppointmentDate . ' ' . $newAppointmentTime, new \DateTimeZone($clientTimezone));
            $now = new \DateTime('now', new \DateTimeZone($clientTimezone));
            
            if ($newDateTime < $now) {
                $errorMsg = 'Cannot schedule appointments in the past. Please select a future date and time.';
                if ($this->request->isAJAX()) {
                    return $this->response->setStatusCode(422)->setJSON([
                        'success' => false,
                        'message' => $errorMsg
                    ]);
                }
                return redirect()->back()
                    ->withInput()
                    ->with('error', $errorMsg);
            }
        }

        $validation = \Config\Services::validation();
        
        // Validation rules (status values must match API)
        $rules = [
            'provider_id' => 'required|is_natural_no_zero',
            'service_id' => 'required|is_natural_no_zero',
            'appointment_date' => 'required|valid_date',
            'appointment_time' => 'required',
            'status' => 'required|in_list[pending,confirmed,completed,cancelled,no-show]',
            'customer_first_name' => 'required|min_length[2]|max_length[120]',
            'customer_last_name' => 'permit_empty|max_length[160]',
            'customer_email' => 'required|valid_email|max_length[255]',
            'customer_phone' => 'required|min_length[10]|max_length[32]',
            'customer_address' => 'permit_empty|max_length[255]',
            'notes' => 'permit_empty|max_length[1000]'
        ];

        if (!$this->validate($rules)) {
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(422)->setJSON([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validation->getErrors()
                ]);
            }
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
        
        // Debug: Log status value
        log_message('info', '[Appointments::update] Status from form: ' . $status);
        log_message('info', '[Appointments::update] Current appointment status: ' . $existingAppointment['status']);
        
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
            $startDateTime = new \DateTime($startLocal, new \DateTimeZone('UTC'));
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
                // Use consistent field naming: 'custom_field_1' not 'field_1'
                // This matches CustomerManagement controller and BookingSettingsService
                $customFieldsData["custom_field_{$i}"] = $fieldValue;
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
        
        log_message('info', '[Appointments::update] Appointment data to save: ' . json_encode($appointmentData));

        $updated = $this->appointmentModel->update($appointmentId, $appointmentData);

        // If the appointment time changed, reset reminder_sent so reminders can re-send.
        try {
            (new \App\Services\AppointmentNotificationService())
                ->resetReminderSentIfTimeChanged(
                    (int) $appointmentId,
                    (string) ($existingAppointment['start_time'] ?? ''),
                    (string) ($startTimeUtc ?? '')
                );
        } catch (\Throwable $e) {
            log_message('error', '[Appointments::update] Failed resetting reminder flag: {msg}', ['msg' => $e->getMessage()]);
        }

        if (!$updated) {
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(400)->setJSON([
                    'success' => false,
                    'message' => 'Failed to update appointment. Please try again.'
                ]);
            }
            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to update appointment. Please try again.');
        }

        log_message('info', '[Appointments::update] Successfully updated appointment #' . $appointmentId);

        // Success
        if ($this->request->isAJAX()) {
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Appointment updated successfully!',
                'redirect' => '/appointments'
            ]);
        }
        return redirect()->to(base_url('appointments'))
            ->with('success', 'Appointment updated successfully!');
    }
}
