<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class AddHashToAppointments extends MigrationBase
{
    public function up()
    {
        $appointmentsTable = $this->db->prefixTable('appointments');

        // Guard: skip if appointments table doesn't exist yet
        if (!$this->db->tableExists($appointmentsTable)) {
            return;
        }

        // Skip if hash column already exists
        if ($this->hasColumn($appointmentsTable, 'hash')) {
            return;
        }

        // Add hash column to appointments table
        $this->forge->addColumn('appointments', [
            'hash' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => true, // Allow null initially for existing records
            ],
        ]);

        // Add unique index on hash column for fast lookups
        $this->forge->addKey('hash', false, true, 'idx_appointments_hash');
        $this->forge->processIndexes('appointments');

        // Generate hashes for existing appointments
        $builder = $this->db->table($appointmentsTable);
        $appointments = $builder->select('id')->get()->getResultArray();

        $encryptionKey = config('Encryption')->key ?? 'default-secret-key';
        
        foreach ($appointments as $appointment) {
            $hash = hash('sha256', 'appointment_' . $appointment['id'] . $encryptionKey . uniqid('', true));
            $builder->where('id', $appointment['id'])->update(['hash' => $hash]);
        }

        // Now make hash column NOT NULL after all records have hashes
        $this->forge->modifyColumn('appointments', [
            'hash' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => false,
            ],
        ]);
    }

    public function down()
    {
        $appointmentsTable = $this->db->prefixTable('appointments');

        if (!$this->db->tableExists($appointmentsTable)) {
            return;
        }

        // Remove unique index
        if ($this->hasIndex('appointments', 'idx_appointments_hash')) {
            try {
                $this->forge->dropKey('appointments', 'idx_appointments_hash');
            } catch (\Throwable $e) {
                // Index may not exist
            }
        }
        
        // Remove hash column
        if ($this->hasColumn($appointmentsTable, 'hash')) {
            try {
                $this->forge->dropColumn('appointments', 'hash');
            } catch (\Throwable $e) {
                // Legacy refresh paths may already have removed the column.
            }
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

    private function hasIndex(string $table, string $index): bool
    {
        $fullTable = $this->db->prefixTable($table);
        return $this->db->query(
            "SHOW INDEX FROM `{$fullTable}` WHERE Key_name = ?",
            [$index]
        )->getFirstRow() !== null;
    }
}
