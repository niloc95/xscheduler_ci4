<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class CreateBusinessHoursTable extends MigrationBase
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
                'comment'    => 'User ID with provider role',
            ],
            'weekday' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'comment'    => '0=Sun ... 6=Sat',
            ],
            'start_time' => [
                'type' => 'TIME',
                'null' => false,
            ],
            'end_time' => [
                'type' => 'TIME',
                'null' => false,
            ],
            'breaks_json' => [
                'type' => 'TEXT',
                'null' => true,
                'comment' => 'Optional breaks array as JSON [{start: "12:00", end: "12:30"}]',
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
        $this->forge->addKey(['provider_id', 'weekday']);
        $this->forge->addForeignKey('provider_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('business_hours');
    }

    public function down()
    {
        $this->forge->dropTable('business_hours');
    }
}
