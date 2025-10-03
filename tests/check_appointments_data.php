<?php

/**
 * Check Appointments Database Data
 * Run: php tests/check_appointments_data.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Bootstrap CodeIgniter
$pathsConfig = require __DIR__ . '/../app/Config/Paths.php';
$paths = new \Config\Paths();
foreach ((array)$pathsConfig as $key => $value) {
    $paths->$key = $value;
}

define('APPPATH', realpath($paths->appDirectory) . DIRECTORY_SEPARATOR);
define('ROOTPATH', realpath(APPPATH . '../') . DIRECTORY_SEPARATOR);
define('FCPATH', realpath(ROOTPATH . 'public') . DIRECTORY_SEPARATOR);
define('WRITEPATH', realpath($paths->writableDirectory) . DIRECTORY_SEPARATOR);

// Load environment
require APPPATH . 'Config/Constants.php';

// Initialize
$app = \Config\Services::codeigniter();
$app->initialize();

// Get database
$db = \Config\Database::connect();

echo "=" . str_repeat("=", 79) . "\n";
echo "APPOINTMENT DATABASE DIAGNOSTIC\n";
echo "=" . str_repeat("=", 79) . "\n\n";

// Check if table exists
$tableExists = $db->tableExists('xs_appointments');
if (!$tableExists) {
    echo "❌ ERROR: Table 'xs_appointments' does not exist!\n";
    exit(1);
}

echo "✅ Table 'xs_appointments' exists\n\n";

// Count total appointments
$totalQuery = $db->query("SELECT COUNT(*) as total FROM xs_appointments");
$total = $totalQuery->getRow()->total;
echo "Total appointments: {$total}\n\n";

if ($total == 0) {
    echo "❌ No appointments found in database!\n";
    echo "\nTo create test appointment, run:\n";
    echo "INSERT INTO xs_appointments (provider_id, service_id, customer_id, user_id, start_time, end_time, status, created_at, updated_at)\n";
    echo "VALUES (1, 1, 1, 1, DATE_ADD(NOW(), INTERVAL 1 HOUR), DATE_ADD(NOW(), INTERVAL 2 HOUR), 'confirmed', NOW(), NOW());\n";
    exit(0);
}

// Check for NULL or invalid times
$nullTimesQuery = $db->query("
    SELECT COUNT(*) as count FROM xs_appointments 
    WHERE start_time IS NULL OR end_time IS NULL
");
$nullTimes = $nullTimesQuery->getRow()->count;

$zeroTimesQuery = $db->query("
    SELECT COUNT(*) as count FROM xs_appointments 
    WHERE start_time = '0000-00-00 00:00:00' OR end_time = '0000-00-00 00:00:00'
");
$zeroTimes = $zeroTimesQuery->getRow()->count;

$midnightQuery = $db->query("
    SELECT COUNT(*) as count FROM xs_appointments 
    WHERE TIME(start_time) = '00:00:00' AND TIME(end_time) = '00:00:00'
");
$midnight = $midnightQuery->getRow()->count;

echo "Data Quality:\n";
echo ($nullTimes == 0 ? "✅" : "❌") . " NULL start/end times: {$nullTimes}\n";
echo ($zeroTimes == 0 ? "✅" : "❌") . " Zero dates: {$zeroTimes}\n";
echo ($midnight == 0 ? "✅" : "⚠️") . " Midnight times (00:00:00): {$midnight}\n\n";

// Get recent appointments
echo "Recent Appointments:\n";
echo str_repeat("-", 80) . "\n";

$query = $db->query("
    SELECT 
        id,
        provider_id,
        service_id,
        customer_id,
        start_time,
        end_time,
        status,
        DATE(start_time) as appt_date,
        TIME(start_time) as start_clock,
        TIME(end_time) as end_clock
    FROM xs_appointments
    WHERE start_time >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ORDER BY start_time DESC
    LIMIT 10
");

$appointments = $query->getResultArray();

if (empty($appointments)) {
    echo "⚠️  No appointments in the last 7 days\n\n";
    echo "Showing last 10 appointments (any date):\n";
    echo str_repeat("-", 80) . "\n";
    
    $query = $db->query("
        SELECT 
            id,
            provider_id,
            service_id,
            customer_id,
            start_time,
            end_time,
            status,
            DATE(start_time) as appt_date,
            TIME(start_time) as start_clock,
            TIME(end_time) as end_clock
        FROM xs_appointments
        ORDER BY start_time DESC
        LIMIT 10
    ");
    $appointments = $query->getResultArray();
}

printf("%-4s | %-8s | %-7s | %-12s | %-8s | %-8s | %-10s\n",
    "ID", "Provider", "Service", "Date", "Start", "End", "Status"
);
echo str_repeat("-", 80) . "\n";

foreach ($appointments as $appt) {
    $hasTime = ($appt['start_clock'] !== '00:00:00' && $appt['end_clock'] !== '00:00:00');
    $marker = $hasTime ? "✅" : "❌";
    
    printf("%s%-3d | %-8d | %-7d | %-12s | %-8s | %-8s | %-10s\n",
        $marker,
        $appt['id'],
        $appt['provider_id'],
        $appt['service_id'],
        $appt['appt_date'],
        $appt['start_clock'],
        $appt['end_clock'],
        $appt['status']
    );
}

echo str_repeat("-", 80) . "\n\n";

// Check if any appointments are in the current view range
$today = date('Y-m-d');
$nextWeek = date('Y-m-d', strtotime('+7 days'));

$visibleQuery = $db->query("
    SELECT COUNT(*) as count 
    FROM xs_appointments 
    WHERE start_time BETWEEN '{$today} 00:00:00' AND '{$nextWeek} 23:59:59'
    AND TIME(start_time) != '00:00:00'
");
$visibleCount = $visibleQuery->getRow()->count;

echo "Appointments visible in calendar (today to +7 days with valid times): {$visibleCount}\n\n";

if ($visibleCount == 0) {
    echo "⚠️  WARNING: No appointments will show in Day/Week views!\n";
    echo "   Reason: No appointments scheduled for today to next 7 days with non-zero times\n\n";
    echo "To create test appointment for today at 2 PM:\n";
    echo "INSERT INTO xs_appointments (provider_id, service_id, customer_id, user_id, start_time, end_time, status, created_at, updated_at)\n";
    echo "VALUES (1, 1, 1, 1, '" . date('Y-m-d') . " 14:00:00', '" . date('Y-m-d') . " 15:00:00', 'confirmed', NOW(), NOW());\n";
} else {
    echo "✅ {$visibleCount} appointments should be visible in Day/Week views\n";
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "Diagnostic complete!\n";
echo str_repeat("=", 80) . "\n";
