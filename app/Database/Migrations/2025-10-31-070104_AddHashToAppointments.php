<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddHashToAppointments extends Migration
{
    public function up()
    {
        // Add hash column to appointments table
        $this->forge->addColumn('appointments', [
            'hash' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => true, // Allow null initially for existing records
                'after'      => 'id',
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
        // Remove unique index
        $this->forge->dropKey('appointments', 'idx_appointments_hash');
        
        // Remove hash column
        $this->forge->dropColumn('appointments', 'hash');
    }
}
