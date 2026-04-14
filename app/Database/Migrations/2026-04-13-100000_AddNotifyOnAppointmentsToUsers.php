<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class AddNotifyOnAppointmentsToUsers extends MigrationBase
{
    public function up(): void
    {
        $table = $this->db->prefixTable('users');

        if (!$this->db->fieldExists('notify_on_appointments', $table)) {
            $this->forge->addColumn('users', [
                'notify_on_appointments' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'unsigned'   => true,
                    'null'       => false,
                    'default'    => 1,
                ],
            ]);
        }
    }

    public function down(): void
    {
        $table = $this->db->prefixTable('users');

        if ($this->db->fieldExists('notify_on_appointments', $table)) {
            $this->forge->dropColumn('users', 'notify_on_appointments');
        }
    }
}
