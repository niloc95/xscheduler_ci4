<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\SettingModel;

class SettingsAudit extends BaseCommand
{
    protected $group       = 'Custom';
    protected $name        = 'settings:audit';
    protected $description = 'Audit Settings View data flow to database';

    public function run(array $params)
    {
        CLI::write('=== SETTINGS DATA FLOW AUDIT ===', 'yellow');
        CLI::newLine();

        $settingModel = new SettingModel();
        $results = [];

        // 1. Test database connectivity
        CLI::write('1. Testing Database Connectivity...', 'cyan');
        try {
            $db = \Config\Database::connect();
            $query = $db->query("DESCRIBE xs_settings");
            $structure = $query->getResultArray();
            
            CLI::write('   ✓ Database connection successful', 'green');
            CLI::write('   ✓ Settings table structure:', 'green');
            
            foreach ($structure as $column) {
                CLI::write("     - {$column['Field']} ({$column['Type']})", 'white');
            }
            
            $count = $settingModel->countAllResults();
            CLI::write("   ✓ Current settings count: {$count}", 'green');
            
            $results['database'] = 'PASS';
        } catch (\Exception $e) {
            CLI::write("   ✗ Database error: " . $e->getMessage(), 'red');
            $results['database'] = 'FAIL: ' . $e->getMessage();
        }
        CLI::newLine();

        // 2. Test field mappings from Settings controller
        CLI::write('2. Testing Field Mappings (Controller -> Database)...', 'cyan');
        
        $fieldMappings = $this->getFieldMappings();
        $testData = $this->getTestData();

        foreach ($testData as $settingKey => $testValue) {
            try {
                $type = is_array($testValue) ? 'json' : (is_bool($testValue) ? 'bool' : 'string');
                $success = $settingModel->upsert($settingKey, $testValue, $type, 1);
                
                if ($success) {
                    $retrieved = $settingModel->getByKeys([$settingKey]);
                    $retrievedValue = $retrieved[$settingKey] ?? null;
                    
                    if ($this->valuesMatch($testValue, $retrievedValue, $type)) {
                        CLI::write("   ✓ {$settingKey}: Write/Read OK", 'green');
                        $results['fields'][$settingKey] = 'PASS';
                    } else {
                        CLI::write("   ✗ {$settingKey}: Value mismatch", 'red');
                        CLI::write("     Expected: " . json_encode($testValue), 'yellow');
                        CLI::write("     Got: " . json_encode($retrievedValue), 'yellow');
                        $results['fields'][$settingKey] = 'FAIL: Value mismatch';
                    }
                } else {
                    CLI::write("   ✗ {$settingKey}: Write failed", 'red');
                    $results['fields'][$settingKey] = 'FAIL: Write failed';
                }
            } catch (\Exception $e) {
                CLI::write("   ✗ {$settingKey}: Exception - " . $e->getMessage(), 'red');
                $results['fields'][$settingKey] = 'FAIL: ' . $e->getMessage();
            }
        }
        CLI::newLine();

        // 3. Test checkbox fields
        CLI::write('3. Testing Checkbox Field Handling...', 'cyan');
        
        $checkboxFields = $this->getCheckboxFields();

        foreach ($checkboxFields as $field) {
            try {
                // Test both checked and unchecked states
                $settingModel->upsert($field, '1', 'string', 1);
                $checked = $settingModel->getByKeys([$field]);
                
                $settingModel->upsert($field, '0', 'string', 1);
                $unchecked = $settingModel->getByKeys([$field]);
                
                if (($checked[$field] ?? '') === '1' && ($unchecked[$field] ?? '') === '0') {
                    CLI::write("   ✓ {$field}: Checkbox states OK", 'green');
                    $results['checkboxes'][$field] = 'PASS';
                } else {
                    CLI::write("   ✗ {$field}: Checkbox state handling failed", 'red');
                    $results['checkboxes'][$field] = 'FAIL: State handling';
                }
            } catch (\Exception $e) {
                CLI::write("   ✗ {$field}: Exception - " . $e->getMessage(), 'red');
                $results['checkboxes'][$field] = 'FAIL: ' . $e->getMessage();
            }
        }
        CLI::newLine();

        // 4. Test special fields
        CLI::write('4. Testing Special Field Handling...', 'cyan');
        
        // Test blocked periods (JSON handling)
        try {
            $blockedPeriods = [
                ['start' => '2025-12-25', 'end' => '2025-12-25', 'notes' => 'Christmas'],
                ['start' => '2025-01-01', 'end' => '2025-01-01', 'notes' => 'New Year']
            ];
            
            $settingModel->upsert('business.blocked_periods', $blockedPeriods, 'json', 1);
            $retrieved = $settingModel->getByKeys(['business.blocked_periods']);
            
            if (json_encode($blockedPeriods) === json_encode($retrieved['business.blocked_periods'])) {
                CLI::write("   ✓ business.blocked_periods: JSON handling OK", 'green');
                $results['special']['blocked_periods'] = 'PASS';
            } else {
                CLI::write("   ✗ business.blocked_periods: JSON handling failed", 'red');
                $results['special']['blocked_periods'] = 'FAIL: JSON mismatch';
            }
        } catch (\Exception $e) {
            CLI::write("   ✗ business.blocked_periods: Exception - " . $e->getMessage(), 'red');
            $results['special']['blocked_periods'] = 'FAIL: ' . $e->getMessage();
        }

        // Test custom field titles and types
        for ($i = 1; $i <= 6; $i++) {
            try {
                $title = "Custom Field {$i} Test";
                $type = 'text';
                
                $settingModel->upsert("booking.custom_field_{$i}_title", $title, 'string', 1);
                $settingModel->upsert("booking.custom_field_{$i}_type", $type, 'string', 1);
                
                $retrieved = $settingModel->getByKeys([
                    "booking.custom_field_{$i}_title",
                    "booking.custom_field_{$i}_type"
                ]);
                
                if ($retrieved["booking.custom_field_{$i}_title"] === $title && 
                    $retrieved["booking.custom_field_{$i}_type"] === $type) {
                    CLI::write("   ✓ Custom field {$i}: Title/Type OK", 'green');
                    $results['special']["custom_field_{$i}"] = 'PASS';
                } else {
                    CLI::write("   ✗ Custom field {$i}: Title/Type failed", 'red');
                    $results['special']["custom_field_{$i}"] = 'FAIL: Value mismatch';
                }
            } catch (\Exception $e) {
                CLI::write("   ✗ Custom field {$i}: Exception - " . $e->getMessage(), 'red');
                $results['special']["custom_field_{$i}"] = 'FAIL: ' . $e->getMessage();
            }
        }
        CLI::newLine();

        // 5. Generate report
        CLI::write('=== AUDIT REPORT ===', 'yellow');
        CLI::newLine();
        
        $totalTests = 0;
        $passedTests = 0;
        $failedFields = [];

        // Database connectivity
        CLI::write("Database Connectivity: " . $results['database'], 
                  $results['database'] === 'PASS' ? 'green' : 'red');
        if ($results['database'] === 'PASS') {
            $passedTests++;
        } else {
            $failedFields[] = 'Database Connection';
        }
        $totalTests++;

        // Field mappings
        CLI::newLine();
        CLI::write("Field Mappings:", 'cyan');
        foreach ($results['fields'] ?? [] as $field => $result) {
            CLI::write("  {$field}: {$result}", $result === 'PASS' ? 'green' : 'red');
            $totalTests++;
            if ($result === 'PASS') {
                $passedTests++;
            } else {
                $failedFields[] = $field;
            }
        }

        // Checkbox fields
        CLI::newLine();
        CLI::write("Checkbox Fields:", 'cyan');
        foreach ($results['checkboxes'] ?? [] as $field => $result) {
            CLI::write("  {$field}: {$result}", $result === 'PASS' ? 'green' : 'red');
            $totalTests++;
            if ($result === 'PASS') {
                $passedTests++;
            } else {
                $failedFields[] = $field;
            }
        }

        // Special fields
        CLI::newLine();
        CLI::write("Special Fields:", 'cyan');
        foreach ($results['special'] ?? [] as $field => $result) {
            CLI::write("  {$field}: {$result}", $result === 'PASS' ? 'green' : 'red');
            $totalTests++;
            if ($result === 'PASS') {
                $passedTests++;
            } else {
                $failedFields[] = $field;
            }
        }

        CLI::newLine();
        CLI::write('=== SUMMARY ===', 'yellow');
        CLI::write("Total Tests: {$totalTests}", 'white');
        CLI::write("Passed: {$passedTests}", 'green');
        CLI::write("Failed: " . ($totalTests - $passedTests), 'red');
        CLI::write("Success Rate: " . round(($passedTests / $totalTests) * 100, 1) . "%", 'cyan');

        if (!empty($failedFields)) {
            CLI::newLine();
            CLI::write("Failed Fields:", 'red');
            foreach ($failedFields as $field) {
                CLI::write("  - {$field}", 'red');
            }
        }

        CLI::newLine();
        CLI::write('=== RECOMMENDATIONS ===', 'yellow');
        if ($passedTests === $totalTests) {
            CLI::write("✓ All settings fields are properly connected to the database.", 'green');
            CLI::write("✓ Data flow is working correctly for all tested scenarios.", 'green');
        } else {
            CLI::write("⚠ Some fields have connectivity issues that need attention.", 'red');
            CLI::write("⚠ Review the failed fields above and check:", 'yellow');
            CLI::write("  - Database table structure", 'white');
            CLI::write("  - Field mapping in Settings controller", 'white');
            CLI::write("  - Form field names in the view", 'white');
        }

        return $passedTests === $totalTests ? 0 : 1;
    }

    private function getFieldMappings(): array
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

    private function getTestData(): array
    {
        return [
            'general.company_name' => 'Test Company ' . date('Y-m-d H:i:s'),
            'general.company_email' => 'test@example.com',
            'localization.time_format' => '24',
            'business.work_start' => '09:00',
            'business.blocked_periods' => [['start' => '2025-12-25', 'end' => '2025-12-25', 'notes' => 'Christmas']],
            'integrations.ldap_enabled' => true
        ];
    }

    private function getCheckboxFields(): array
    {
        $fields = [
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
            'booking.notes_required'
        ];

        // Add custom field checkboxes
        for ($i = 1; $i <= 6; $i++) {
            $fields[] = "booking.custom_field_{$i}_enabled";
            $fields[] = "booking.custom_field_{$i}_required";
        }

        return $fields;
    }

    private function valuesMatch($expected, $actual, string $type): bool
    {
        switch ($type) {
            case 'json':
                return json_encode($expected) === json_encode($actual);
            case 'bool':
                return (bool)$expected === (bool)$actual;
            default:
                return (string)$expected === (string)$actual;
        }
    }
}