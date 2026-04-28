<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class AddCustomFieldsColumnToCustomers extends MigrationBase
{
    public function up()
    {
        if (!$this->db->tableExists('customers')) {
            return;
        }

        // Use information_schema to bypass any stale CI4 field-name cache that can
        // survive a down()/up() cycle inside the same PHP process (e.g. migrate:refresh).
        $database  = $this->db->getDatabase();
        $prefix    = $this->db->getPrefix();
        $tableName = $prefix . 'customers';
        $check = $this->db->query(
            'SELECT 1 FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ? LIMIT 1',
            [$database, $tableName, 'custom_fields']
        );

        if ($check->getNumRows() > 0) {
            return; // Column already present — nothing to do.
        }

        $this->forge->addColumn('customers', [
            'custom_fields' => [
                'type'    => 'TEXT',
                'null'    => true,
                'comment' => 'JSON encoded map of custom booking fields',
            ],
        ]);
    }

    public function down()
    {
        if (!$this->db->tableExists('customers')) {
            return;
        }

        if ($this->db->fieldExists('custom_fields', 'customers')) {
            try {
                $this->forge->dropColumn('customers', 'custom_fields');
            } catch (\Throwable $e) {
                // Column may already be absent on some refresh paths.
            }
        }
    }
}
