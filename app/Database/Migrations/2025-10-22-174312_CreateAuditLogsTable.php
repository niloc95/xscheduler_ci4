<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAuditLogsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
                'comment'    => 'User who performed the action',
            ],
            'action' => [
                'type'       => 'VARCHAR',
                'constraint' => '100',
                'null'       => false,
                'comment'    => 'Action type: user_created, user_updated, role_changed, password_reset, etc.',
            ],
            'target_type' => [
                'type'       => 'VARCHAR',
                'constraint' => '50',
                'null'       => false,
                'default'    => 'user',
                'comment'    => 'Type of entity affected: user, provider, staff, etc.',
            ],
            'target_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'comment'    => 'ID of the affected entity',
            ],
            'old_value' => [
                'type' => 'TEXT',
                'null' => true,
                'comment' => 'JSON of previous values (for updates)',
            ],
            'new_value' => [
                'type' => 'TEXT',
                'null' => true,
                'comment' => 'JSON of new values (for updates)',
            ],
            'ip_address' => [
                'type'       => 'VARCHAR',
                'constraint' => '45',
                'null'       => true,
                'comment'    => 'IP address of the request',
            ],
            'user_agent' => [
                'type' => 'VARCHAR',
                'constraint' => '255',
                'null' => true,
                'comment' => 'User agent string',
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('user_id');
        $this->forge->addKey('target_id');
        $this->forge->addKey('action');
        $this->forge->addKey('created_at');
        
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        
        $this->forge->createTable('audit_logs');
    }

    public function down()
    {
        $this->forge->dropTable('audit_logs');
    }
}
