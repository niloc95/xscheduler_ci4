<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddStatusToUsers extends Migration
{
    public function up()
    {
        // Cross-DB: Use ENUM on MySQL, VARCHAR on SQLite/Postgres/etc.
        $field = [
            'status' => [
                'type'    => 'VARCHAR',
                'constraint' => 16,
                'default' => 'active',
                'null'    => false,
            ],
        ];

        // On MySQL, upgrade to ENUM for stricter validation
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
        $this->forge->dropColumn('users', 'status');
    }
}
