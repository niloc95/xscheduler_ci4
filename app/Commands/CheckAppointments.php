<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class CheckAppointments extends BaseCommand
{
    protected $group       = 'Database';
    protected $name        = 'app:check-appointments';
    protected $description = 'Check appointments data for Day/Week view troubleshooting';

    public function run(array $params)
    {
        $db = \Config\Database::connect();

        CLI::write(str_repeat("=", 80), 'yellow');
        CLI::write("APPOINTMENT DATABASE DIAGNOSTIC", 'yellow');
        CLI::write(str_repeat("=", 80), 'yellow');
        CLI::newLine();

        // Check if table exists
        if (!$db->tableExists('xs_appointments')) {
            CLI::error("❌ Table 'xs_appointments' does not exist!");
            return EXIT_ERROR;
        }

        CLI::write("✅ Table 'xs_appointments' exists", 'green');
        CLI::newLine();

        // Count total
        $totalQuery = $db->query("SELECT COUNT(*) as total FROM xs_appointments");
        $total = $totalQuery->getRow()->total;
        CLI::write("Total appointments: {$total}");
        CLI::newLine();

        if ($total == 0) {
            CLI::error("❌ No appointments found!");
            CLI::write("\nTo create test appointment, run:", 'yellow');
            CLI::write("INSERT INTO xs_appointments (provider_id, service_id, customer_id, user_id, start_time, end_time, status, created_at, updated_at)", 'cyan');
            CLI::write("VALUES (1, 1, 1, 1, DATE_ADD(NOW(), INTERVAL 1 HOUR), DATE_ADD(NOW(), INTERVAL 2 HOUR), 'confirmed', NOW(), NOW());", 'cyan');
            return EXIT_SUCCESS;
        }

        // Check data quality
        $nullTimes = $db->query("SELECT COUNT(*) as count FROM xs_appointments WHERE start_time IS NULL OR end_time IS NULL")->getRow()->count;
        $zeroTimes = $db->query("SELECT COUNT(*) as count FROM xs_appointments WHERE CAST(start_time AS CHAR) = '0000-00-00 00:00:00' OR CAST(end_time AS CHAR) = '0000-00-00 00:00:00'")->getRow()->count;
        $midnight = $db->query("SELECT COUNT(*) as count FROM xs_appointments WHERE start_time IS NOT NULL AND TIME(start_time) = '00:00:00' AND TIME(end_time) = '00:00:00'")->getRow()->count;

        CLI::write("Data Quality:");
        CLI::write(($nullTimes == 0 ? "✅" : "❌") . " NULL start/end times: {$nullTimes}", $nullTimes == 0 ? 'green' : 'red');
        CLI::write(($zeroTimes == 0 ? "✅" : "❌") . " Zero dates: {$zeroTimes}", $zeroTimes == 0 ? 'green' : 'red');
        CLI::write(($midnight == 0 ? "✅" : "⚠️ ") . " Midnight times: {$midnight}", $midnight == 0 ? 'green' : 'yellow');
        CLI::newLine();

        // Get recent appointments
        CLI::write("Recent Appointments:", 'yellow');
        CLI::write(str_repeat("-", 80), 'dark_gray');

        $query = $db->query("
            SELECT 
                id, provider_id, service_id, customer_id,
                start_time, end_time, status,
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
            CLI::write("⚠️  No appointments in last 7 days", 'yellow');
            CLI::write("Showing last 10 appointments (any date):");
            
            $query = $db->query("
                SELECT 
                    id, provider_id, service_id, start_time, end_time, status,
                    DATE(start_time) as appt_date,
                    TIME(start_time) as start_clock,
                    TIME(end_time) as end_clock
                FROM xs_appointments
                ORDER BY start_time DESC
                LIMIT 10
            ");
            $appointments = $query->getResultArray();
        }

        CLI::table($appointments, ['ID', 'Provider', 'Service', 'Date', 'Start Time', 'End Time', 'Status']);

        CLI::newLine();

        // Check visible range
        $today = date('Y-m-d');
        $nextWeek = date('Y-m-d', strtotime('+7 days'));
        
        $visibleQuery = $db->query("
            SELECT COUNT(*) as count 
            FROM xs_appointments 
            WHERE start_time BETWEEN '{$today} 00:00:00' AND '{$nextWeek} 23:59:59'
            AND TIME(start_time) != '00:00:00'
        ");
        $visibleCount = $visibleQuery->getRow()->count;

        CLI::write("Appointments visible in calendar (today to +7 days): {$visibleCount}", 'cyan');
        CLI::newLine();

        if ($visibleCount == 0) {
            CLI::write("⚠️  WARNING: No appointments will show in Day/Week views!", 'yellow');
            CLI::write("   Reason: No appointments scheduled for today to next 7 days", 'yellow');
            CLI::newLine();
            CLI::write("To create test appointment for today at 2 PM:", 'cyan');
            $sql = "INSERT INTO xs_appointments (provider_id, service_id, customer_id, user_id, start_time, end_time, status, created_at, updated_at) VALUES (1, 1, 1, 1, '" . date('Y-m-d') . " 14:00:00', '" . date('Y-m-d') . " 15:00:00', 'confirmed', NOW(), NOW());";
            CLI::write($sql, 'white');
        } else {
            CLI::write("✅ {$visibleCount} appointments should be visible in Day/Week views", 'green');
        }

        CLI::newLine();
        CLI::write(str_repeat("=", 80), 'yellow');
        CLI::write("Diagnostic complete!", 'green');
        CLI::write(str_repeat("=", 80), 'yellow');

        return EXIT_SUCCESS;
    }
}
