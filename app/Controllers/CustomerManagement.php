<?php

/**
 * =============================================================================
 * CUSTOMER MANAGEMENT CONTROLLER
 * =============================================================================
 * 
 * @file        app/Controllers/CustomerManagement.php
 * @description Handles customer record management including listing, creating,
 *              editing, viewing, and deleting customer profiles.
 * 
 * ROUTES HANDLED:
 * -----------------------------------------------------------------------------
 * GET  /customer-management                : List customers with search
 * GET  /customer-management/create         : Show customer creation form
 * POST /customer-management/store          : Create new customer record
 * GET  /customer-management/edit/:hash     : Show edit form for customer
 * POST /customer-management/update/:hash   : Update existing customer
 * GET  /customer-management/history/:hash  : View customer profile with history
 * POST /customer-management/delete/:hash   : Hard delete customer without appointments
 * GET  /customer-management/search         : AJAX search endpoint
 * 
 * PURPOSE:
 * -----------------------------------------------------------------------------
 * Manages the customer database:
 * - Customer profile CRUD operations
 * - Contact information management (email, phone, address)
 * - Appointment history viewing
 * - Customer search and filtering
 * - Hash-based URLs for privacy
 * 
 * CUSTOMER DATA:
 * -----------------------------------------------------------------------------
 * - Personal: First name, last name, email, phone
 * - Address: Street, city, state, postal code, country
 * - Communication: Preferred contact method, opt-out flags
 * - History: Appointment count, last visit, total spend
 * 
 * SECURITY:
 * -----------------------------------------------------------------------------
 * - Hash identifiers for non-enumerable URLs
 * - Role-based access (staff+ can view/edit scoped customers, admin can delete)
 * - CSRF protection on forms
 * - Input validation and sanitization
 * 
 * DEPENDENCIES:
 * -----------------------------------------------------------------------------
 * - CustomerModel              : Database operations
 * - BookingSettingsService     : Field configuration
 * - CustomerAppointmentService : History and statistics
 * 
 * @see         app/Views/customer-management/ for view templates
 * @see         app/Models/CustomerModel.php for data model
 * @package     App\Controllers
 * @extends     BaseController
 * @author      Nilesh Nagin Cara
 * @copyright   2024-2026 Nilesh Nagin Cara
 * =============================================================================
 */

namespace App\Controllers;

use App\Models\CustomerModel;
use App\Services\BookingSettingsService;
use App\Services\CustomerDeletionService;
use App\Services\CustomerAppointmentService;
use App\Services\PhoneNumberService;

class CustomerManagement extends BaseController
{
    protected CustomerModel $customers;
    protected BookingSettingsService $bookingSettings;
    protected CustomerAppointmentService $appointmentService;
    protected CustomerDeletionService $customerDeletionService;
    protected PhoneNumberService $phoneNumberService;

    public function __construct(
        ?CustomerModel $customers = null,
        ?BookingSettingsService $bookingSettings = null,
        ?CustomerAppointmentService $appointmentService = null,
        ?CustomerDeletionService $customerDeletionService = null,
        ?PhoneNumberService $phoneNumberService = null,
    )
    {
        $this->customers = $customers ?? new CustomerModel();
        $this->bookingSettings = $bookingSettings ?? new BookingSettingsService();
        $this->appointmentService = $appointmentService ?? new CustomerAppointmentService();
        $this->customerDeletionService = $customerDeletionService ?? new CustomerDeletionService($this->customers);
        $this->phoneNumberService = $phoneNumberService ?? new PhoneNumberService();
    }

    /**
     * List customers
     */
    public function index()
    {
        $currentUserId = (int) (session()->get('user_id') ?? 0);
        if (!$currentUserId) {
            return redirect()->to(base_url('auth/login'));
        }

        $currentRole = $this->resolveScopedRole();
        $q = trim((string) $this->request->getGet('q'));

        // Staff/provider see only customers relevant to them.
        $staffCustomerIds = null;
        if ($currentRole === 'staff') {
            $staffCustomerIds = $this->appointmentService->resolveCustomerIdsForStaff($currentUserId);
        } elseif ($currentRole === 'provider') {
            $staffCustomerIds = $this->appointmentService->resolveCustomerIdsForProvider($currentUserId);
        }

        if ($q !== '') {
            $customers = $this->customers->search(['q' => $q, 'limit' => 200, 'customer_ids' => $staffCustomerIds]);
        } else {
            $builder = $this->customers->orderBy('created_at', 'DESC');
            if ($staffCustomerIds !== null) {
                if (empty($staffCustomerIds)) {
                    $builder->where('id', 0); // No results — unassigned staff.
                } else {
                    $builder->whereIn('id', $staffCustomerIds);
                }
            }
            $customers = $builder->findAll(200);
        }

        // Total count respects the same scope.
        $countBuilder = $this->customers->builder();
        if ($staffCustomerIds !== null) {
            if (empty($staffCustomerIds)) {
                $countBuilder->where('id', 0);
            } else {
                $countBuilder->whereIn('id', $staffCustomerIds);
            }
        }
        $totalCustomers = (int) $countBuilder->countAllResults();

        $data = [
            'title' => 'Customer Management - WebScheduler',
            'customers' => $customers,
            'currentUser' => session()->get('user'),
            'canDeleteCustomers' => $this->hasRole('admin'),
            'q' => $q,
            'totalCustomers' => $totalCustomers,
        ];

        return view('customer-management/index', $data);
    }

    /**
     * Check whether the current user may access the given customer record.
     * Admin has unrestricted access; provider/staff are limited to scoped customer IDs.
     */
    private function isCustomerAccessible(string $role, int $userId, int $customerId): bool
    {
        if ($role === 'admin') {
            return true;
        }
        if ($role === 'provider') {
            $allowed = $this->appointmentService->resolveCustomerIdsForProvider($userId);
        } elseif ($role === 'staff') {
            $allowed = $this->appointmentService->resolveCustomerIdsForStaff($userId);
        } else {
            return false;
        }
        return in_array($customerId, $allowed, true);
    }

    /**
     * Show create form
     */
    public function create()
    {
        if (!session()->get('user_id')) {
            return redirect()->to(base_url('auth/login'));
        }
        
        $fieldConfig = $this->bookingSettings->getFieldConfiguration();
        $customFields = $this->bookingSettings->getCustomFieldConfiguration();
        
        $data = [
            'title' => 'Create Customer - WebScheduler',
            'validation' => $this->validator,
            'fieldConfig' => $fieldConfig,
            'customFields' => $customFields,
        ];
        return view('customer-management/create', $data);
    }

    /**
     * Persist a new customer
     */
    public function store()
    {
        if (!session()->get('user_id')) {
            return redirect()->to(base_url('auth/login'));
        }

        // Use dynamic validation rules from booking settings
        $rules = $this->bookingSettings->getValidationRules();

        if (!$this->validate($rules)) {
            // Return JSON for SPA or HTML for traditional form
            if ($this->request->isAJAX()) {
                return response()->setJSON([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $this->validator->getErrors()
                ]);
            }
            return redirect()->back()->withInput()->with('validation', $this->validator);
        }

        $payload = [];
        $fieldConfig = $this->bookingSettings->getFieldConfiguration();
        $customFields = $this->bookingSettings->getCustomFieldConfiguration();
        
        // Only include fields that are displayed in settings
        foreach ($fieldConfig as $fieldName => $config) {
            if ($config['display']) {
                $payload[$fieldName] = trim((string) $this->request->getPost($fieldName));
            }
        }

        if (array_key_exists('phone', $payload)) {
            $payload['phone'] = $this->phoneNumberService->normalize(
                $payload['phone'],
                $this->request->getPost('phone_country_code')
            ) ?? '';
        }

        $customFieldPayload = [];
        if (!empty($customFields)) {
            log_message('info', '[CustomerManagement::store] Processing ' . count($customFields) . ' enabled custom fields');
            
            foreach ($customFields as $fieldName => $config) {
                $raw = $this->request->getPost($fieldName);
                if ($config['type'] === 'checkbox') {
                    $value = $raw ? '1' : '0';
                } else {
                    $value = trim((string) ($raw ?? ''));
                }

                // Always save ALL enabled fields, even if empty
                // This ensures consistent data structure and allows newly enabled fields to be saved
                $customFieldPayload[$fieldName] = $value;
                log_message('info', "[CustomerManagement::store] Field '{$fieldName}' = '{$value}' (type: {$config['type']})");
            }

            // Save custom fields JSON (even if all values are empty)
            // This ensures the database has a consistent structure for all enabled fields
            $payload['custom_fields'] = json_encode($customFieldPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            log_message('info', '[CustomerManagement::store] Custom fields JSON for new customer: ' . $payload['custom_fields']);
        } else {
            log_message('info', '[CustomerManagement::store] No custom fields enabled in settings');
        }

        // Support combined name field fallback (if first_name/last_name are not displayed)
        $name = trim((string) $this->request->getPost('name'));
        if ($name && !isset($payload['first_name']) && !isset($payload['last_name'])) {
            $parts = preg_split('/\s+/', $name, 2);
            $payload['first_name'] = $parts[0] ?? '';
            $payload['last_name']  = $parts[1] ?? '';
        }

        $now = date('Y-m-d H:i:s');
        $payload['created_at'] = $now;
        $payload['updated_at'] = $now;

        // Skip model validation since we already validated with the service
        // This prevents conflicts with model-level rules
        $id = $this->customers->insert($payload, false);
        if ($id) {
            log_message('info', '[CustomerManagement] Successfully created customer ID: ' . $id);
            
            // Return JSON for SPA or HTML for traditional form
            if ($this->request->isAJAX()) {
                return response()->setJSON([
                    'success' => true,
                    'message' => 'Customer created successfully.',
                    'redirect' => base_url('customer-management')
                ]);
            }
            return redirect()->to(base_url('customer-management'))->with('success', 'Customer created successfully.');
        }
        
        // Log model validation errors if any
        $modelErrors = $this->customers->errors();
        if (!empty($modelErrors)) {
            log_message('error', '[CustomerManagement] Model validation errors: ' . json_encode($modelErrors));
        }
        
        log_message('error', '[CustomerManagement] Failed to create customer');
        
        // Return JSON for SPA or HTML for traditional form
        if ($this->request->isAJAX()) {
            return response()->setJSON([
                'success' => false,
                'message' => 'Failed to create customer.'
            ]);
        }
        return redirect()->back()->withInput()->with('error', 'Failed to create customer.');
    }

    /**
     * Edit form
     */
    public function edit(string $identifier)
    {
        $currentUserId = (int) (session()->get('user_id') ?? 0);
        if (!$currentUserId) {
            return redirect()->to(base_url('auth/login'));
        }
        $currentRole = $this->resolveScopedRole();
        $customer = $this->customers->findByIdentifier($identifier);
        if (!$customer) {
            return redirect()->to(base_url('customer-management'))->with('error', 'Customer not found.');
        }
        if (!$this->isCustomerAccessible($currentRole, $currentUserId, (int) $customer['id'])) {
            return redirect()->to(base_url('customer-management'))->with('error', 'Access denied.');
        }
        
        $fieldConfig = $this->bookingSettings->getFieldConfiguration();
        $customFields = $this->bookingSettings->getCustomFieldConfiguration();
        $customFieldValues = [];
        if (!empty($customer['custom_fields'])) {
            $decoded = json_decode((string) $customer['custom_fields'], true);
            if (is_array($decoded)) {
                $customFieldValues = $decoded;
            }
        }
        
        $data = [
            'title' => 'Edit Customer - WebScheduler',
            'customer' => $customer,
            'customerIdentifier' => (string) ($customer['hash'] ?? $customer['id'] ?? ''),
            'canDeleteCustomers' => $this->hasRole('admin'),
            'appointmentCount' => $this->customerDeletionService->countAppointmentsForCustomer((int) $customer['id']),
            'validation' => $this->validator,
            'fieldConfig' => $fieldConfig,
            'customFields' => $customFields,
            'customFieldValues' => $customFieldValues,
        ];
        return view('customer-management/edit', $data);
    }

    /**
     * Update customer
     */
    public function update(string $identifier)
    {
        if (!session()->get('user_id')) {
            return redirect()->to(base_url('auth/login'));
        }
        $customer = $this->customers->findByIdentifier($identifier);
        if (!$customer) {
            $errorMsg = 'Customer not found.';
            if ($this->request->isAJAX()) {
                return response()->setJSON(['success' => false, 'message' => $errorMsg]);
            }
            return redirect()->to(base_url('customer-management'))->with('error', $errorMsg);
        }

        $id = $customer['id'];

        // Use dynamic validation rules from booking settings
        $rules = $this->bookingSettings->getValidationRulesForUpdate($id);

        if (!$this->validate($rules)) {
            if ($this->request->isAJAX()) {
                return response()->setJSON([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $this->validator->getErrors()
                ]);
            }
            return redirect()->back()->withInput()->with('validation', $this->validator);
        }

        $payload = [];
        $fieldConfig = $this->bookingSettings->getFieldConfiguration();
        $customFields = $this->bookingSettings->getCustomFieldConfiguration();
        
        // Only include fields that are displayed in settings
        foreach ($fieldConfig as $fieldName => $config) {
            if ($config['display']) {
                $payload[$fieldName] = trim((string) $this->request->getPost($fieldName));
            }
        }

        if (array_key_exists('phone', $payload)) {
            $payload['phone'] = $this->phoneNumberService->normalize(
                $payload['phone'],
                $this->request->getPost('phone_country_code')
            ) ?? '';
        }

        $name = trim((string) $this->request->getPost('name'));
        if ($name && !isset($payload['first_name']) && !isset($payload['last_name'])) {
            $parts = preg_split('/\s+/', $name, 2);
            $payload['first_name'] = $parts[0] ?? '';
            $payload['last_name']  = $parts[1] ?? '';
        }

        if (!empty($customFields)) {
            log_message('info', '[CustomerManagement::update] Processing ' . count($customFields) . ' enabled custom fields for customer ID: ' . $id);
            
            // Load existing custom fields from database to preserve disabled fields
            $existing = [];
            if (!empty($customer['custom_fields'])) {
                $decoded = json_decode((string) $customer['custom_fields'], true);
                if (is_array($decoded)) {
                    $existing = $decoded;
                    log_message('info', '[CustomerManagement::update] Loaded existing custom fields: ' . json_encode($existing));
                }
            }

            // Update only the currently enabled custom fields
            foreach ($customFields as $fieldName => $config) {
                $raw = $this->request->getPost($fieldName);
                if ($config['type'] === 'checkbox') {
                    $value = $raw ? '1' : '0';
                } else {
                    $value = trim((string) ($raw ?? ''));
                }

                // Always save ALL enabled fields, even if empty
                // This allows newly enabled fields to be saved and preserves empty values
                $existing[$fieldName] = $value;
                log_message('info', "[CustomerManagement::update] Updating field '{$fieldName}' = '{$value}' (type: {$config['type']})");
            }

            // Save the merged custom fields (existing disabled fields + updated enabled fields)
            // This preserves data for disabled fields while updating enabled ones
            $payload['custom_fields'] = json_encode($existing, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            log_message('info', '[CustomerManagement::update] Final custom fields JSON: ' . $payload['custom_fields']);
        } else {
            log_message('info', '[CustomerManagement::update] No custom fields enabled in settings for customer ID: ' . $id);
        }

        $payload['updated_at'] = date('Y-m-d H:i:s');

        // Skip model validation since we already validated with the service
        // This prevents conflicts with model-level rules (e.g., email required, {id} placeholder issues)
        if ($this->customers->update($id, $payload, false)) {
            log_message('info', '[CustomerManagement] Successfully updated customer ID: ' . $id);
            
            if ($this->request->isAJAX()) {
                return response()->setJSON([
                    'success' => true,
                    'message' => 'Customer updated successfully.',
                    'redirect' => base_url('customer-management')
                ]);
            }
            
            log_message('info', '[CustomerManagement] Redirecting to /customer-management');
            return redirect()->to(base_url('customer-management'))->with('success', 'Customer updated successfully.');
        }
        
        // Log model validation errors if any
        $modelErrors = $this->customers->errors();
        if (!empty($modelErrors)) {
            log_message('error', '[CustomerManagement] Model validation errors for customer ID ' . $id . ': ' . json_encode($modelErrors));
        }
        
        log_message('error', '[CustomerManagement] Failed to update customer ID: ' . $id);
        
        if ($this->request->isAJAX()) {
            return response()->setJSON([
                'success' => false,
                'message' => 'Failed to update customer.'
            ]);
        }
        return redirect()->back()->withInput()->with('error', 'Failed to update customer.');
    }

    /**
     * Delete a customer if no appointments reference the record.
     */
    public function delete(string $identifier)
    {
        $currentUserId = (int) (session()->get('user_id') ?? 0);
        if (!$currentUserId) {
            return redirect()->to(base_url('auth/login'));
        }

        $result = $this->customerDeletionService->deleteCustomerByIdentifier($currentUserId, $identifier);

        if ($this->request->isAJAX()) {
            if ($result['success']) {
                $result['redirect'] = base_url('customer-management');
            }

            return $this->response
                ->setStatusCode($result['success'] ? 200 : ($result['statusCode'] ?? 400))
                ->setJSON($result);
        }

        if (!$result['success']) {
            return redirect()->to(base_url('customer-management'))->with('error', $result['message']);
        }

        return redirect()->to(base_url('customer-management'))->with('success', $result['message']);
    }

    /**
     * AJAX search endpoint for live search  
     */
    public function ajaxSearch()
    {
        $currentUserId = (int) (session()->get('user_id') ?? 0);
        if (!$currentUserId) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON(['error' => 'Unauthorized', 'success' => false]);
        }

        $q = trim((string) $this->request->getGet('q'));
        $currentRole = $this->resolveScopedRole();

        $staffCustomerIds = null;
        if ($currentRole === 'staff') {
            $staffCustomerIds = $this->appointmentService->resolveCustomerIdsForStaff($currentUserId);
        } elseif ($currentRole === 'provider') {
            $staffCustomerIds = $this->appointmentService->resolveCustomerIdsForProvider($currentUserId);
        }
        
        try {
            if ($q !== '') {
                $customers = $this->customers->search([
                    'q' => $q,
                    'limit' => 200,
                    'customer_ids' => $staffCustomerIds,
                ]);
            } else {
                $builder = $this->customers->orderBy('created_at', 'DESC');
                if ($staffCustomerIds !== null) {
                    if (empty($staffCustomerIds)) {
                        $builder->where('id', 0); // No results for unassigned staff.
                    } else {
                        $builder->whereIn('id', $staffCustomerIds);
                    }
                }
                $customers = $builder->findAll(200);
            }

            return $this->response->setJSON([
                'success' => true,
                'customers' => $customers,
                'count' => count($customers)
            ]);
        } catch (\Exception $e) {
            log_message('error', '[CustomerManagement::ajaxSearch] Error: ' . $e->getMessage());
            return $this->response
                ->setStatusCode(500)
                ->setJSON([
                    'success' => false,
                    'error' => 'Search failed: ' . $e->getMessage()
                ]);
        }
    }

    /**
     * View customer appointment history
     */
    public function history(string $identifier)
    {
        $currentUserId = (int) (session()->get('user_id') ?? 0);
        if (!$currentUserId) {
            return redirect()->to(base_url('auth/login'));
        }
        $currentRole = $this->resolveScopedRole();

        $customer = $this->customers->findByIdentifier($identifier);
        if (!$customer) {
            return redirect()->to(base_url('customer-management'))->with('error', 'Customer not found.');
        }
        if (!$this->isCustomerAccessible($currentRole, $currentUserId, (int) $customer['id'])) {
            return redirect()->to(base_url('customer-management'))->with('error', 'Access denied.');
        }

        $customerId = (int) $customer['id'];
        
        // Get query parameters for filtering
        $page = max(1, (int) $this->request->getGet('page') ?: 1);
        $status = $this->request->getGet('status');
        $providerId = $this->request->getGet('provider_id');
        $serviceId = $this->request->getGet('service_id');
        $dateFrom = $this->request->getGet('date_from');
        $dateTo = $this->request->getGet('date_to');

        $filters = [];
        if ($status) $filters['status'] = $status;
        if ($providerId) $filters['provider_id'] = (int) $providerId;
        if ($serviceId) $filters['service_id'] = (int) $serviceId;
        if ($dateFrom) $filters['date_from'] = $dateFrom;
        if ($dateTo) $filters['date_to'] = $dateTo;

        // Get appointment history with pagination
        $history = $this->appointmentService->getHistory($customerId, $filters, $page, 20);
        
        // Get stats
        $stats = $this->appointmentService->getStats($customerId);
        
        // Get upcoming appointments
        $upcoming = $this->appointmentService->getUpcoming($customerId, 5);
        
        // Get filter options (scope provider list by role)
        $scopedProviderIds = null;
        if ($currentRole === 'provider') {
            $scopedProviderIds = [$currentUserId];
        } elseif ($currentRole === 'staff') {
            $scopedProviderIds = $this->appointmentService->getProviderIdsForStaff($currentUserId);
        }
        $providers = $this->appointmentService->getProvidersForFilter($scopedProviderIds);
        $services = $this->appointmentService->getServicesForFilter();

        $data = [
            'title' => 'Customer History - ' . trim($customer['first_name'] . ' ' . ($customer['last_name'] ?? '')),
            'customer' => $customer,
            'customerIdentifier' => (string) ($customer['hash'] ?? $customer['id'] ?? ''),
            'history' => $history,
            'stats' => $stats,
            'upcoming' => $upcoming,
            'providers' => $providers,
            'services' => $services,
            'filters' => $filters,
            'currentPage' => $page,
        ];

        return view('customer-management/history', $data);
    }

    /**
     * Return all session roles with compatibility fallback to single role fields.
     *
     * @return array<int, string>
     */
    private function getSessionRoles(): array
    {
        $user = session()->get('user');
        $roles = [];

        if (is_array($user)) {
            $roles = $user['roles'] ?? [];
            if (!is_array($roles)) {
                $roles = [$roles];
            }

            $primaryRole = (string) ($user['role'] ?? '');
            if ($primaryRole !== '' && !in_array($primaryRole, $roles, true)) {
                $roles[] = $primaryRole;
            }
        }

        $fallbackRole = (string) (current_user_role() ?? '');
        if ($fallbackRole !== '' && !in_array($fallbackRole, $roles, true)) {
            $roles[] = $fallbackRole;
        }

        return array_values(array_filter(array_map(static fn($role) => trim((string) $role), $roles), static fn($role) => $role !== ''));
    }

    private function hasRole(string $role): bool
    {
        return in_array($role, $this->getSessionRoles(), true);
    }

    private function resolveScopedRole(): string
    {
        $roles = $this->getSessionRoles();
        foreach (['admin', 'provider', 'staff'] as $role) {
            if (in_array($role, $roles, true)) {
                return $role;
            }
        }

        return '';
    }
}
