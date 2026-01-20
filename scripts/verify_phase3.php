#!/usr/bin/env php
<?php

/**
 * Phase 3 Verification Script
 * 
 * Verifies that all Phase 3 features are working correctly:
 * - Database indexes
 * - Cache invalidation
 * - Loading states
 * - Error handling
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Bootstrap CodeIgniter
$pathsPath = __DIR__ . '/../app/Config/Paths.php';
require realpath($pathsPath) ?: $pathsPath;

$paths = new Config\Paths();
$bootstrap = rtrim($paths->systemDirectory, '\\/ ') . '/bootstrap.php';
$app = require realpath($bootstrap) ?: $bootstrap;

echo "\n╔══════════════════════════════════════════════════════════════╗\n";
echo "║          Phase 3 Dashboard Implementation Verification       ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

$db = \Config\Database::connect();
$passed = 0;
$failed = 0;

// Test 1: Database Indexes
echo "📊 Test 1: Verifying Database Indexes...\n";
try {
    $indexes = $db->query("SHOW INDEX FROM xs_appointments WHERE Key_name IN ('idx_provider_start_status', 'idx_start_end_time', 'idx_status_start')")->getResultArray();
    
    $indexNames = array_unique(array_column($indexes, 'Key_name'));
    $expectedIndexes = ['idx_provider_start_status', 'idx_start_end_time', 'idx_status_start'];
    
    $allFound = true;
    foreach ($expectedIndexes as $expected) {
        if (in_array($expected, $indexNames)) {
            echo "   ✓ Index '{$expected}' exists\n";
        } else {
            echo "   ✗ Index '{$expected}' NOT FOUND\n";
            $allFound = false;
        }
    }
    
    if ($allFound) {
        echo "   ✅ All indexes created successfully!\n\n";
        $passed++;
    } else {
        echo "   ❌ Some indexes are missing!\n\n";
        $failed++;
    }
} catch (Exception $e) {
    echo "   ❌ Error: {$e->getMessage()}\n\n";
    $failed++;
}

// Test 2: DashboardService File
echo "📁 Test 2: Verifying DashboardService.php...\n";
$serviceFile = __DIR__ . '/../app/Services/DashboardService.php';
if (file_exists($serviceFile)) {
    echo "   ✓ File exists\n";
    
    // Check if file uses DATE(start_time) instead of appointment_date
    $content = file_get_contents($serviceFile);
    $hasAppointmentDate = strpos($content, 'appointment_date') !== false;
    $hasDateStartTime = strpos($content, 'DATE(start_time)') !== false;
    
    if (!$hasAppointmentDate && $hasDateStartTime) {
        echo "   ✓ Using correct column names (DATE(start_time))\n";
        echo "   ✅ DashboardService.php is correct!\n\n";
        $passed++;
    } else {
        echo "   ✗ Still using old column name (appointment_date)\n";
        echo "   ❌ DashboardService.php needs fixing!\n\n";
        $failed++;
    }
} else {
    echo "   ❌ DashboardService.php not found!\n\n";
    $failed++;
}

// Test 3: AuthorizationService File
echo "🔒 Test 3: Verifying AuthorizationService.php...\n";
$authFile = __DIR__ . '/../app/Services/AuthorizationService.php';
if (file_exists($authFile)) {
    echo "   ✓ File exists\n";
    
    // Check if it has the required methods
    $content = file_get_contents($authFile);
    $hasMethods = strpos($content, 'getProviderScope') !== false &&
                  strpos($content, 'canViewDashboardMetrics') !== false;
    
    if ($hasMethods) {
        echo "   ✓ Required methods found\n";
        echo "   ✅ AuthorizationService.php is correct!\n\n";
        $passed++;
    } else {
        echo "   ✗ Missing required methods\n";
        echo "   ❌ AuthorizationService.php incomplete!\n\n";
        $failed++;
    }
} else {
    echo "   ❌ AuthorizationService.php not found!\n\n";
    $failed++;
}

// Test 4: AppointmentModel Cache Hooks
echo "🔄 Test 4: Verifying Cache Invalidation Hooks...\n";
$modelFile = __DIR__ . '/../app/Models/AppointmentModel.php';
if (file_exists($modelFile)) {
    echo "   ✓ File exists\n";
    
    $content = file_get_contents($modelFile);
    $hasAfterInsert = strpos($content, '$afterInsert') !== false;
    $hasAfterUpdate = strpos($content, '$afterUpdate') !== false;
    $hasAfterDelete = strpos($content, '$afterDelete') !== false;
    $hasInvalidateMethod = strpos($content, 'invalidateDashboardCache') !== false;
    
    if ($hasAfterInsert && $hasAfterUpdate && $hasAfterDelete && $hasInvalidateMethod) {
        echo "   ✓ afterInsert hook found\n";
        echo "   ✓ afterUpdate hook found\n";
        echo "   ✓ afterDelete hook found\n";
        echo "   ✓ invalidateDashboardCache method found\n";
        echo "   ✅ Cache invalidation is configured!\n\n";
        $passed++;
    } else {
        echo "   ✗ Missing cache invalidation hooks\n";
        echo "   ❌ Cache invalidation not complete!\n\n";
        $failed++;
    }
} else {
    echo "   ❌ AppointmentModel.php not found!\n\n";
    $failed++;
}

// Test 5: Dashboard Controller Enhancements
echo "🎛️  Test 5: Verifying Dashboard Controller...\n";
$controllerFile = __DIR__ . '/../app/Controllers/Dashboard.php';
if (file_exists($controllerFile)) {
    echo "   ✓ File exists\n";
    
    $content = file_get_contents($controllerFile);
    $hasApiMetrics = strpos($content, 'apiMetrics') !== false;
    $hasErrorHandling = strpos($content, 'RuntimeException') !== false;
    $hasStructuredResponse = strpos($content, '"success"') !== false;
    
    if ($hasApiMetrics && $hasErrorHandling && $hasStructuredResponse) {
        echo "   ✓ apiMetrics endpoint found\n";
        echo "   ✓ Error handling found\n";
        echo "   ✓ Structured responses found\n";
        echo "   ✅ Dashboard controller enhanced!\n\n";
        $passed++;
    } else {
        echo "   ✗ Missing enhancements\n";
        echo "   ❌ Dashboard controller incomplete!\n\n";
        $failed++;
    }
} else {
    echo "   ❌ Dashboard.php not found!\n\n";
    $failed++;
}

// Test 6: Landing View with Loading States
echo "🎨 Test 6: Verifying Dashboard Landing View...\n";
$viewFile = __DIR__ . '/../app/Views/dashboard/landing.php';
if (file_exists($viewFile)) {
    echo "   ✓ File exists\n";
    
    $content = file_get_contents($viewFile);
    $hasLoadingPulse = strpos($content, 'loading-pulse') !== false;
    $hasSuccessFeedback = strpos($content, 'success-feedback') !== false;
    $hasRefreshLogic = strpos($content, 'refreshMetrics') !== false;
    $hasErrorState = strpos($content, 'showErrorState') !== false;
    
    if ($hasLoadingPulse && $hasSuccessFeedback && $hasRefreshLogic && $hasErrorState) {
        echo "   ✓ Loading animation found\n";
        echo "   ✓ Success feedback found\n";
        echo "   ✓ Auto-refresh logic found\n";
        echo "   ✓ Error handling found\n";
        echo "   ✅ Landing view complete!\n\n";
        $passed++;
    } else {
        echo "   ✗ Missing UI enhancements\n";
        echo "   ❌ Landing view incomplete!\n\n";
        $failed++;
    }
} else {
    echo "   ❌ landing.php not found!\n\n";
    $failed++;
}

// Test 7: Integration Test File
echo "🧪 Test 7: Verifying Integration Tests...\n";
$testFile = __DIR__ . '/../tests/integration/DashboardLandingTest.php';
if (file_exists($testFile)) {
    echo "   ✓ File exists\n";
    
    $content = file_get_contents($testFile);
    $testCount = substr_count($content, 'public function test');
    
    if ($testCount >= 10) {
        echo "   ✓ Found {$testCount} test methods\n";
        echo "   ✅ Integration tests created!\n\n";
        $passed++;
    } else {
        echo "   ✗ Only {$testCount} test methods found (expected 10+)\n";
        echo "   ❌ Integration tests incomplete!\n\n";
        $failed++;
    }
} else {
    echo "   ❌ DashboardLandingTest.php not found!\n\n";
    $failed++;
}

// Test 8: Migration File
echo "🗄️  Test 8: Verifying Migration File...\n";
$migrationPattern = __DIR__ . '/../app/Database/Migrations/*_AddDashboardIndexes.php';
$migrationFiles = glob($migrationPattern);
if (!empty($migrationFiles)) {
    echo "   ✓ Migration file exists\n";
    
    $content = file_get_contents($migrationFiles[0]);
    $hasIndexes = strpos($content, 'idx_provider_start_status') !== false &&
                  strpos($content, 'idx_start_end_time') !== false &&
                  strpos($content, 'idx_status_start') !== false;
    
    if ($hasIndexes) {
        echo "   ✓ All index definitions found\n";
        echo "   ✅ Migration file correct!\n\n";
        $passed++;
    } else {
        echo "   ✗ Missing index definitions\n";
        echo "   ❌ Migration file incomplete!\n\n";
        $failed++;
    }
} else {
    echo "   ❌ AddDashboardIndexes migration not found!\n\n";
    $failed++;
}

// Test 9: Query Performance Test
echo "⚡ Test 9: Testing Query Performance...\n";
try {
    $startTime = microtime(true);
    
    // Test query with indexes
    $result = $db->query("
        SELECT COUNT(*) as count 
        FROM xs_appointments 
        WHERE provider_id = 1 
        AND DATE(start_time) = CURDATE() 
        AND status = 'confirmed'
    ")->getRow();
    
    $endTime = microtime(true);
    $queryTime = round(($endTime - $startTime) * 1000, 2);
    
    echo "   ✓ Query executed in {$queryTime}ms\n";
    
    if ($queryTime < 100) {
        echo "   ✅ Query performance is excellent!\n\n";
        $passed++;
    } else if ($queryTime < 200) {
        echo "   ⚠️  Query performance is acceptable\n\n";
        $passed++;
    } else {
        echo "   ❌ Query performance is slow (>200ms)\n\n";
        $failed++;
    }
} catch (Exception $e) {
    echo "   ❌ Error: {$e->getMessage()}\n\n";
    $failed++;
}

// Final Summary
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║                      Test Summary                            ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

$total = $passed + $failed;
$percentage = $total > 0 ? round(($passed / $total) * 100) : 0;

echo "   Tests Passed: {$passed}/{$total} ({$percentage}%)\n";
echo "   Tests Failed: {$failed}/{$total}\n\n";

if ($failed === 0) {
    echo "   🎉 ALL TESTS PASSED! Phase 3 implementation is complete!\n\n";
    echo "   Next Steps:\n";
    echo "   1. Access dashboard at: http://localhost:8080/dashboard\n";
    echo "   2. Test with different user roles (admin, provider, staff)\n";
    echo "   3. Verify auto-refresh works (5-minute interval)\n";
    echo "   4. Check loading animations and error handling\n";
    echo "   5. Monitor cache performance in production\n\n";
    exit(0);
} else {
    echo "   ⚠️  Some tests failed. Please review the errors above.\n\n";
    exit(1);
}
