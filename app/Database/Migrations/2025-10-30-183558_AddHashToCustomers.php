<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class AddHashToCustomers extends MigrationBase
{
    public function up()
    {
        // Guard: skip if customers table doesn't exist yet
        if (!$this->db->tableExists('customers')) {
            return;
        }

        // Skip if hash column already exists
        if ($this->db->fieldExists('hash', 'customers')) {
            return;
        }

        // Add hash column to customers table (without prefix, CI4 adds it)
        $this->forge->addColumn('customers', $this->sanitiseFields([
            'hash' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => true, // Allow null initially for existing records
                'after'      => 'id',
            ],
        ]));

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
        // Skip on SQLite — modifyColumn triggers table recreation that can fail
        if (!$this->isSQLite()) {
            $this->forge->modifyColumn('customers', [
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
        if (!$this->db->tableExists('customers')) {
            return;
        }

        // Remove unique index
        try {
            $this->forge->dropKey('customers', 'idx_customers_hash');
        } catch (\Throwable $e) {
            // Index may not exist
        }
        
        // Remove hash column
        if ($this->db->fieldExists('hash', 'customers')) {
            $this->forge->dropColumn('customers', 'hash');
        }
    }
}
