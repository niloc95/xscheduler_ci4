<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

/**
 * Migration: Add Calendar Performance Indexes
 * 
 * P0-3 Calendar Performance Optimization
 * 
 * Adds indexes to optimize calendar appointment loading queries:
 * - Composite index on (start_time, provider_id) for calendar range + provider filtering
 * - Index on start_time alone for futureOnly queries
 * 
 * These indexes support the P0-3 optimization that loads only today+future appointments
 * by provider, significantly reducing calendar load times for systems with historical data.
 */
class AddCalendarPerformanceIndexes extends MigrationBase
{
    public function up()
    {
        $table = 'appointments';

        // Guard: skip if appointments table doesn't exist yet
        if (!$this->db->tableExists($table)) {
            return;
        }

        // Index for futureOnly queries: WHERE start_time >= TODAY
        $this->createIndexIfMissing($table, 'idx_appts_start_time', ['start_time']);

        // Composite index for calendar range + provider queries
        $this->createIndexIfMissing($table, 'idx_appts_start_provider', ['start_time', 'provider_id']);

        // Composite index for calendar range + status queries
        $this->createIndexIfMissing($table, 'idx_appts_start_status', ['start_time', 'status']);

        log_message('info', '[Migration] Calendar performance indexes added successfully');
    }

    public function down()
    {
        if (!$this->db->tableExists('appointments')) {
            return;
        }

        $this->dropIndexIfExists('appointments', 'idx_appts_start_time');
        $this->dropIndexIfExists('appointments', 'idx_appts_start_provider');
        $this->dropIndexIfExists('appointments', 'idx_appts_start_status');

        log_message('info', '[Migration] Calendar performance indexes removed');
    }
}
