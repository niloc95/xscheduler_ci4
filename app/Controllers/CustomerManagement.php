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
 * GET  /customers                  : List all customers with search
 * GET  /customers/create           : Show customer creation form
 * POST /customers/store            : Create new customer record
 * GET  /customers/edit/:hash       : Show edit form for customer
 * POST /customers/update/:hash     : Update existing customer
 * GET  /customers/view/:hash       : View customer profile with history
 * POST /customers/delete/:hash     : Soft delete customer record
 * GET  /customers/search           : AJAX search endpoint
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
 * - Role-based access (staff+ can view, admin can delete)
 * - CSRF protection on forms
 * - Input validation and sanitization
 * 
 * DEPENDENCIES:
 * -----------------------------------------------------------------------------
 * - CustomerModel              : Database operations
 * - BookingSettingsService     : Field configuration
 * - CustomerAppointmentService : History and statistics
 * 
 * @see         app/Views/customers/ for view templates
 * @see         app/Models/CustomerModel.php for data model
 * @package     App\Controllers
 * @extends     BaseController
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

namespace App\Controllers;

use App\Models\CustomerModel;
use App\Services\BookingSettingsService;
use App\Services\CustomerAppointmentService;

class CustomerManagement extends BaseController
{
    protected CustomerModel $customers;
    protected BookingSettingsService $bookingSettings;
    protected CustomerAppointmentService $appointmentService;

    public function __construct()
    {
        $this->customers = new CustomerModel();
        $this->bookingSettings = new BookingSettingsService();
        $this->appointmentService = new CustomerAppointmentService();
    }

    /**
     * List customers
     */
    public function index()
    {
        $currentUserId = (int) (session()->get('user_id') ?? 0);
        if (!$currentUserId) {
            return redirect()->to('/auth/login');
        }

        // Basic listing; later we can scope by provider if schema supports assignment
        $q = trim((string) $this->request->getGet('q'));
        if ($q !== '') {
            $customers = $this->customers->search(['q' => $q, 'limit' => 200]);
        } else {
            $customers = $this->customers->orderBy('created_at', 'DESC')->findAll(200);
        }

        // Get total customer count
        $totalCustomers = $this->customers->countAllResults();

        $data = [
            'title' => 'Customer Management - WebSchedulr',
            'customers' => $customers,
            'currentUser' => session()->get('user'),
            'q' => $q,
            'totalCustomers' => $totalCustomers,
        ];

        return view('customer-management/index', $data);
    }

    /**
     * Show create form
     */
    public function create()
    {
        if (!session()->get('user_id')) {
            return redirect()->to('/auth/login');
        }
        
        $fieldConfig = $this->bookingSettings->getFieldConfiguration();
        $customFields = $this->bookingSettings->getCustomFieldConfiguration();
        
        $data = [
            'title' => 'Create Customer - WebSchedulr',
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
            return redirect()->to('/auth/login');
        }

        // Use dynamic validation rules from booking settings
        $rules = $this->bookingSettings->getValidationRules();

        if (!$this->validate($rules)) {
            // Return JSON for SPA or HTML for traditional form
            if ($this->request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest') {
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

        // Skip model validation since we already validated with the service
        // This prevents conflicts with model-level rules
        $id = $this->customers->insert($payload, false);
        if ($id) {
            log_message('info', '[CustomerManagement] Successfully created customer ID: ' . $id);
            
            // Return JSON for SPA or HTML for traditional form
            if ($this->request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest') {
                return response()->setJSON([
                    'success' => true,
                    'message' => 'Customer created successfully.',
                    'redirect' => '/customer-management'
                ]);
            }
            return redirect()->to('/customer-management')->with('success', 'Customer created successfully.');
        }
        
        // Log model validation errors if any
        $modelErrors = $this->customers->errors();
        if (!empty($modelErrors)) {
            log_message('error', '[CustomerManagement] Model validation errors: ' . json_encode($modelErrors));
        }
        
        log_message('error', '[CustomerManagement] Failed to create customer');
        
        // Return JSON for SPA or HTML for traditional form
        if ($this->request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest') {
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
    public function edit(string $hash)
    {
        if (!session()->get('user_id')) {
            return redirect()->to('/auth/login');
        }
        $customer = $this->customers->findByHash($hash);
        if (!$customer) {
            return redirect()->to('/customer-management')->with('error', 'Customer not found.');
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
            'title' => 'Edit Customer - WebSchedulr',
            'customer' => $customer,
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
    public function update(string $hash)
    {
        if (!session()->get('user_id')) {
            return redirect()->to('/auth/login');
        }
        $customer = $this->customers->findByHash($hash);
        if (!$customer) {
            $errorMsg = 'Customer not found.';
            if ($this->request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest') {
                return response()->setJSON(['success' => false, 'message' => $errorMsg]);
            }
            return redirect()->to('/customer-management')->with('error', $errorMsg);
        }

        $id = $customer['id'];

        // Use dynamic validation rules from booking settings
        $rules = $this->bookingSettings->getValidationRulesForUpdate($id);

        if (!$this->validate($rules)) {
            if ($this->request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest') {
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

        // Skip model validation since we already validated with the service
        // This prevents conflicts with model-level rules (e.g., email required, {id} placeholder issues)
        if ($this->customers->update($id, $payload, false)) {
            log_message('info', '[CustomerManagement] Successfully updated customer ID: ' . $id);
            
            if ($this->request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest') {
                return response()->setJSON([
                    'success' => true,
                    'message' => 'Customer updated successfully.',
                    'redirect' => '/customer-management'
                ]);
            }
            
            log_message('info', '[CustomerManagement] Redirecting to /customer-management');
            return redirect()->to('/customer-management')->with('success', 'Customer updated successfully.');
        }
        
        // Log model validation errors if any
        $modelErrors = $this->customers->errors();
        if (!empty($modelErrors)) {
            log_message('error', '[CustomerManagement] Model validation errors for customer ID ' . $id . ': ' . json_encode($modelErrors));
        }
        
        log_message('error', '[CustomerManagement] Failed to update customer ID: ' . $id);
        
        if ($this->request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest') {
            return response()->setJSON([
                'success' => false,
                'message' => 'Failed to update customer.'
            ]);
        }
        return redirect()->back()->withInput()->with('error', 'Failed to update customer.');
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
        
        try {
            if ($q !== '') {
                $customers = $this->customers->search(['q' => $q, 'limit' => 200]);
            } else {
                $customers = $this->customers->orderBy('created_at', 'DESC')->findAll(200);
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
    public function history(string $hash)
    {
        if (!session()->get('user_id')) {
            return redirect()->to('/auth/login');
        }
        
        $customer = $this->customers->findByHash($hash);
        if (!$customer) {
            return redirect()->to('/customer-management')->with('error', 'Customer not found.');
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
        
        // Get filter options
        $providers = $this->appointmentService->getProvidersForFilter();
        $services = $this->appointmentService->getServicesForFilter();

        $data = [
            'title' => 'Customer History - ' . trim($customer['first_name'] . ' ' . ($customer['last_name'] ?? '')),
            'customer' => $customer,
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
}
