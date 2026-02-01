<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class AddActiveToCategories extends MigrationBase
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
        // Only attempt to drop column if both table and column exist
        if ($this->db->tableExists('categories') && $this->db->fieldExists('active', 'categories')) {
            $this->forge->dropColumn('categories', 'active');
        }
    }
}
