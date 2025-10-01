<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class AddResetTokenToUsers extends MigrationBase
{
    public function up()
    {
        $this->forge->addColumn('users', [
            'reset_token' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'after'      => 'password_hash'
            ],
            'reset_expires' => [
                'type'       => 'DATETIME',
                'null'       => true,
                'after'      => 'reset_token'
            ]
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('users', ['reset_token', 'reset_expires']);
    }
}
