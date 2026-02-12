<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class AddColorToUsers extends MigrationBase
{
    public function up()
    {
        // Skip if column already exists (may have been added in initial
        // CreateUsersTable migration on fresh installs).
        if ($this->db->fieldExists('color', $this->db->prefixTable('users'))
            || $this->db->fieldExists('color', 'users')) {
            log_message('info', 'AddColorToUsers: color column already exists, skipping');
            return;
        }

        // Add color column for provider color assignment
        $fields = $this->sanitiseFields([
            'color' => [
                'type'       => 'VARCHAR',
                'constraint' => 10,
                'null'       => true,
                'comment'    => 'Provider color for calendar display (hex code)',
            ],
        ]);
        
        $this->forge->addColumn('users', $fields);
        
        log_message('info', 'Added color column to xs_users table');
    }

    public function down()
    {
        $this->forge->dropColumn('users', 'color');
        
        log_message('info', 'Dropped color column from xs_users table');
    }
}
