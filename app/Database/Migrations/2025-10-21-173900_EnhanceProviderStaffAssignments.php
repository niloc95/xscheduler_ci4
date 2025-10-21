<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class EnhanceProviderStaffAssignments extends Migration
{
    public function up()
    {
        // Add assigned_by column (DBPrefix is auto-applied by forge)
        $this->forge->addColumn('provider_staff_assignments', [
            'assigned_by' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'staff_id',
            ],
        ]);

        // Add status column
        $this->forge->addColumn('provider_staff_assignments', [
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'active',
                'after'      => 'assigned_by',
            ],
        ]);

        // Add foreign key for assigned_by
        $this->db->query('
            ALTER TABLE xs_provider_staff_assignments
            ADD CONSTRAINT fk_assigned_by 
            FOREIGN KEY (assigned_by) 
            REFERENCES xs_users(id) 
            ON DELETE SET NULL
        ');

        // Add index for status lookups
        $this->db->query('
            ALTER TABLE xs_provider_staff_assignments
            ADD INDEX idx_status (status)
        ');
    }

    public function down()
    {
        // Drop foreign key first
        $this->db->query('ALTER TABLE xs_provider_staff_assignments DROP FOREIGN KEY fk_assigned_by');
        
        // Drop index
        $this->db->query('ALTER TABLE xs_provider_staff_assignments DROP INDEX idx_status');
        
        // Drop columns (DBPrefix auto-applied by forge)
        $this->forge->dropColumn('provider_staff_assignments', ['assigned_by', 'status']);
    }
}
