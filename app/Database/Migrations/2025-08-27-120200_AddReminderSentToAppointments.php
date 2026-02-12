<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class AddReminderSentToAppointments extends MigrationBase
{
    public function up()
    {
        // Guard: skip if appointments table doesn't exist yet
        if (!$this->db->tableExists('appointments')) {
            return;
        }

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
        if (!$this->db->tableExists('appointments')) {
            return;
        }

        $this->forge->dropColumn('appointments', 'reminder_sent');
    }
}
