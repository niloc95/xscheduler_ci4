<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddColorToUsers extends Migration
{
    public function up()
    {
        // Add color column for provider color assignment
        $fields = [
            'color' => [
                'type'       => 'VARCHAR',
                'constraint' => 10,
                'null'       => true,
                'comment'    => 'Provider color for calendar display (hex code)',
                'after'      => 'profile_image',
            ],
        ];
        
        $this->forge->addColumn('users', $fields);
        
        log_message('info', 'Added color column to xs_users table');
    }

    public function down()
    {
        $this->forge->dropColumn('users', 'color');
        
        log_message('info', 'Dropped color column from xs_users table');
    }
}
