<?php

namespace App\Services;

use App\Models\SettingModel;

/**
 * BookingSettingsService
 * 
 * Centralized service for fetching and managing booking form field configurations
 * from the settings database. Used by Customer Management and Appointment Booking
 * to dynamically show/hide/require fields based on Settings → Booking Tab.
 */
class BookingSettingsService
{
    protected SettingModel $settingModel;
    private ?array $fieldConfigCache = null;
    private ?array $customFieldConfigCache = null;

    public function __construct()
    {
        $this->settingModel = new SettingModel();
    }

    /**
     * Get the complete field configuration for booking forms
     * 
     * Returns an associative array mapping field names to their configuration:
     * [
     *   'first_name' => ['display' => true, 'required' => false],
     *   'last_name' => ['display' => true, 'required' => false],
     *   'email' => ['display' => true, 'required' => true],
     *   'phone' => ['display' => true, 'required' => false],
     *   'address' => ['display' => false, 'required' => false],
     *   'notes' => ['display' => true, 'required' => false],
     * ]
     * 
     * @return array Field configuration array
     */
    public function getFieldConfiguration(): array
    {
        if ($this->fieldConfigCache !== null) {
            return $this->fieldConfigCache;
        }

        $settings = $this->settingModel->getByKeys([
            'booking.first_names_display',
            'booking.first_names_required',
            'booking.surname_display',
            'booking.surname_required',
            'booking.email_display',
            'booking.email_required',
            'booking.phone_display',
            'booking.phone_required',
            'booking.address_display',
            'booking.address_required',
            'booking.notes_display',
            'booking.notes_required',
        ]);

        return $this->fieldConfigCache = [
            'first_name' => [
                'display' => $this->toBool($settings['booking.first_names_display'] ?? '1'),
                'required' => $this->toBool($settings['booking.first_names_required'] ?? '0'),
            ],
            'last_name' => [
                'display' => $this->toBool($settings['booking.surname_display'] ?? '1'),
                'required' => $this->toBool($settings['booking.surname_required'] ?? '0'),
            ],
            'email' => [
                'display' => $this->toBool($settings['booking.email_display'] ?? '1'),
                'required' => $this->toBool($settings['booking.email_required'] ?? '1'), // Email typically required
            ],
            'phone' => [
                'display' => $this->toBool($settings['booking.phone_display'] ?? '1'),
                'required' => $this->toBool($settings['booking.phone_required'] ?? '0'),
            ],
            'address' => [
                'display' => $this->toBool($settings['booking.address_display'] ?? '0'),
                'required' => $this->toBool($settings['booking.address_required'] ?? '0'),
            ],
            'notes' => [
                'display' => $this->toBool($settings['booking.notes_display'] ?? '1'),
                'required' => $this->toBool($settings['booking.notes_required'] ?? '0'),
            ],
        ];
    }

    /**
     * Get configuration for enabled custom booking fields
     *
     * @return array<string, array{index:int,title:string,type:string,required:bool}>
     */
    public function getCustomFieldConfiguration(): array
    {
        if ($this->customFieldConfigCache !== null) {
            return $this->customFieldConfigCache;
        }

        $keys = [];
        for ($i = 1; $i <= 6; $i++) {
            $keys[] = "booking.custom_field_{$i}_enabled";
            $keys[] = "booking.custom_field_{$i}_title";
            $keys[] = "booking.custom_field_{$i}_type";
            $keys[] = "booking.custom_field_{$i}_required";
        }

        $settings = $this->settingModel->getByKeys($keys);
        $customFields = [];

        for ($i = 1; $i <= 6; $i++) {
            $enabled = $this->toBool($settings["booking.custom_field_{$i}_enabled"] ?? '0');
            if (!$enabled) {
                continue;
            }

            $fieldKey = "custom_field_{$i}";
            $title = trim((string) ($settings["booking.custom_field_{$i}_title"] ?? ''));
            $type = strtolower((string) ($settings["booking.custom_field_{$i}_type"] ?? 'text'));

            $customFields[$fieldKey] = [
                'index' => $i,
                'title' => $title !== '' ? $title : "Custom Field {$i}",
                'type' => in_array($type, ['text', 'textarea', 'select', 'checkbox'], true) ? $type : 'text',
                'required' => $this->toBool($settings["booking.custom_field_{$i}_required"] ?? '0'),
            ];
        }

        return $this->customFieldConfigCache = $customFields;
    }

    /**
     * Get validation rules for customer form based on booking settings
     * 
     * @return array Validation rules compatible with CodeIgniter validation
     */
    public function getValidationRules(): array
    {
        $config = $this->getFieldConfiguration();
        $rules = [];

        foreach ($config as $field => $settings) {
            if (!$settings['display']) {
                // If field is not displayed, it should be permit_empty and not required
                $rules[$field] = 'permit_empty';
                continue;
            }

            $fieldRules = [];
            
            if ($settings['required']) {
                $fieldRules[] = 'required';
            } else {
                $fieldRules[] = 'permit_empty';
            }

            // Add field-specific validation rules
            switch ($field) {
                case 'first_name':
                case 'last_name':
                    $fieldRules[] = 'max_length[100]';
                    break;
                
                case 'email':
                    $fieldRules[] = 'valid_email';
                    $fieldRules[] = 'is_unique[customers.email]';
                    break;
                
                case 'phone':
                    $fieldRules[] = 'max_length[20]';
                    break;
                
                case 'address':
                    $fieldRules[] = 'max_length[255]';
                    break;
                
                case 'notes':
                    $fieldRules[] = 'max_length[1000]';
                    break;
            }

            $rules[$field] = implode('|', $fieldRules);
        }

        $customFields = $this->getCustomFieldConfiguration();
        foreach ($customFields as $field => $settings) {
            $fieldRules = [];

            $fieldRules[] = $settings['required'] ? 'required' : 'permit_empty';

            switch ($settings['type']) {
                case 'textarea':
                    $fieldRules[] = 'max_length[2000]';
                    break;
                case 'checkbox':
                    $fieldRules[] = 'in_list[0,1]';
                    break;
                case 'select':
                case 'text':
                default:
                    $fieldRules[] = 'max_length[255]';
                    break;
            }

            $rules[$field] = implode('|', array_unique($fieldRules));
        }

        return $rules;
    }

    /**
     * Get validation rules for updating an existing customer
     * 
     * @param int $customerId Customer ID to exclude from unique email check
     * @return array Validation rules
     */
    public function getValidationRulesForUpdate(int $customerId): array
    {
        $rules = $this->getValidationRules();
        
        // Update email rule to exclude current customer from unique check
        if (isset($rules['email'])) {
            $rules['email'] = str_replace(
                'is_unique[customers.email]',
                "is_unique[customers.email,id,{$customerId}]",
                $rules['email']
            );
        }

        return $rules;
    }

    /**
     * Check if a specific field should be displayed
     * 
     * @param string $fieldName Field name (first_name, last_name, email, etc.)
     * @return bool True if field should be displayed
     */
    public function isFieldDisplayed(string $fieldName): bool
    {
        $config = $this->getFieldConfiguration();
        return $config[$fieldName]['display'] ?? false;
    }

    /**
     * Check if a specific field is required
     * 
     * @param string $fieldName Field name
     * @return bool True if field is required
     */
    public function isFieldRequired(string $fieldName): bool
    {
        $config = $this->getFieldConfiguration();
        return $config[$fieldName]['required'] ?? false;
    }

    /**
     * Get a list of visible field names
     * 
     * @return array Array of field names that should be visible
     */
    public function getVisibleFields(): array
    {
        $config = $this->getFieldConfiguration();
        return array_keys(array_filter($config, fn($field) => $field['display']));
    }

    /**
     * Get a list of required field names
     * 
     * @return array Array of field names that are required
     */
    public function getRequiredFields(): array
    {
        $config = $this->getFieldConfiguration();
        return array_keys(array_filter($config, fn($field) => $field['required']));
    }

    /**
     * Convert various truthy/falsy values to boolean
     * 
     * @param mixed $value Value to convert
     * @return bool Boolean value
     */
    private function toBool($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        
        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
        }
        
        return (bool) $value;
    }
}
