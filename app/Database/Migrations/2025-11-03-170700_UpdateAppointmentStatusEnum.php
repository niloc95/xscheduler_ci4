<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class UpdateAppointmentStatusEnum extends Migration
{
    public function up()
    {
        // Modify the status column to use new enum values
        $fields = [
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['pending', 'confirmed', 'completed', 'cancelled', 'no-show'],
                'default'    => 'pending',
                'null'       => false,
            ],
        ];

        $this->forge->modifyColumn('appointments', $fields);
        
        // Update any existing 'booked' or 'rescheduled' statuses to 'pending'
        $this->db->table('appointments')->whereIn('status', ['booked', 'rescheduled'])->update(['status' => 'pending']);
    }

    public function down()
    {
        // Revert to old enum values
        $fields = [
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['booked', 'cancelled', 'completed', 'rescheduled'],
                'default'    => 'booked',
                'null'       => false,
            ],
        ];

        $this->forge->modifyColumn('appointments', $fields);
        
        // Update any new statuses back to old ones
        $this->db->table('appointments')->whereIn('status', ['pending', 'confirmed'])->update(['status' => 'booked']);
        $this->db->table('appointments')->where('status', 'no-show')->update(['status' => 'completed']);
    }
}
