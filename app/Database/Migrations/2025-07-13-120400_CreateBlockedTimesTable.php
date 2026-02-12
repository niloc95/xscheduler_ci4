<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class CreateBlockedTimesTable extends MigrationBase
{
    public function up()
    {
        $this->forge->addField($this->sanitiseFields([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'provider_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'comment'    => 'NULL for global blocks',
            ],
            'start_time' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
            'end_time' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
            'reason' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
                'null'       => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]));

        $this->forge->addKey('id', true);
        $this->forge->addKey('provider_id');
        $this->forge->addKey('start_time');
        $this->forge->addForeignKey('provider_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('blocked_times');
    }

    public function down()
    {
        $this->forge->dropTable('blocked_times');
    }
}
