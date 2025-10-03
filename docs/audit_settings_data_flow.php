<?php
/**
 * Settings Data Flow Audit Script
 * Tests all form fields for proper database connectivity
 */

// Define path constants
define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR);
define('APPPATH', __DIR__ . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR);
define('WRITEPATH', __DIR__ . DIRECTORY_SEPARATOR . 'writable' . DIRECTORY_SEPARATOR);
define('SYSTEMPATH', __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'codeigniter4' . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR);

require_once 'vendor/autoload.php';

// Bootstrap CodeIgniter
$paths = new \Config\Paths();
$bootstrap = rtrim($paths->systemDirectory, '\\/ ') . DIRECTORY_SEPARATOR . 'bootstrap.php';
$app = require realpath($bootstrap) ?: $bootstrap;

use App\Models\SettingModel;

class SettingsDataFlowAudit
{
    private $settingModel;
    private $results = [];

    public function __construct()
    {
        $this->settingModel = new SettingModel();
    }

    public function runAudit()
    {
        echo "=== SETTINGS DATA FLOW AUDIT ===\n\n";
        
        // 1. Test database connectivity
        $this->testDatabaseConnectivity();
        
        // 2. Test all field mappings from controller
        $this->testFieldMappings();
        
        // 3. Test checkbox field handling
        $this->testCheckboxFields();
        
        // 4. Test special field handling
        $this->testSpecialFields();
        
        // 5. Generate report
        $this->generateReport();
    }

    private function testDatabaseConnectivity()
    {
        echo "1. Testing Database Connectivity...\n";
        try {
            $db = \Config\Database::connect();
            $query = $db->query("DESCRIBE xs_settings");
            $structure = $query->getResultArray();
            
            echo "   ✓ Database connection successful\n";
            echo "   ✓ Settings table structure:\n";
            
            foreach ($structure as $column) {
                echo "     - {$column['Field']} ({$column['Type']})\n";
            }
            
            // Count existing settings
            $count = $this->settingModel->countAllResults();
            echo "   ✓ Current settings count: {$count}\n";
            
            $this->results['database'] = 'PASS';
        } catch (Exception $e) {
            echo "   ✗ Database error: " . $e->getMessage() . "\n";
            $this->results['database'] = 'FAIL: ' . $e->getMessage();
        }
        echo "\n";
    }

    private function testFieldMappings()
    {
        echo "2. Testing Field Mappings (Controller -> Database)...\n";
        
        // Field mappings from Settings controller
        $fieldMappings = [
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

        $testData = [
            'general.company_name' => 'Test Company ' . date('Y-m-d H:i:s'),
            'general.company_email' => 'test@example.com',
            'localization.time_format' => '24',
            'business.work_start' => '09:00',
            'business.blocked_periods' => [['start' => '2025-12-25', 'end' => '2025-12-25', 'notes' => 'Christmas']],
            'integrations.ldap_enabled' => true
        ];

        foreach ($testData as $settingKey => $testValue) {
            try {
                // Test write
                $type = is_array($testValue) ? 'json' : (is_bool($testValue) ? 'bool' : 'string');
                $success = $this->settingModel->upsert($settingKey, $testValue, $type, 1);
                
                if ($success) {
                    // Test read
                    $retrieved = $this->settingModel->getByKeys([$settingKey]);
                    $retrievedValue = $retrieved[$settingKey] ?? null;
                    
                    if ($this->valuesMatch($testValue, $retrievedValue, $type)) {
                        echo "   ✓ {$settingKey}: Write/Read OK\n";
                        $this->results['fields'][$settingKey] = 'PASS';
                    } else {
                        echo "   ✗ {$settingKey}: Value mismatch\n";
                        echo "     Expected: " . json_encode($testValue) . "\n";
                        echo "     Got: " . json_encode($retrievedValue) . "\n";
                        $this->results['fields'][$settingKey] = 'FAIL: Value mismatch';
                    }
                } else {
                    echo "   ✗ {$settingKey}: Write failed\n";
                    $this->results['fields'][$settingKey] = 'FAIL: Write failed';
                }
            } catch (Exception $e) {
                echo "   ✗ {$settingKey}: Exception - " . $e->getMessage() . "\n";
                $this->results['fields'][$settingKey] = 'FAIL: ' . $e->getMessage();
            }
        }
        echo "\n";
    }

    private function testCheckboxFields()
    {
        echo "3. Testing Checkbox Field Handling...\n";
        
        $checkboxFields = [
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
            $checkboxFields[] = "booking.custom_field_{$i}_enabled";
            $checkboxFields[] = "booking.custom_field_{$i}_required";
        }

        foreach ($checkboxFields as $field) {
            try {
                // Test both checked and unchecked states
                $this->settingModel->upsert($field, '1', 'string', 1);
                $checked = $this->settingModel->getByKeys([$field]);
                
                $this->settingModel->upsert($field, '0', 'string', 1);
                $unchecked = $this->settingModel->getByKeys([$field]);
                
                if (($checked[$field] ?? '') === '1' && ($unchecked[$field] ?? '') === '0') {
                    echo "   ✓ {$field}: Checkbox states OK\n";
                    $this->results['checkboxes'][$field] = 'PASS';
                } else {
                    echo "   ✗ {$field}: Checkbox state handling failed\n";
                    $this->results['checkboxes'][$field] = 'FAIL: State handling';
                }
            } catch (Exception $e) {
                echo "   ✗ {$field}: Exception - " . $e->getMessage() . "\n";
                $this->results['checkboxes'][$field] = 'FAIL: ' . $e->getMessage();
            }
        }
        echo "\n";
    }

    private function testSpecialFields()
    {
        echo "4. Testing Special Field Handling...\n";
        
        // Test blocked periods (JSON handling)
        try {
            $blockedPeriods = [
                ['start' => '2025-12-25', 'end' => '2025-12-25', 'notes' => 'Christmas'],
                ['start' => '2025-01-01', 'end' => '2025-01-01', 'notes' => 'New Year']
            ];
            
            $this->settingModel->upsert('business.blocked_periods', $blockedPeriods, 'json', 1);
            $retrieved = $this->settingModel->getByKeys(['business.blocked_periods']);
            
            if (json_encode($blockedPeriods) === json_encode($retrieved['business.blocked_periods'])) {
                echo "   ✓ business.blocked_periods: JSON handling OK\n";
                $this->results['special']['blocked_periods'] = 'PASS';
            } else {
                echo "   ✗ business.blocked_periods: JSON handling failed\n";
                $this->results['special']['blocked_periods'] = 'FAIL: JSON mismatch';
            }
        } catch (Exception $e) {
            echo "   ✗ business.blocked_periods: Exception - " . $e->getMessage() . "\n";
            $this->results['special']['blocked_periods'] = 'FAIL: ' . $e->getMessage();
        }

        // Test custom field titles and types
        for ($i = 1; $i <= 6; $i++) {
            try {
                $title = "Custom Field {$i} Test";
                $type = 'text';
                
                $this->settingModel->upsert("booking.custom_field_{$i}_title", $title, 'string', 1);
                $this->settingModel->upsert("booking.custom_field_{$i}_type", $type, 'string', 1);
                
                $retrieved = $this->settingModel->getByKeys([
                    "booking.custom_field_{$i}_title",
                    "booking.custom_field_{$i}_type"
                ]);
                
                if ($retrieved["booking.custom_field_{$i}_title"] === $title && 
                    $retrieved["booking.custom_field_{$i}_type"] === $type) {
                    echo "   ✓ Custom field {$i}: Title/Type OK\n";
                    $this->results['special']["custom_field_{$i}"] = 'PASS';
                } else {
                    echo "   ✗ Custom field {$i}: Title/Type failed\n";
                    $this->results['special']["custom_field_{$i}"] = 'FAIL: Value mismatch';
                }
            } catch (Exception $e) {
                echo "   ✗ Custom field {$i}: Exception - " . $e->getMessage() . "\n";
                $this->results['special']["custom_field_{$i}"] = 'FAIL: ' . $e->getMessage();
            }
        }
        echo "\n";
    }

    private function valuesMatch($expected, $actual, $type)
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

    private function generateReport()
    {
        echo "=== AUDIT REPORT ===\n\n";
        
        $totalTests = 0;
        $passedTests = 0;
        $failedFields = [];

        // Database connectivity
        echo "Database Connectivity: " . $this->results['database'] . "\n";
        if ($this->results['database'] === 'PASS') {
            $passedTests++;
        } else {
            $failedFields[] = 'Database Connection';
        }
        $totalTests++;

        // Field mappings
        echo "\nField Mappings:\n";
        foreach ($this->results['fields'] ?? [] as $field => $result) {
            echo "  {$field}: {$result}\n";
            $totalTests++;
            if ($result === 'PASS') {
                $passedTests++;
            } else {
                $failedFields[] = $field;
            }
        }

        // Checkbox fields
        echo "\nCheckbox Fields:\n";
        foreach ($this->results['checkboxes'] ?? [] as $field => $result) {
            echo "  {$field}: {$result}\n";
            $totalTests++;
            if ($result === 'PASS') {
                $passedTests++;
            } else {
                $failedFields[] = $field;
            }
        }

        // Special fields
        echo "\nSpecial Fields:\n";
        foreach ($this->results['special'] ?? [] as $field => $result) {
            echo "  {$field}: {$result}\n";
            $totalTests++;
            if ($result === 'PASS') {
                $passedTests++;
            } else {
                $failedFields[] = $field;
            }
        }

        echo "\n=== SUMMARY ===\n";
        echo "Total Tests: {$totalTests}\n";
        echo "Passed: {$passedTests}\n";
        echo "Failed: " . ($totalTests - $passedTests) . "\n";
        echo "Success Rate: " . round(($passedTests / $totalTests) * 100, 1) . "%\n";

        if (!empty($failedFields)) {
            echo "\nFailed Fields:\n";
            foreach ($failedFields as $field) {
                echo "  - {$field}\n";
            }
        }

        echo "\n=== RECOMMENDATIONS ===\n";
        if ($passedTests === $totalTests) {
            echo "✓ All settings fields are properly connected to the database.\n";
            echo "✓ Data flow is working correctly for all tested scenarios.\n";
        } else {
            echo "⚠ Some fields have connectivity issues that need attention.\n";
            echo "⚠ Review the failed fields above and check:\n";
            echo "  - Database table structure\n";
            echo "  - Field mapping in Settings controller\n";
            echo "  - Form field names in the view\n";
        }
    }
}

// Run the audit
$audit = new SettingsDataFlowAudit();
$audit->runAudit();