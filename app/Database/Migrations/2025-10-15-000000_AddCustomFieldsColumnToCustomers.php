<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class AddCustomFieldsColumnToCustomers extends MigrationBase
{
    public function up()
    {
        if (!$this->db->fieldExists('custom_fields', 'customers')) {
            $this->forge->addColumn('customers', [
                'custom_fields' => [
                    'type'       => 'TEXT',
                    'null'       => true,
                    'comment'    => 'JSON encoded map of custom booking fields',
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('custom_fields', 'customers')) {
            $this->forge->dropColumn('customers', 'custom_fields');
        }
    }
}
