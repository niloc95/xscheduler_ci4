<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class AddPublicTokenToAppointments extends MigrationBase
{
    public function up()
    {
        // Guard: skip if appointments table doesn't exist yet
        if (! $this->hasTable('appointments')) {
            return;
        }

        if (! $this->hasColumn('appointments', 'public_token')) {
            $this->forge->addColumn('appointments', [
                'public_token' => [
                    'type'       => 'CHAR',
                    'constraint' => 36,
                    'null'       => true,
                    'comment'    => 'Public confirmation token (GUID)',
                ],
            ]);
        }

        if (! $this->hasColumn('appointments', 'public_token_expires_at')) {
            $this->forge->addColumn('appointments', [
                'public_token_expires_at' => [
                    'type'    => 'DATETIME',
                    'null'    => true,
                    'comment' => 'Optional expiry for public token',
                ],
            ]);
        }

        if (! $this->hasIndexByName('appointments', 'idx_appointments_public_token')) {
            $this->forge->addKey('public_token', false, true, 'idx_appointments_public_token');
            $this->forge->processIndexes('appointments');
        }
    }

    public function down()
    {
        if (! $this->hasTable('appointments')) {
            return;
        }

        if ($this->hasIndexByName('appointments', 'idx_appointments_public_token')) {
            try {
                $this->forge->dropKey('appointments', 'idx_appointments_public_token');
            } catch (\Throwable $e) {
                // Refresh paths can reach here after a partially-applied rollback.
            }
        }

        if ($this->hasColumn('appointments', 'public_token')) {
            try {
                $this->forge->dropColumn('appointments', 'public_token');
            } catch (\Throwable $e) {
                // Column(s) may already be absent on legacy refresh paths.
            }
        }

        if ($this->hasColumn('appointments', 'public_token_expires_at')) {
            try {
                $this->forge->dropColumn('appointments', 'public_token_expires_at');
            } catch (\Throwable $e) {
                // Column may already be absent on legacy refresh paths.
            }
        }
    }

    private function hasTable(string $table): bool
    {
        $database = $this->db->getDatabase();
        $tableName = $this->db->prefixTable($table);
        $query = $this->db->query(
            'SELECT 1 FROM information_schema.tables WHERE table_schema = ? AND table_name = ? LIMIT 1',
            [$database, $tableName]
        );

        return $query->getNumRows() > 0;
    }

    private function hasColumn(string $table, string $column): bool
    {
        $database = $this->db->getDatabase();
        $tableName = $this->db->prefixTable($table);
        $query = $this->db->query(
            'SELECT 1 FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ? LIMIT 1',
            [$database, $tableName, $column]
        );

        return $query->getNumRows() > 0;
    }

    private function hasIndexByName(string $table, string $index): bool
    {
        $database = $this->db->getDatabase();
        $tableName = $this->db->prefixTable($table);
        $query = $this->db->query(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
            [$database, $tableName, $index]
        );

        return $query->getNumRows() > 0;
    }
}
