<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class AlterCategoriesAddActive extends MigrationBase
{
    public function up()
    {
        $this->forge->addColumn('categories', [
            'active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
        ]);
    }

    public function down()
    {
        if ($this->db->tableExists('categories') && $this->db->fieldExists('active', 'categories')) {
            try {
                $this->forge->dropColumn('categories', 'active');
            } catch (\Throwable $e) {
                // Column may already be absent on some rollback paths
            }
        }
    }
}
