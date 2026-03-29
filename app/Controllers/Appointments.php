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
 * @author      Nilesh Nagin Cara
 * @copyright   2024-2026 Nilesh Nagin Cara
 * =============================================================================
 */

namespace App\Controllers;

use App\Models\LocationModel;
use App\Models\UserModel;
use App\Models\ServiceModel;
use App\Models\AppointmentModel;
use App\Models\CustomerModel;
use App\Services\Appointment\AppointmentDateTimeNormalizer;
use App\Services\Appointment\AppointmentFormContextService;
use App\Services\Appointment\AppointmentFormGuardService;
use App\Services\Appointment\AppointmentFormMutationService;
use App\Services\Appointment\AppointmentFormResponseService;
use App\Services\LocalizationSettingsService;
use App\Services\TimezoneService;

class Appointments extends BaseController
{
    protected $userModel;
    protected $serviceModel;
    protected $appointmentModel;
    protected $customerModel;
    protected LocationModel $locationModel;
    protected AppointmentDateTimeNormalizer $appointmentDateTimeNormalizer;
    protected LocalizationSettingsService $localizationSettingsService;
    protected AppointmentFormContextService $appointmentFormContextService;
    protected AppointmentFormGuardService $appointmentFormGuardService;
    protected AppointmentFormMutationService $appointmentFormMutationService;
    protected AppointmentFormResponseService $appointmentFormResponseService;

    public function __construct(
        ?UserModel $userModel = null,
        ?ServiceModel $serviceModel = null,
        ?AppointmentModel $appointmentModel = null,
        ?CustomerModel $customerModel = null,
        ?LocationModel $locationModel = null,
        ?AppointmentDateTimeNormalizer $appointmentDateTimeNormalizer = null,
        ?LocalizationSettingsService $localizationSettingsService = null,
        ?AppointmentFormContextService $appointmentFormContextService = null,
        ?AppointmentFormGuardService $appointmentFormGuardService = null,
        ?AppointmentFormMutationService $appointmentFormMutationService = null,
        ?AppointmentFormResponseService $appointmentFormResponseService = null,
    )
    {
        $this->userModel = $userModel ?? new UserModel();
        $this->serviceModel = $serviceModel ?? new ServiceModel();
        $this->appointmentModel = $appointmentModel ?? new AppointmentModel();
        $this->customerModel = $customerModel ?? new CustomerModel();
        $this->locationModel = $locationModel ?? new LocationModel();
        $this->appointmentDateTimeNormalizer = $appointmentDateTimeNormalizer ?? new AppointmentDateTimeNormalizer();
        $this->localizationSettingsService = $localizationSettingsService ?? new LocalizationSettingsService();
        $this->appointmentFormContextService = $appointmentFormContextService ?? new AppointmentFormContextService();
        $this->appointmentFormGuardService = $appointmentFormGuardService ?? new AppointmentFormGuardService();
        $this->appointmentFormMutationService = $appointmentFormMutationService ?? new AppointmentFormMutationService();
        $this->appointmentFormResponseService = $appointmentFormResponseService ?? new AppointmentFormResponseService();
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

        $db = \Config\Database::connect();
        $usersHasIsActive = method_exists($db, 'fieldExists') ? $db->fieldExists('is_active', 'xs_users') : true;
        $usersHasStatus = method_exists($db, 'fieldExists') ? $db->fieldExists('status', 'xs_users') : true;
        
        // Get real appointments from database
        $appointments = $this->appointmentModel->getDashboardAppointments(null, $context, 100);
        
        // Get active providers with colors for legend
        $activeProvidersBuilder = $this->userModel
            ->where('role', 'provider')
            ->where('color IS NOT NULL')
            ->where('color !=', '')
            ->orderBy('name', 'ASC');

        if ($usersHasIsActive) {
            $activeProvidersBuilder->where('is_active', true);
        } elseif ($usersHasStatus) {
            $activeProvidersBuilder->where('status', 'active');
        }

        $activeProviders = $activeProvidersBuilder->findAll();
        
        // Get ALL providers for filter dropdown (including those without colors)
        $allProvidersBuilder = $this->userModel
            ->where('role', 'provider')
            ->orderBy('name', 'ASC');

        if ($usersHasIsActive) {
            $allProvidersBuilder->where('is_active', true);
        } elseif ($usersHasStatus) {
            $allProvidersBuilder->where('status', 'active');
        }

        $allProviders = $allProvidersBuilder->findAll();
        
        // Get ALL services for filter dropdown
        // Note: xs_services table uses 'active' column, not 'is_active'
        $allServices = $this->serviceModel
            ->where('active', 1)
            ->orderBy('name', 'ASC')
            ->findAll();
        
        // Get ALL active locations for filter dropdown
        $allLocations = $this->locationModel
            ->where('is_active', 1)
            ->orderBy('name', 'ASC')
            ->findAll();
        
        // Get current filter values from request
        $currentFilters = [
            'status' => $this->request->getGet('status') ?? '',
            'provider_id' => $this->request->getGet('provider_id') ?? '',
            'service_id' => $this->request->getGet('service_id') ?? '',
            'location_id' => $this->request->getGet('location_id') ?? '',
        ];
        
        // Get real stats from database
        $stats = $this->appointmentModel->getStats($context);
        
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
            'allLocations' => $allLocations,
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
        $guard = $this->appointmentFormGuardService->requireLogin();
        if ($guard !== null) {
            return $this->appointmentFormGuardService->toResponse($guard, $this->response);
        }

        $guard = $this->appointmentFormGuardService->requireRole(['customer', 'staff', 'provider', 'admin'], true);
        if ($guard !== null) {
            return $this->appointmentFormGuardService->toResponse($guard, $this->response);
        }

        return view('appointments/form', $this->appointmentFormContextService->buildCreateViewData((string) current_user_role()));
    }

    /**
     * Store new appointment
     */
    public function store()
    {
        log_message('info', '[Appointments::store] ========== STORE METHOD CALLED ==========');
        log_message('info', '[Appointments::store] POST data: ' . json_encode($this->request->getPost()));
        
        $guard = $this->appointmentFormGuardService->requireLogin('Please log in to book an appointment');
        if ($guard !== null) {
            log_message('error', '[Appointments::store] Not logged in');
            return $this->appointmentFormGuardService->toResponse($guard, $this->response);
        }

        $guard = $this->appointmentFormGuardService->requireRole(['customer', 'staff', 'provider', 'admin']);
        if ($guard !== null) {
            log_message('error', '[Appointments::store] Access denied - role check failed');
            return $this->appointmentFormGuardService->toResponse($guard, $this->response);
        }

        $guard = $this->appointmentFormGuardService->validateNotInPast(
            $this->request->getPost('appointment_date'),
            $this->request->getPost('appointment_time'),
            'Cannot book appointments in the past. Please select a future date and time.',
            $this->request->isAJAX(),
        );
        if ($guard !== null) {
            return $this->appointmentFormGuardService->toResponse($guard, $this->response);
        }

        $clientTimezone = $this->resolveClientTimezone();
        $result = $this->appointmentFormMutationService->createFromFormPayload($this->request->getPost(), $clientTimezone);

        return $this->appointmentFormResponseService->fromMutationResult($result, $this->request->isAJAX(), $this->response);
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
            $timezoneCandidate = $this->localizationSettingsService->getTimezone();
        }

        $offsetCandidate = $headerOffset !== '' ? $headerOffset : $postOffset;
        if ($offsetCandidate !== '' && is_numeric($offsetCandidate) && $session) {
            $session->set('client_timezone_offset', (int) $offsetCandidate);
        }

        return $this->appointmentDateTimeNormalizer->resolveInputTimezone($timezoneCandidate);
    }

    /**
     * Edit existing appointment (staff, provider, admin only)
     */
    public function edit($appointmentHash = null)
    {
        $guard = $this->appointmentFormGuardService->requireLogin();
        if ($guard !== null) {
            return $this->appointmentFormGuardService->toResponse($guard, $this->response);
        }

        $guard = $this->appointmentFormGuardService->requireRole(['staff', 'provider', 'admin'], true);
        if ($guard !== null) {
            return $this->appointmentFormGuardService->toResponse($guard, $this->response);
        }

        $guard = $this->appointmentFormGuardService->requireAppointmentHash($appointmentHash);
        if ($guard !== null) {
            return $this->appointmentFormGuardService->toResponse($guard, $this->response);
        }

        return view('appointments/form', $this->appointmentFormContextService->buildEditViewData($appointmentHash, (string) current_user_role()));
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
        $guard = $this->appointmentFormGuardService->requireLogin('Please log in to continue');
        if ($guard !== null) {
            return $this->appointmentFormGuardService->toResponse($guard, $this->response);
        }

        $guard = $this->appointmentFormGuardService->requireRole(['staff', 'provider', 'admin']);
        if ($guard !== null) {
            return $this->appointmentFormGuardService->toResponse($guard, $this->response);
        }

        $guard = $this->appointmentFormGuardService->requireAppointmentHash($appointmentHash);
        if ($guard !== null) {
            return $this->appointmentFormGuardService->toResponse($guard, $this->response);
        }

        $guard = $this->appointmentFormGuardService->requireExistingAppointment($appointmentHash);
        if ($guard !== null) {
            return $this->appointmentFormGuardService->toResponse($guard, $this->response);
        }

        // Get the new appointment date/time from form
        $newAppointmentDate = $this->request->getPost('appointment_date');
        $newAppointmentTime = $this->request->getPost('appointment_time');
        
        $guard = $this->appointmentFormGuardService->validateNotInPast(
            $newAppointmentDate,
            $newAppointmentTime,
            'Cannot schedule appointments in the past. Please select a future date and time.',
            $this->request->isAJAX(),
        );
        if ($guard !== null) {
            return $this->appointmentFormGuardService->toResponse($guard, $this->response);
        }

        $clientTimezone = $this->resolveClientTimezone();
        $result = $this->appointmentFormMutationService->updateFromFormPayload($appointmentHash, $this->request->getPost(), $clientTimezone);
        if (!empty($result['notFound'])) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Appointment not found');
        }

        return $this->appointmentFormResponseService->fromMutationResult($result, $this->request->isAJAX(), $this->response);
    }
}
