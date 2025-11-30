<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

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
class AddCalendarPerformanceIndexes extends Migration
{
    public function up()
    {
        // Check if indexes already exist to prevent duplicate index errors
        $db = \Config\Database::connect();
        $tableName = 'xs_appointments';
        
        // Get existing indexes
        $query = $db->query("SHOW INDEX FROM {$tableName}");
        $existingIndexes = [];
        foreach ($query->getResultArray() as $row) {
            $existingIndexes[] = $row['Key_name'];
        }
        
        // Index for futureOnly queries: WHERE start_time >= TODAY
        // This index optimizes the P0-3 futureOnly=1 parameter that excludes historical data
        if (!in_array('idx_appts_start_time', $existingIndexes)) {
            $this->forge->addKey('start_time', false, false, 'idx_appts_start_time');
            log_message('info', '[Migration] Adding idx_appts_start_time index');
        }
        
        // Composite index for calendar range + provider queries
        // Optimizes: WHERE start_time >= ? AND start_time <= ? AND provider_id = ?
        if (!in_array('idx_appts_start_provider', $existingIndexes)) {
            // Use raw SQL for composite index since forge doesn't support it directly
            $db->query("CREATE INDEX idx_appts_start_provider ON {$tableName} (start_time, provider_id)");
            log_message('info', '[Migration] Adding idx_appts_start_provider composite index');
        }
        
        // Composite index for calendar range + status queries (supports status filter buttons)
        // Optimizes: WHERE start_time >= ? AND status = ?
        if (!in_array('idx_appts_start_status', $existingIndexes)) {
            $db->query("CREATE INDEX idx_appts_start_status ON {$tableName} (start_time, status)");
            log_message('info', '[Migration] Adding idx_appts_start_status composite index');
        }
        
        log_message('info', '[Migration] Calendar performance indexes added successfully');
    }

    public function down()
    {
        $db = \Config\Database::connect();
        $tableName = 'xs_appointments';
        
        // Get existing indexes
        $query = $db->query("SHOW INDEX FROM {$tableName}");
        $existingIndexes = [];
        foreach ($query->getResultArray() as $row) {
            $existingIndexes[] = $row['Key_name'];
        }
        
        // Drop indexes if they exist
        if (in_array('idx_appts_start_time', $existingIndexes)) {
            $db->query("DROP INDEX idx_appts_start_time ON {$tableName}");
        }
        
        if (in_array('idx_appts_start_provider', $existingIndexes)) {
            $db->query("DROP INDEX idx_appts_start_provider ON {$tableName}");
        }
        
        if (in_array('idx_appts_start_status', $existingIndexes)) {
            $db->query("DROP INDEX idx_appts_start_status ON {$tableName}");
        }
        
        log_message('info', '[Migration] Calendar performance indexes removed');
    }
}
