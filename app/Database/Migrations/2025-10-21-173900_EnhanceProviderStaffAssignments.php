<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class EnhanceProviderStaffAssignments extends MigrationBase
{
    public function up()
    {
        // Get the database prefix from connection config
        $prefix = $this->db->DBPrefix;
        
        // Add assigned_by column (DBPrefix is auto-applied by forge)
        $this->forge->addColumn('provider_staff_assignments', $this->sanitiseFields([
            'assigned_by' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
        ]));

        // Add status column
        $this->forge->addColumn('provider_staff_assignments', [
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'active',
            ],
        ]);

        // Add foreign key for assigned_by (MySQL only - SQLite does not support ALTER TABLE ADD CONSTRAINT)
        $this->mysqlOnly("
            ALTER TABLE {$prefix}provider_staff_assignments
            ADD CONSTRAINT fk_assigned_by 
            FOREIGN KEY (assigned_by) 
            REFERENCES {$prefix}users(id) 
            ON DELETE SET NULL
        ");

        // Add index for status lookups (cross-database)
        $this->createIndexIfMissing('provider_staff_assignments', 'idx_status', ['status']);
    }

    public function down()
    {
        // Get the database prefix from connection config
        $prefix = $this->db->DBPrefix;
        
        // Drop foreign key first (MySQL only)
        $this->mysqlOnly("ALTER TABLE {$prefix}provider_staff_assignments DROP FOREIGN KEY fk_assigned_by");
        
        // Drop index (cross-database)
        $this->dropIndexIfExists('provider_staff_assignments', 'idx_status');
        
        // Drop columns (DBPrefix auto-applied by forge)
        $this->forge->dropColumn('provider_staff_assignments', ['assigned_by', 'status']);
    }
}
