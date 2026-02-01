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
        $this->forge->dropColumn('categories', 'active');
    }
}
