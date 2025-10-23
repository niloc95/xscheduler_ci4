<?php
/**
 * Calendar Visibility Test Script
 * Tests whether calendar UI is rendering properly
 */

// Check if appointments view is accessible
$appointments_view = file_exists(__DIR__ . '/app/Views/appointments/index.php');
echo "[TEST 1] Appointments view file exists: " . ($appointments_view ? "✓ YES" : "✗ NO") . "\n";

// Check if calendar JS module exists
$calendar_js = file_exists(__DIR__ . '/resources/js/modules/appointments/appointments-calendar.js');
echo "[TEST 2] Calendar JS module exists: " . ($calendar_js ? "✓ YES" : "✗ NO") . "\n";

// Check if FullCalendar CSS exists
$fullcalendar_css = file_exists(__DIR__ . '/resources/css/fullcalendar-overrides.css');
echo "[TEST 3] FullCalendar CSS overrides exist: " . ($fullcalendar_css ? "✓ YES" : "✗ NO") . "\n";

// Check if build assets exist
$build_assets = file_exists(__DIR__ . '/public/build/assets/main.js');
echo "[TEST 4] Build assets compiled: " . ($build_assets ? "✓ YES" : "✗ NO") . "\n";

// Check if app.js properly imports calendar functions
$app_js = file_get_contents(__DIR__ . '/resources/js/app.js');
$has_import = strpos($app_js, "import { initAppointmentsCalendar") !== false;
$has_init = strpos($app_js, "initializeCalendar") !== false;
echo "[TEST 5] app.js imports calendar module: " . ($has_import ? "✓ YES" : "✗ NO") . "\n";
echo "[TEST 6] app.js has initializeCalendar call: " . ($has_init ? "✓ YES" : "✗ NO") . "\n";

// Check if view contains calendar container
$index_view = file_get_contents(__DIR__ . '/app/Views/appointments/index.php');
$has_container = strpos($index_view, 'id="appointments-inline-calendar"') !== false;
$has_toolbar = strpos($index_view, 'data-calendar-toolbar') !== false;
echo "[TEST 7] View has calendar container: " . ($has_container ? "✓ YES" : "✗ NO") . "\n";
echo "[TEST 8] View has calendar toolbar: " . ($has_toolbar ? "✓ YES" : "✗ NO") . "\n";

// Check if container is NOT hidden
$has_display_none = strpos($index_view, 'appointments-inline-calendar" class="hidden') !== false;
$has_visibility_hidden = strpos($index_view, 'appointments-inline-calendar" style="display: none') !== false;
echo "[TEST 9] Calendar NOT hidden in view: " . ((!$has_display_none && !$has_visibility_hidden) ? "✓ YES" : "✗ NO - FOUND HIDDEN CLASS") . "\n";

// Check view buttons setup
$has_view_buttons = strpos($index_view, 'data-calendar-action') !== false;
echo "[TEST 10] View buttons exist in markup: " . ($has_view_buttons ? "✓ YES" : "✗ NO") . "\n";

echo "\n=== AUDIT COMPLETE ===\n";
?>
