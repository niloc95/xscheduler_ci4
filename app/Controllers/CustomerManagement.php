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
            foreach ($customFields as $fieldName => $config) {
                $raw = $this->request->getPost($fieldName);
                if ($config['type'] === 'checkbox') {
                    $value = $raw ? '1' : '0';
                } else {
                    $value = trim((string) ($raw ?? ''));
                }

                if ($value === '' && !$config['required']) {
                    continue;
                }

                $customFieldPayload[$fieldName] = $value;
            }

            if (!empty($customFieldPayload)) {
                $payload['custom_fields'] = json_encode($customFieldPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }

        // Support combined name field fallback (if first_name/last_name are not displayed)
        $name = trim((string) $this->request->getPost('name'));
        if ($name && !isset($payload['first_name']) && !isset($payload['last_name'])) {
            $parts = preg_split('/\s+/', $name, 2);
            $payload['first_name'] = $parts[0] ?? '';
            $payload['last_name']  = $parts[1] ?? '';
        }

        $id = $this->customers->insert($payload);
        if ($id) {
            return redirect()->to('/customer-management')->with('success', 'Customer created successfully.');
        }
        return redirect()->back()->withInput()->with('error', 'Failed to create customer.');
    }

    /**
     * Edit form
     */
    public function edit(int $id)
    {
        if (!session()->get('user_id')) {
            return redirect()->to('/auth/login');
        }
        $customer = $this->customers->find($id);
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
    public function update(int $id)
    {
        if (!session()->get('user_id')) {
            return redirect()->to('/auth/login');
        }
        $customer = $this->customers->find($id);
        if (!$customer) {
            return redirect()->to('/customer-management')->with('error', 'Customer not found.');
        }

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
            $existing = [];
            if (!empty($customer['custom_fields'])) {
                $decoded = json_decode((string) $customer['custom_fields'], true);
                if (is_array($decoded)) {
                    $existing = $decoded;
                }
            }

            foreach ($customFields as $fieldName => $config) {
                $raw = $this->request->getPost($fieldName);
                if ($config['type'] === 'checkbox') {
                    $value = $raw ? '1' : '0';
                } else {
                    $value = trim((string) ($raw ?? ''));
                }

                if ($value === '' && !$config['required']) {
                    unset($existing[$fieldName]);
                    continue;
                }

                $existing[$fieldName] = $value;
            }

            if (!empty($existing)) {
                $payload['custom_fields'] = json_encode($existing, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } elseif (!empty($customer['custom_fields'])) {
                $payload['custom_fields'] = null;
            }
        }

        if ($this->customers->update($id, $payload)) {
            return redirect()->to('/customer-management')->with('success', 'Customer updated successfully.');
        }
        return redirect()->back()->withInput()->with('error', 'Failed to update customer.');
    }
}
