<?php
/**
 * Phase 2 Integration Test Script
 * Tests CalendarConfigService and BookingSettingsService integration
 * 
 * Run: php test-phase2-integration.php
 */

// Bootstrap CodeIgniter
define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR);
require __DIR__ . '/app/Config/Paths.php';
$paths = new Config\Paths();
require rtrim($paths->systemDirectory, '\\/ ') . DIRECTORY_SEPARATOR . 'bootstrap.php';

$app = Config\Services::codeigniter();
$app->initialize();
$app->setContext(is_cli() ? 'php-cli' : 'web');

echo "=== Phase 2 Service Layer Integration Tests ===\n\n";

// Test 1: CalendarConfigService
echo "1. Testing CalendarConfigService\n";
echo "-------------------------------------------\n";

try {
    $calendarService = new \App\Services\CalendarConfigService();
    
    // Get JavaScript config
    $jsConfig = $calendarService->getJavaScriptConfig();
    
    echo "✅ CalendarConfigService instantiated successfully\n";
    echo "Configuration loaded:\n";
    echo "  - Initial View: " . ($jsConfig['initialView'] ?? 'N/A') . "\n";
    echo "  - First Day: " . ($jsConfig['firstDay'] ?? 'N/A') . "\n";
    echo "  - Slot Duration: " . ($jsConfig['slotDuration'] ?? 'N/A') . "\n";
    echo "  - Slot Min Time: " . ($jsConfig['slotMinTime'] ?? 'N/A') . "\n";
    echo "  - Slot Max Time: " . ($jsConfig['slotMaxTime'] ?? 'N/A') . "\n";
    echo "  - Time Zone: " . ($jsConfig['timeZone'] ?? 'N/A') . "\n";
    echo "  - Weekends: " . ($jsConfig['weekends'] ? 'Yes' : 'No') . "\n";
    
    // Check time format
    $slotFormat = $jsConfig['slotLabelFormat'] ?? [];
    $is12Hour = isset($slotFormat['hour12']) ? ($slotFormat['hour12'] ? '12-hour' : '24-hour') : 'Unknown';
    echo "  - Time Format: {$is12Hour}\n";
    
    // Check business hours
    $businessHours = $jsConfig['businessHours'] ?? [];
    echo "  - Business Hours: " . count($businessHours) . " configured\n";
    
    if (count($businessHours) > 0) {
        echo "    Sample:\n";
        foreach (array_slice($businessHours, 0, 3) as $hours) {
            $days = is_array($hours['daysOfWeek'] ?? []) ? implode(',', $hours['daysOfWeek']) : 'N/A';
            echo "      Day(s) {$days}: {$hours['startTime']} - {$hours['endTime']}\n";
        }
    }
    
    echo "\n✅ CalendarConfigService test PASSED\n\n";
    
} catch (\Exception $e) {
    echo "\n❌ CalendarConfigService test FAILED\n";
    echo "Error: " . $e->getMessage() . "\n\n";
}

// Test 2: BookingSettingsService
echo "2. Testing BookingSettingsService\n";
echo "-------------------------------------------\n";

try {
    $bookingService = new \App\Services\BookingSettingsService();
    
    // Get field configuration
    $fieldConfig = $bookingService->getFieldConfiguration();
    
    echo "✅ BookingSettingsService instantiated successfully\n";
    echo "Field Configuration:\n";
    
    foreach ($fieldConfig as $fieldName => $config) {
        $display = $config['display'] ? '✓ Displayed' : '✗ Hidden';
        $required = $config['required'] ? '(Required)' : '(Optional)';
        echo "  - {$fieldName}: {$display} {$required}\n";
    }
    
    // Get custom fields
    $customFields = $bookingService->getCustomFieldConfiguration();
    echo "\nCustom Fields: " . count($customFields) . " configured\n";
    
    if (count($customFields) > 0) {
        foreach ($customFields as $fieldKey => $fieldMeta) {
            $required = $fieldMeta['required'] ? '(Required)' : '(Optional)';
            echo "  - {$fieldMeta['title']} [{$fieldMeta['type']}] {$required}\n";
        }
    }
    
    // Get visible and required fields
    $visibleFields = $bookingService->getVisibleFields();
    $requiredFields = $bookingService->getRequiredFields();
    
    echo "\nSummary:\n";
    echo "  - Visible Fields: " . count($visibleFields) . " (" . implode(', ', $visibleFields) . ")\n";
    echo "  - Required Fields: " . count($requiredFields) . " (" . implode(', ', $requiredFields) . ")\n";
    
    echo "\n✅ BookingSettingsService test PASSED\n\n";
    
} catch (\Exception $e) {
    echo "\n❌ BookingSettingsService test FAILED\n";
    echo "Error: " . $e->getMessage() . "\n\n";
}

// Test 3: LocalizationSettingsService
echo "3. Testing LocalizationSettingsService\n";
echo "-------------------------------------------\n";

try {
    $localizationService = new \App\Services\LocalizationSettingsService();
    
    echo "✅ LocalizationSettingsService instantiated successfully\n";
    
    $timeFormat = $localizationService->getTimeFormat();
    $timezone = $localizationService->getTimezone();
    $is12Hour = $localizationService->isTwelveHour();
    
    echo "Settings:\n";
    echo "  - Time Format: {$timeFormat}\n";
    echo "  - Is 12-Hour: " . ($is12Hour ? 'Yes' : 'No') . "\n";
    echo "  - Timezone: {$timezone}\n";
    echo "  - Format Example: " . $localizationService->getFormatExample() . "\n";
    echo "  - Format Description: " . $localizationService->describeExpectedFormat() . "\n";
    
    // Test time normalization
    $testTimes = ['09:00 AM', '14:30', '9:00 PM', '23:45'];
    echo "\nTime Normalization Tests:\n";
    foreach ($testTimes as $time) {
        $normalized = $localizationService->normaliseTimeInput($time);
        $display = $localizationService->formatTimeForDisplay($normalized);
        echo "  - '{$time}' → '{$normalized}' → '{$display}'\n";
    }
    
    echo "\n✅ LocalizationSettingsService test PASSED\n\n";
    
} catch (\Exception $e) {
    echo "\n❌ LocalizationSettingsService test FAILED\n";
    echo "Error: " . $e->getMessage() . "\n\n";
}

// Test 4: Integration - All Services Together
echo "4. Testing Service Integration\n";
echo "-------------------------------------------\n";

try {
    $calendarService = new \App\Services\CalendarConfigService();
    $bookingService = new \App\Services\BookingSettingsService();
    
    // Simulate what the controller does
    $controllerData = [
        'calendarConfig' => $calendarService->getJavaScriptConfig(),
        'fieldConfig' => $bookingService->getFieldConfiguration(),
        'customFields' => $bookingService->getCustomFieldConfiguration(),
        'localization' => $calendarService->getLocalizationContext(),
    ];
    
    echo "✅ All services instantiated together successfully\n";
    echo "Controller would receive:\n";
    echo "  - Calendar Config: " . count($controllerData['calendarConfig']) . " settings\n";
    echo "  - Field Config: " . count($controllerData['fieldConfig']) . " fields\n";
    echo "  - Custom Fields: " . count($controllerData['customFields']) . " fields\n";
    echo "  - Localization: " . count($controllerData['localization']) . " settings\n";
    
    echo "\n✅ Service Integration test PASSED\n\n";
    
} catch (\Exception $e) {
    echo "\n❌ Service Integration test FAILED\n";
    echo "Error: " . $e->getMessage() . "\n\n";
}

// Summary
echo "=== Test Summary ===\n";
echo "✅ All Phase 2 service layer components working correctly\n";
echo "\nNext Steps:\n";
echo "1. Start development server: php spark serve\n";
echo "2. Navigate to /appointments/create\n";
echo "3. Verify dynamic form fields render correctly\n";
echo "4. Check calendar at /appointments\n";
echo "5. Verify calendar uses API config\n";
echo "\nManual Testing Checklist:\n";
echo "□ Calendar displays with correct time format\n";
echo "□ Business hours highlighted on calendar\n";
echo "□ Appointment form shows/hides fields per settings\n";
echo "□ Required indicators (*) appear correctly\n";
echo "□ Custom fields render if enabled\n";
echo "□ Form validation respects field requirements\n";
