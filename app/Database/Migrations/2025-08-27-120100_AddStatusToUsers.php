<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class AddStatusToUsers extends MigrationBase
{
    public function up()
    {
        $field = [
            'status' => [
                'type'    => 'VARCHAR',
                'constraint' => 16,
                'default' => 'active',
                'null'    => false,
            ],
        ];

        if ($this->db->DBDriver === 'MySQLi') {
            $field['status'] = [
                'type'       => 'ENUM',
                'constraint' => ['active', 'inactive', 'suspended'],
                'default'    => 'active',
                'null'       => false,
            ];
        }

        $this->forge->addColumn('users', $field);
    }

    public function down()
    {
        if ($this->db->fieldExists('status', 'users')) {
            try {
                $this->forge->dropColumn('users', 'status');
            } catch (\Throwable $e) {
                // Column may already be absent on some rollback paths
            }
        }
    }
}
