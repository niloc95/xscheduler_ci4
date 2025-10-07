<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class AddCompositeIndexesToAppointments extends MigrationBase
{
    public function up()
    {
        $db = $this->db;

        if (!$db->tableExists('appointments')) {
            return;
        }

        $appointmentsTable = $db->prefixTable('appointments');

        // Add composite indexes to speed up counts and range queries with filters
        $this->createIndexIfMissing($appointmentsTable, 'idx_appts_provider_start', ['provider_id', 'start_time']);
        $this->createIndexIfMissing($appointmentsTable, 'idx_appts_service_start', ['service_id', 'start_time']);
        $this->createIndexIfMissing($appointmentsTable, 'idx_appts_status_start', ['status', 'start_time']);
    }

    public function down()
    {
        $db = $this->db;

        if (!$db->tableExists('appointments')) {
            return;
        }

        $appointmentsTable = $db->prefixTable('appointments');

        // Drop indexes if they exist
        $this->dropIndexIfExists($appointmentsTable, 'idx_appts_provider_start');
        $this->dropIndexIfExists($appointmentsTable, 'idx_appts_service_start');
        $this->dropIndexIfExists($appointmentsTable, 'idx_appts_status_start');
    }

    private function createIndexIfMissing(string $table, string $indexName, array $columns): void
    {
        $db     = $this->db;
        $exists = $db->query("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName])->getFirstRow();

        if ($exists) {
            return;
        }

        $columnList = implode(', ', array_map(static fn ($column) => "`{$column}`", $columns));
        $db->query("CREATE INDEX `{$indexName}` ON `{$table}` ({$columnList})");
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        $db     = $this->db;
        $exists = $db->query("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName])->getFirstRow();

        if (!$exists) {
            return;
        }

        $db->query("ALTER TABLE `{$table}` DROP INDEX `{$indexName}`");
    }
}
