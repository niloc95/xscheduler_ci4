<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AlterCategoriesAddActive extends Migration
{
    public function up()
    {
        $this->forge->addColumn('categories', [
            'active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
                'after'      => 'color',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('categories', 'active');
    }
}
