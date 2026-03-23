<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class AlterServicesAddCategoryAndActive extends MigrationBase
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
        ];
        // active
        $fields['active'] = [
            'type'       => 'TINYINT',
            'constraint' => 1,
            'default'    => 1,
        ];

        $forge->addColumn('services', $this->sanitiseFields($fields));

    }

    public function down()
    {
        if ($this->db->tableExists('services') && $this->db->fieldExists('category_id', 'services')) {
            $this->forge->dropColumn('services', 'category_id');
        }

        if ($this->db->tableExists('services') && $this->db->fieldExists('active', 'services')) {
            $this->forge->dropColumn('services', 'active');
        }
    }
}
