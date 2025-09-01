<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddProfileImageToUsers extends Migration
{
    public function up()
    {
        $fields = [
            'profile_image' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'after'      => 'phone',
            ],
        ];
        $this->forge->addColumn('users', $fields);
    }

    public function down()
    {
        $this->forge->dropColumn('users', 'profile_image');
    }
}
