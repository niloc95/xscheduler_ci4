<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class UpdateAppointmentStatusEnum extends MigrationBase
{
    public function up()
    {
        if (!$this->db->tableExists('appointments')) {
            return;
        }

        // Modify the status column to use new enum values (skips on SQLite)
        $this->modifyEnumColumn('appointments', [
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['pending', 'confirmed', 'completed', 'cancelled', 'no-show'],
                'default'    => 'pending',
                'null'       => false,
            ],
        ]);
        
        // Update any existing 'booked' or 'rescheduled' statuses to 'pending'
        $this->db->table('appointments')->whereIn('status', ['booked', 'rescheduled'])->update(['status' => 'pending']);
    }

    public function down()
    {
        if (!$this->db->tableExists('appointments')) {
            return;
        }

        // Revert to old enum values (skips on SQLite)
        $this->modifyEnumColumn('appointments', [
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['booked', 'cancelled', 'completed', 'rescheduled'],
                'default'    => 'booked',
                'null'       => false,
            ],
        ]);
        
        // Update any new statuses back to old ones
        $this->db->table('appointments')->whereIn('status', ['pending', 'confirmed'])->update(['status' => 'booked']);
        $this->db->table('appointments')->where('status', 'no-show')->update(['status' => 'completed']);
    }
}
