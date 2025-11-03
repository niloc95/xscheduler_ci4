<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddHashToCustomers extends Migration
{
    public function up()
    {
        // Add hash column to customers table (without prefix, CI4 adds it)
        $this->forge->addColumn('customers', [
            'hash' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => true, // Allow null initially for existing records
                'after'      => 'id',
            ],
        ]);

        // Add unique index on hash column for fast lookups
        $this->forge->addKey('hash', false, true, 'idx_customers_hash');
        $this->forge->processIndexes('customers');

        // Generate hashes for existing customers
        $db = \Config\Database::connect();
        $builder = $db->table('customers'); // CodeIgniter automatically adds the prefix
        $customers = $builder->select('id')->get()->getResultArray();

        $encryptionKey = config('Encryption')->key ?? 'default-secret-key';
        
        foreach ($customers as $customer) {
            $hash = hash('sha256', $customer['id'] . $encryptionKey . uniqid('', true));
            $builder->where('id', $customer['id'])->update(['hash' => $hash]);
        }

        // Now make hash column NOT NULL after all records have hashes
        $this->forge->modifyColumn('customers', [
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
        $this->forge->dropKey('customers', 'idx_customers_hash');
        
        // Remove hash column
        $this->forge->dropColumn('customers', 'hash');
    }
}
