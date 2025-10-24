<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class CreateProviderSchedulesTable extends MigrationBase
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'provider_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => false,
            ],
            'day_of_week' => [
                'type'       => 'ENUM',
                'constraint' => ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'],
                'null'       => false,
            ],
            'start_time' => [
                'type' => 'TIME',
                'null' => false,
            ],
            'end_time' => [
                'type' => 'TIME',
                'null' => false,
            ],
            'break_start' => [
                'type' => 'TIME',
                'null' => true,
            ],
            'break_end' => [
                'type' => 'TIME',
                'null' => true,
            ],
            'is_active' => [
                'type'    => 'BOOLEAN',
                'null'    => false,
                'default' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['provider_id', 'day_of_week'], 'provider_day_unique');
        $this->forge->addForeignKey('provider_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('provider_schedules');
    }

    public function down(): void
    {
        $this->forge->dropTable('provider_schedules', true);
    }
}
