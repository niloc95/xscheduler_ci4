<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class SettingsViewAudit extends BaseCommand
{
    protected $group       = 'Custom';
    protected $name        = 'settings:view-audit';
    protected $description = 'Audit Settings View form fields against controller mappings';

    public function run(array $params)
    {
        CLI::write('=== SETTINGS VIEW-CONTROLLER MAPPING AUDIT ===', 'yellow');
        CLI::newLine();

        // Get form fields from the view
        $viewFields = $this->extractFormFieldsFromView();
        
        // Get controller mappings
        $controllerMappings = $this->getControllerMappings();
        
        // Get expected fields from controller load
        $expectedFields = $this->getExpectedFieldsFromController();

        CLI::write('1. Form Fields Found in View:', 'cyan');
        foreach ($viewFields as $field) {
            CLI::write("  - {$field}", 'white');
        }
        CLI::write("  Total: " . count($viewFields), 'yellow');
        CLI::newLine();

        CLI::write('2. Controller Field Mappings:', 'cyan');
        foreach ($controllerMappings as $dbKey => $formField) {
            CLI::write("  - {$formField} -> {$dbKey}", 'white');
        }
        CLI::write("  Total: " . count($controllerMappings), 'yellow');
        CLI::newLine();

        CLI::write('3. Expected Fields (from controller load):', 'cyan');
        foreach ($expectedFields as $field) {
            CLI::write("  - {$field}", 'white');
        }
        CLI::write("  Total: " . count($expectedFields), 'yellow');
        CLI::newLine();

        // Analyze mappings
        CLI::write('4. Mapping Analysis:', 'cyan');
        
        $unmappedViewFields = [];
        $mappedFields = [];
        $missingInView = [];

        // Check if view fields are mapped in controller
        foreach ($viewFields as $viewField) {
            if (in_array($viewField, $controllerMappings)) {
                $mappedFields[] = $viewField;
            } else {
                $unmappedViewFields[] = $viewField;
            }
        }

        // Check if controller mappings have corresponding view fields
        foreach ($controllerMappings as $dbKey => $formField) {
            if (!in_array($formField, $viewFields)) {
                $missingInView[] = $formField . " (maps to {$dbKey})";
            }
        }

        CLI::write("✓ Properly Mapped Fields: " . count($mappedFields), 'green');
        CLI::write("⚠ Unmapped View Fields: " . count($unmappedViewFields), 'yellow');
        CLI::write("✗ Missing in View: " . count($missingInView), 'red');
        CLI::newLine();

        if (!empty($unmappedViewFields)) {
            CLI::write('Unmapped View Fields (no controller handling):', 'yellow');
            foreach ($unmappedViewFields as $field) {
                CLI::write("  - {$field}", 'red');
            }
            CLI::newLine();
        }

        if (!empty($missingInView)) {
            CLI::write('Controller Mappings Missing in View:', 'red');
            foreach ($missingInView as $field) {
                CLI::write("  - {$field}", 'red');
            }
            CLI::newLine();
        }

        // Check value loading
        CLI::write('5. Value Loading Check:', 'cyan');
        $this->checkValueLoading($expectedFields, $viewFields);

        CLI::newLine();
        CLI::write('=== SUMMARY ===', 'yellow');
        $totalIssues = count($unmappedViewFields) + count($missingInView);
        
        if ($totalIssues === 0) {
            CLI::write("✓ All form fields are properly mapped and handled.", 'green');
            CLI::write("✓ No data flow issues detected.", 'green');
        } else {
            CLI::write("⚠ Found {$totalIssues} potential data flow issues.", 'red');
            CLI::write("⚠ Review the unmapped/missing fields above.", 'yellow');
        }

        return $totalIssues === 0 ? 0 : 1;
    }

    private function extractFormFieldsFromView(): array
    {
        $viewPath = APPPATH . 'Views/settings.php';
        $content = file_get_contents($viewPath);
        
        $fields = [];
        
        // Extract name attributes from form elements
        preg_match_all('/name=["\']([^"\']+)["\']/', $content, $matches);
        
        foreach ($matches[1] as $field) {
            // Skip hidden and special fields
            if (!in_array($field, ['form_source', 'company_logo', 'blocked_periods'])) {
                $fields[] = $field;
            }
        }
        
        return array_unique($fields);
    }

    private function getControllerMappings(): array
    {
        return [
            'general.company_name' => 'company_name',
            'general.company_email' => 'company_email',
            'general.company_link' => 'company_link',
            'general.telephone_number' => 'telephone_number',
            'general.mobile_number' => 'mobile_number',
            'general.business_address' => 'business_address',
            'localization.time_format' => 'time_format',
            'localization.first_day' => 'first_day',
            'localization.language' => 'language',
            'localization.timezone' => 'timezone',
            'localization.currency' => 'currency',
            'booking.first_names_display' => 'booking_first_names_display',
            'booking.first_names_required' => 'booking_first_names_required',
            'booking.surname_display' => 'booking_surname_display',
            'booking.surname_required' => 'booking_surname_required',
            'booking.email_display' => 'booking_email_display',
            'booking.email_required' => 'booking_email_required',
            'booking.phone_display' => 'booking_phone_display',
            'booking.phone_required' => 'booking_phone_required',
            'booking.address_display' => 'booking_address_display',
            'booking.address_required' => 'booking_address_required',
            'booking.notes_display' => 'booking_notes_display',
            'booking.notes_required' => 'booking_notes_required',
            'booking.custom_field_1_enabled' => 'booking_custom_field_1_enabled',
            'booking.custom_field_1_title' => 'booking_custom_field_1_title',
            'booking.custom_field_1_type' => 'booking_custom_field_1_type',
            'booking.custom_field_1_required' => 'booking_custom_field_1_required',
            'booking.custom_field_2_enabled' => 'booking_custom_field_2_enabled',
            'booking.custom_field_2_title' => 'booking_custom_field_2_title',
            'booking.custom_field_2_type' => 'booking_custom_field_2_type',
            'booking.custom_field_2_required' => 'booking_custom_field_2_required',
            'booking.custom_field_3_enabled' => 'booking_custom_field_3_enabled',
            'booking.custom_field_3_title' => 'booking_custom_field_3_title',
            'booking.custom_field_3_type' => 'booking_custom_field_3_type',
            'booking.custom_field_3_required' => 'booking_custom_field_3_required',
            'booking.custom_field_4_enabled' => 'booking_custom_field_4_enabled',
            'booking.custom_field_4_title' => 'booking_custom_field_4_title',
            'booking.custom_field_4_type' => 'booking_custom_field_4_type',
            'booking.custom_field_4_required' => 'booking_custom_field_4_required',
            'booking.custom_field_5_enabled' => 'booking_custom_field_5_enabled',
            'booking.custom_field_5_title' => 'booking_custom_field_5_title',
            'booking.custom_field_5_type' => 'booking_custom_field_5_type',
            'booking.custom_field_5_required' => 'booking_custom_field_5_required',
            'booking.custom_field_6_enabled' => 'booking_custom_field_6_enabled',
            'booking.custom_field_6_title' => 'booking_custom_field_6_title',
            'booking.custom_field_6_type' => 'booking_custom_field_6_type',
            'booking.custom_field_6_required' => 'booking_custom_field_6_required',
            'business.work_start' => 'work_start',
            'business.work_end' => 'work_end',
            'business.break_start' => 'break_start',
            'business.break_end' => 'break_end',
            'business.blocked_periods' => 'blocked_periods',
            'business.reschedule' => 'reschedule',
            'business.cancel' => 'cancel',
            'business.future_limit' => 'future_limit',
            'booking.statuses' => 'statuses',
            'legal.cookie_notice' => 'cookie_notice',
            'legal.terms' => 'terms',
            'legal.privacy' => 'privacy',
            'integrations.webhook_url' => 'webhook_url',
            'integrations.analytics' => 'analytics',
            'integrations.api_integrations' => 'api_integrations',
            'integrations.ldap_enabled' => 'ldap_enabled',
            'integrations.ldap_host' => 'ldap_host',
            'integrations.ldap_dn' => 'ldap_dn'
        ];
    }

    private function getExpectedFieldsFromController(): array
    {
        return [
            'general.company_name',
            'general.company_email',
            'general.company_link',
            'general.telephone_number',
            'general.mobile_number',
            'general.business_address',
            'localization.time_format',
            'localization.first_day',
            'localization.language',
            'localization.timezone',
            'localization.currency',
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
            'booking.custom_field_1_enabled',
            'booking.custom_field_1_title',
            'booking.custom_field_1_type',
            'booking.custom_field_1_required',
            'booking.custom_field_2_enabled',
            'booking.custom_field_2_title',
            'booking.custom_field_2_type',
            'booking.custom_field_2_required',
            'booking.custom_field_3_enabled',
            'booking.custom_field_3_title',
            'booking.custom_field_3_type',
            'booking.custom_field_3_required',
            'booking.custom_field_4_enabled',
            'booking.custom_field_4_title',
            'booking.custom_field_4_type',
            'booking.custom_field_4_required',
            'booking.custom_field_5_enabled',
            'booking.custom_field_5_title',
            'booking.custom_field_5_type',
            'booking.custom_field_5_required',
            'booking.custom_field_6_enabled',
            'booking.custom_field_6_title',
            'booking.custom_field_6_type',
            'booking.custom_field_6_required',
            'booking.fields',
            'booking.custom_fields',
            'booking.statuses',
            'business.work_start',
            'business.work_end',
            'business.break_start',
            'business.break_end',
            'business.blocked_periods',
            'business.reschedule',
            'business.cancel',
            'business.future_limit',
            'legal.cookie_notice',
            'legal.terms',
            'legal.privacy',
            'integrations.webhook_url',
            'integrations.analytics',
            'integrations.api_integrations',
            'integrations.ldap_enabled',
            'integrations.ldap_host',
            'integrations.ldap_dn'
        ];
    }

    private function checkValueLoading(array $expectedFields, array $viewFields): void
    {
        $viewPath = APPPATH . 'Views/settings.php';
        $content = file_get_contents($viewPath);
        
        $issuesFound = 0;
        
        foreach ($expectedFields as $field) {
            // Check if the field is referenced in value loading
            $pattern = '/\$settings\[[\'"]' . preg_quote($field, '/') . '[\'"]]/';
            if (preg_match($pattern, $content)) {
                CLI::write("  ✓ {$field}: Value loading OK", 'green');
            } else {
                CLI::write("  ⚠ {$field}: No value loading found", 'yellow');
                $issuesFound++;
            }
        }
        
        if ($issuesFound === 0) {
            CLI::write("✓ All expected fields have proper value loading.", 'green');
        } else {
            CLI::write("⚠ {$issuesFound} fields may not load values from database.", 'yellow');
        }
    }
}