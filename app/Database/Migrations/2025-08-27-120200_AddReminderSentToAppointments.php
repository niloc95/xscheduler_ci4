<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddReminderSentToAppointments extends Migration
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
