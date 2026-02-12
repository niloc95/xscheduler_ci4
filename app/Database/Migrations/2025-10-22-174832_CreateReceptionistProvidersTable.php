<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class CreateReceptionistProvidersTable extends MigrationBase
{
    public function up()
    {
        $this->forge->addField($this->sanitiseFields([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'receptionist_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
                'comment'    => 'Receptionist user ID',
            ],
            'provider_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
                'comment'    => 'Provider user ID',
            ],
            'assigned_by' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
                'comment'    => 'User who created this assignment',
            ],
            'assigned_at' => [
                'type' => 'DATETIME',
                'null' => false,
                'comment' => 'When the assignment was created',
            ],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => '20',
                'null'       => false,
                'default'    => 'active',
                'comment'    => 'Assignment status: active, inactive',
            ],
        ]));

        $this->forge->addKey('id', true);
        $this->forge->addKey('receptionist_id');
        $this->forge->addKey('provider_id');
        
        // Unique constraint: a receptionist can only be assigned to a provider once
        $this->forge->addUniqueKey(['receptionist_id', 'provider_id'], 'unique_receptionist_provider');
        
        // Foreign keys
        $this->forge->addForeignKey('receptionist_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('provider_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('assigned_by', 'users', 'id', 'CASCADE', 'CASCADE');
        
        $this->forge->createTable('receptionist_providers');
    }

    public function down()
    {
        $this->forge->dropTable('receptionist_providers');
    }
}
