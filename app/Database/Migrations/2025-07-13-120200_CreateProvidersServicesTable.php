<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateProvidersServicesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'provider_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'service_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
        ]);

        $this->forge->addKey(['provider_id', 'service_id'], true);
        $this->forge->addForeignKey('provider_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('service_id', 'services', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('providers_services');
    }

    public function down()
    {
        $this->forge->dropTable('providers_services');
    }
}
