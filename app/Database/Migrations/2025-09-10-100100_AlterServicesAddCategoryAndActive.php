<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AlterServicesAddCategoryAndActive extends Migration
{
    public function up()
    {
        // Add columns if not exist
        $fields = [];
        $forge = $this->forge;

        // category_id
        $fields['category_id'] = [
            'type'       => 'INT',
            'constraint' => 11,
            'unsigned'   => true,
            'null'       => true,
            'after'      => 'description',
        ];
        // active
        $fields['active'] = [
            'type'       => 'TINYINT',
            'constraint' => 1,
            'default'    => 1,
            'after'      => 'price',
        ];

        $forge->addColumn('services', $fields);

    }

    public function down()
    {
        $this->forge->dropColumn('services', 'category_id');
        $this->forge->dropColumn('services', 'active');
    }
}
