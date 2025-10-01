<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class AddReminderSentToAppointments extends MigrationBase
{
    public function up()
    {
        // Cross-DB: BOOLEAN maps to appropriate underlying type per driver
        $field = [
            'reminder_sent' => [
                'type'    => 'BOOLEAN',
                'default' => 0,
                'null'    => false,
            ],
        ];
        $this->forge->addColumn('appointments', $field);
    }

    public function down()
    {
        $this->forge->dropColumn('appointments', 'reminder_sent');
    }
}
