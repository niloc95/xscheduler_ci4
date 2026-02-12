<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class AddDashboardIndexes extends MigrationBase
{
    /**
     * Add performance indexes for Dashboard Landing View
     * 
     * These indexes optimize queries for:
     * - Today's metrics (getTodayMetrics)
     * - Today's schedule (getTodaySchedule)
     * - Upcoming appointments (getUpcomingAppointments)
     * - Provider availability queries
     * 
     * Note: Table uses start_time/end_time, not appointment_date
     */
    public function up()
    {
        $table = 'appointments';

        // Guard: skip if appointments table doesn't exist yet
        if (!$this->db->tableExists($table)) {
            return;
        }

        // Index for provider + start_time + status queries (getTodayMetrics, getTodaySchedule)
        $this->createIndexIfMissing($table, 'idx_provider_start_status', ['provider_id', 'start_time', 'status']);

        // Index for start_time + end_time queries (schedule ordering, time-based filtering)
        $this->createIndexIfMissing($table, 'idx_start_end_time', ['start_time', 'end_time']);

        // Index for status + start_time queries (pending confirmations, status-based alerts)
        $this->createIndexIfMissing($table, 'idx_status_start', ['status', 'start_time']);

        // Note: start_time already has an index from the main table creation
    }

    public function down()
    {
        if (!$this->db->tableExists('appointments')) {
            return;
        }

        $this->dropIndexIfExists('appointments', 'idx_status_start');
        $this->dropIndexIfExists('appointments', 'idx_start_end_time');
        $this->dropIndexIfExists('appointments', 'idx_provider_start_status');
    }
}
