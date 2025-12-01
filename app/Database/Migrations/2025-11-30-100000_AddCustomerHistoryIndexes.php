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
        $db = $this->db;

        if (!$db->tableExists('appointments')) {
            return;
        }

        $appointmentsTable = $db->prefixTable('appointments');

        // Composite index for customer history queries (customer_id + start_time DESC)
        $this->createIndexIfMissing($appointmentsTable, 'idx_appts_customer_start', ['customer_id', 'start_time']);
        
        // Composite index for customer history filtering by status
        $this->createIndexIfMissing($appointmentsTable, 'idx_appts_customer_status', ['customer_id', 'status']);
        
        // Composite index for customer+provider history queries
        $this->createIndexIfMissing($appointmentsTable, 'idx_appts_customer_provider', ['customer_id', 'provider_id']);
    }

    public function down()
    {
        $db = $this->db;

        if (!$db->tableExists('appointments')) {
            return;
        }

        $appointmentsTable = $db->prefixTable('appointments');

        $this->dropIndexIfExists($appointmentsTable, 'idx_appts_customer_start');
        $this->dropIndexIfExists($appointmentsTable, 'idx_appts_customer_status');
        $this->dropIndexIfExists($appointmentsTable, 'idx_appts_customer_provider');
    }

    private function createIndexIfMissing(string $table, string $indexName, array $columns): void
    {
        $db = $this->db;
        $exists = $db->query("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName])->getFirstRow();

        if ($exists) {
            return;
        }

        $columnList = implode(', ', array_map(static fn ($column) => "`{$column}`", $columns));
        $db->query("CREATE INDEX `{$indexName}` ON `{$table}` ({$columnList})");
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        $db = $this->db;
        $exists = $db->query("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName])->getFirstRow();

        if (!$exists) {
            return;
        }

        $db->query("ALTER TABLE `{$table}` DROP INDEX `{$indexName}`");
    }
}
