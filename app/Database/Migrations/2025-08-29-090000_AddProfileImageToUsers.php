<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class AddProfileImageToUsers extends MigrationBase
{
    public function up()
    {
        $fields = [
            'profile_image' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
        ];
        $this->forge->addColumn('users', $this->sanitiseFields($fields));
    }

    public function down()
    {
        // Check if column exists before dropping (SQLite compatibility)
        if ($this->db->fieldExists('profile_image', 'users')) {
            $this->forge->dropColumn('users', 'profile_image');
        }
    }
}
