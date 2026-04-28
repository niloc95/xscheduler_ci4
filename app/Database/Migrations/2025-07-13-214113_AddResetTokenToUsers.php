<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class AddResetTokenToUsers extends MigrationBase
{
    public function up()
    {
        if (! $this->hasTable('users')) {
            return;
        }

        if (! $this->hasColumn('users', 'reset_token')) {
            $this->forge->addColumn('users', [
                'reset_token' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                ],
            ]);
        }

        if (! $this->hasColumn('users', 'reset_expires')) {
            $this->forge->addColumn('users', [
                'reset_expires' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);
        }
    }

    public function down()
    {
        if (! $this->hasTable('users')) {
            return;
        }

        if ($this->hasColumn('users', 'reset_token')) {
            $this->forge->dropColumn('users', 'reset_token');
        }

        if ($this->hasColumn('users', 'reset_expires')) {
            $this->forge->dropColumn('users', 'reset_expires');
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
}
