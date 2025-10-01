<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class CreateSettingsFiles extends MigrationBase
{
    public function up()
    {
        if ($this->db->tableExists('settings_files')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'setting_key' => [
                'type' => 'VARCHAR',
                'constraint' => 191,
            ],
            'filename' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
            ],
            'mime' => [
                'type' => 'VARCHAR',
                'constraint' => 64,
                'null' => true,
            ],
            'data' => [
                'type' => 'LONGBLOB',
                'null' => true,
            ],
            'updated_by' => [
                'type' => 'INT',
                'constraint' => 11,
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('setting_key');
        $this->forge->createTable('settings_files');
    }

    public function down()
    {
        if ($this->db->tableExists('settings_files')) {
            $this->forge->dropTable('settings_files');
        }
    }
}
