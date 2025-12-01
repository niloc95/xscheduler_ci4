<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class FixAppointmentTimezoneOffsets extends Migration
{
    /**
     * Adjust legacy appointment timestamps that were saved with UTC when local time was intended.
     * We subtract 2 hours from start_time and end_time for rows created before the timezone fix.
     *
     * Cutoff chosen based on when the timezone fix was deployed (server logs ~15:35 SAST on 2025-11-16).
     */
    public function up()
    {
        $db = \Config\Database::connect();
        // SAST cutoff when the app timezone was corrected
        $cutoff = '2025-11-16 15:50:00';

        // Preview: count affected rows
        $preview = $db->query(
            "SELECT COUNT(*) AS cnt FROM xs_appointments WHERE created_at < ?",
            [$cutoff]
        )->getRowArray();

        log_message('notice', '[Migration] FixAppointmentTimezoneOffsets up(): about to adjust {cnt} rows (created before ' . $cutoff . ')', $preview ?: []);

        // Apply -2 hour adjustment to legacy rows
        $db->transStart();
        $db->query(
            "UPDATE xs_appointments 
             SET start_time = DATE_SUB(start_time, INTERVAL 2 HOUR),
                 end_time   = DATE_SUB(end_time,   INTERVAL 2 HOUR)
             WHERE created_at < ?",
            [$cutoff]
        );
        $db->transComplete();

        if (!$db->transStatus()) {
            throw new \RuntimeException('Failed to apply timezone offset fix to appointments');
        }

        $affected = $db->affectedRows();
        log_message('notice', '[Migration] FixAppointmentTimezoneOffsets up(): adjusted rows = ' . $affected);
    }

    /**
     * Rollback: add 2 hours back to the same legacy set (created before cutoff).
     */
    public function down()
    {
        $db = \Config\Database::connect();
        $cutoff = '2025-11-16 15:50:00';

        $db->transStart();
        $db->query(
            "UPDATE xs_appointments 
             SET start_time = DATE_ADD(start_time, INTERVAL 2 HOUR),
                 end_time   = DATE_ADD(end_time,   INTERVAL 2 HOUR)
             WHERE created_at < ?",
            [$cutoff]
        );
        $db->transComplete();

        if (!$db->transStatus()) {
            throw new \RuntimeException('Failed to rollback timezone offset fix for appointments');
        }

        $affected = $db->affectedRows();
        log_message('notice', '[Migration] FixAppointmentTimezoneOffsets down(): reverted rows = ' . $affected);
    }
}
