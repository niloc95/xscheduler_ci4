<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPublicTokenToAppointments extends Migration
{
    public function up()
    {
        if (!$this->db->fieldExists('public_token', 'appointments')) {
            $this->forge->addColumn('appointments', [
                'public_token' => [
                    'type'       => 'CHAR',
                    'constraint' => 36,
                    'null'       => true,
                    'comment'    => 'Public confirmation token (GUID)',
                ],
                'public_token_expires_at' => [
                    'type'    => 'DATETIME',
                    'null'    => true,
                    'comment' => 'Optional expiry for public token',
                ],
            ]);

            $this->forge->addKey('public_token', false, true, 'idx_appointments_public_token');
            $this->forge->processIndexes('appointments');
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('public_token', 'appointments')) {
            $this->forge->dropKey('appointments', 'idx_appointments_public_token');
            $this->forge->dropColumn('appointments', ['public_token', 'public_token_expires_at']);
        }
    }
}
