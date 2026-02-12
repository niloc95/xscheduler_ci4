<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

/**
 * Add composite indexes for customer appointment history queries
 * Optimizes queries for customer history pagination and filtering
 */
class AddCustomerHistoryIndexes extends MigrationBase
{
    public function up()
    {
        if (!$this->db->tableExists('appointments')) {
            return;
        }

        $table = 'appointments';

        // Composite index for customer history queries (customer_id + start_time DESC)
        $this->createIndexIfMissing($table, 'idx_appts_customer_start', ['customer_id', 'start_time']);
        
        // Composite index for customer history filtering by status
        $this->createIndexIfMissing($table, 'idx_appts_customer_status', ['customer_id', 'status']);
        
        // Composite index for customer+provider history queries
        $this->createIndexIfMissing($table, 'idx_appts_customer_provider', ['customer_id', 'provider_id']);
    }

    public function down()
    {
        if (!$this->db->tableExists('appointments')) {
            return;
        }

        $this->dropIndexIfExists('appointments', 'idx_appts_customer_start');
        $this->dropIndexIfExists('appointments', 'idx_appts_customer_status');
        $this->dropIndexIfExists('appointments', 'idx_appts_customer_provider');
    }
}
