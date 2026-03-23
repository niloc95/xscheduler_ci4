<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class ConvertReceptionistsToStaff extends MigrationBase
{
    public function up()
    {
        // Historical note:
        // This migration superseded the receptionist role with the staff role.
        // Older migrations and logs still use receptionist terminology, but new
        // runtime behavior should treat staff as the active domain concept.
        // Convert all receptionist users to staff role
        $this->db->table('users')
            ->where('role', 'receptionist')
            ->update(['role' => 'staff']);
        
        log_message('info', 'Converted all receptionist users to staff role');
    }

    public function down()
    {
        // Cannot reliably reverse this migration since we don't know
        // which staff were originally receptionists
        log_message('warning', 'Cannot reverse receptionist to staff conversion');
    }
}
