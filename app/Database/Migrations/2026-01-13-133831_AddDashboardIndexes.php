<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDashboardIndexes extends Migration
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
        $db = \Config\Database::connect();
        
        // Check if indexes already exist before creating
        
        // Index for provider + start_time + status queries (getTodayMetrics, getTodaySchedule)
        if (!$this->indexExists('xs_appointments', 'idx_provider_start_status')) {
            $db->query('CREATE INDEX idx_provider_start_status ON xs_appointments (provider_id, start_time, status)');
        }
        
        // Index for start_time + end_time queries (schedule ordering, time-based filtering)
        if (!$this->indexExists('xs_appointments', 'idx_start_end_time')) {
            $db->query('CREATE INDEX idx_start_end_time ON xs_appointments (start_time, end_time)');
        }
        
        // Index for status + start_time queries (pending confirmations, status-based alerts)
        if (!$this->indexExists('xs_appointments', 'idx_status_start')) {
            $db->query('CREATE INDEX idx_status_start ON xs_appointments (status, start_time)');
        }
        
        // Note: start_time already has an index from the main table creation
        // So we don't need idx_start_time separately
    }

    public function down()
    {
        $db = \Config\Database::connect();
        
        // Drop indexes in reverse order (SQLite-compatible)
        if ($this->indexExists('xs_appointments', 'idx_status_start')) {
            if ($db->DBDriver === 'SQLite3') {
                $db->query('DROP INDEX idx_status_start');
            } else {
                $db->query('DROP INDEX idx_status_start ON xs_appointments');
            }
        }
        
        if ($this->indexExists('xs_appointments', 'idx_start_end_time')) {
            if ($db->DBDriver === 'SQLite3') {
                $db->query('DROP INDEX idx_start_end_time');
            } else {
                $db->query('DROP INDEX idx_start_end_time ON xs_appointments');
            }
        }
        
        if ($this->indexExists('xs_appointments', 'idx_provider_start_status')) {
            if ($db->DBDriver === 'SQLite3') {
                $db->query('DROP INDEX idx_provider_start_status');
            } else {
                $db->query('DROP INDEX idx_provider_start_status ON xs_appointments');
            }
        }
    }
    
    /**
     * Check if an index exists on a table
     * 
     * @param string $table Table name
     * @param string $indexName Index name
     * @return bool
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $db = \Config\Database::connect();
        
        // For MySQL/MariaDB
        if ($db->DBDriver === 'MySQLi') {
            $query = $db->query("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
            return $query->getNumRows() > 0;
        }
        
        // For PostgreSQL
        if ($db->DBDriver === 'Postgre') {
            $query = $db->query("SELECT indexname FROM pg_indexes WHERE tablename = ? AND indexname = ?", [$table, $indexName]);
            return $query->getNumRows() > 0;
        }
        
        // For SQLite
        if ($db->DBDriver === 'SQLite3') {
            $query = $db->query("PRAGMA index_list({$table})");
            foreach ($query->getResultArray() as $row) {
                if ($row['name'] === $indexName) {
                    return true;
                }
            }
            return false;
        }
        
        // Default: assume doesn't exist
        return false;
    }
}
