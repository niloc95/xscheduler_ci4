<?php

namespace App\Controllers;

use App\Models\CustomerModel;
use App\Services\BookingSettingsService;

class CustomerManagement extends BaseController
{
    protected CustomerModel $customers;
    protected BookingSettingsService $bookingSettings;

    public function __construct()
    {
        $this->customers = new CustomerModel();
        $this->bookingSettings = new BookingSettingsService();
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

        $data = [
            'title' => 'Customer Management - WebSchedulr',
            'customers' => $customers,
            'currentUser' => session()->get('user'),
            'q' => $q,
        ];

        return view('customer_management/index', $data);
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
        return view('customer_management/create', $data);
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
            return redirect()->to('/customer-management')->with('success', 'Customer created successfully.');
        }
        
        // Log model validation errors if any
        $modelErrors = $this->customers->errors();
        if (!empty($modelErrors)) {
            log_message('error', '[CustomerManagement] Model validation errors: ' . json_encode($modelErrors));
        }
        
        log_message('error', '[CustomerManagement] Failed to create customer');
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
        return view('customer_management/edit', $data);
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
            return redirect()->to('/customer-management')->with('error', 'Customer not found.');
        }

        $id = $customer['id'];

        // Use dynamic validation rules from booking settings
        $rules = $this->bookingSettings->getValidationRulesForUpdate($id);

        if (!$this->validate($rules)) {
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
            log_message('info', '[CustomerManagement] Redirecting to /customer-management');
            return redirect()->to('/customer-management')->with('success', 'Customer updated successfully.');
        }
        
        // Log model validation errors if any
        $modelErrors = $this->customers->errors();
        if (!empty($modelErrors)) {
            log_message('error', '[CustomerManagement] Model validation errors for customer ID ' . $id . ': ' . json_encode($modelErrors));
        }
        
        log_message('error', '[CustomerManagement] Failed to update customer ID: ' . $id);
        return redirect()->back()->withInput()->with('error', 'Failed to update customer.');
    }
}
