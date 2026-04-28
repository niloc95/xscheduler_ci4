<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class AddNotifyOnAppointmentsToUsers extends MigrationBase
{
    public function up(): void
    {
        $table = $this->db->prefixTable('users');

        if (! $this->hasColumn($table, 'notify_on_appointments')) {
            $this->db->query("ALTER TABLE `{$table}` ADD COLUMN `notify_on_appointments` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1");
        }
    }

    public function down(): void
    {
        $table = $this->db->prefixTable('users');

        if ($this->hasColumn($table, 'notify_on_appointments')) {
            $this->db->query("ALTER TABLE `{$table}` DROP COLUMN `notify_on_appointments`");
        }
    }

    private function hasColumn(string $table, string $column): bool
    {
        $query = $this->db->query(
            'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1',
            [$this->db->database, $table, $column]
        );

        return $query->getFirstRow() !== null;
    }
}
