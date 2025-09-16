<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddActiveToCategories extends Migration
{
    public function up()
    {
        // Add 'active' column if it does not exist
        if (!$this->db->fieldExists('active', 'categories')) {
            $fields = [
                'active' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'null'       => false,
                    'default'    => 1,
                    'comment'    => '1=active, 0=inactive',
                ],
            ];
            $this->forge->addColumn('categories', $fields);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('active', 'categories')) {
            $this->forge->dropColumn('categories', 'active');
        }
    }
}
