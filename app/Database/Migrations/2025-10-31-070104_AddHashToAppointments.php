<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class AddHashToAppointments extends MigrationBase
{
    public function up()
    {
        // Guard: skip if appointments table doesn't exist yet
        if (!$this->db->tableExists('appointments')) {
            return;
        }

        // Skip if hash column already exists
        if ($this->db->fieldExists('hash', 'appointments')) {
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
        $db = \Config\Database::connect();
        $builder = $db->table('appointments'); // CodeIgniter automatically adds the prefix
        $appointments = $builder->select('id')->get()->getResultArray();

        $encryptionKey = config('Encryption')->key ?? 'default-secret-key';
        
        foreach ($appointments as $appointment) {
            $hash = hash('sha256', 'appointment_' . $appointment['id'] . $encryptionKey . uniqid('', true));
            $builder->where('id', $appointment['id'])->update(['hash' => $hash]);
        }

        // Now make hash column NOT NULL after all records have hashes
        // Skip on SQLite — modifyColumn triggers table recreation that can fail
        if (!$this->isSQLite()) {
            $this->forge->modifyColumn('appointments', [
                'hash' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                    'null'       => false,
                ],
            ]);
        }
    }

    public function down()
    {
        if (!$this->db->tableExists('appointments')) {
            return;
        }

        // Remove unique index
        try {
            $this->forge->dropKey('appointments', 'idx_appointments_hash');
        } catch (\Throwable $e) {
            // Index may not exist
        }
        
        // Remove hash column
        if ($this->db->fieldExists('hash', 'appointments')) {
            $this->forge->dropColumn('appointments', 'hash');
        }
    }
}
