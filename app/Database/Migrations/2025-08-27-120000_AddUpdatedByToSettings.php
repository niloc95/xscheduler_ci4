<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class AddUpdatedByToSettings extends MigrationBase
{
    public function up()
    {
        // Ensure settings table exists; create if missing
        if (! $this->db->tableExists('settings')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'setting_key' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                ],
                'setting_value' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'setting_type' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 20,
                    'default'    => 'string',
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'updated_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'updated_by' => [
                    'type'     => 'INT',
                    'unsigned' => true,
                    'null'     => true,
                ],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('setting_key');
            $this->forge->createTable('settings');

            // Add FK constraint (MySQL only)
            if ($this->db->DBDriver === 'MySQLi') {
                $settings = $this->db->prefixTable('settings');
                $users    = $this->db->prefixTable('users');
                $this->db->query("ALTER TABLE {$settings} ADD CONSTRAINT fk_settings_updated_by FOREIGN KEY (updated_by) REFERENCES {$users}(id) ON DELETE SET NULL");
            }
            return;
        }

        // Add column if it does not exist
        if (! $this->db->fieldExists('updated_by', 'settings')) {
            $this->forge->addColumn('settings', [
                'updated_by' => [
                    'type'     => 'INT',
                    'unsigned' => true,
                    'null'     => true,
                ],
            ]);

            if ($this->db->DBDriver === 'MySQLi') {
                $settings = $this->db->prefixTable('settings');
                $users    = $this->db->prefixTable('users');
                $this->db->query("ALTER TABLE {$settings} ADD CONSTRAINT fk_settings_updated_by FOREIGN KEY (updated_by) REFERENCES {$users}(id) ON DELETE SET NULL");
            }
        }
    }

    public function down()
    {
        if (! $this->db->tableExists('settings')) {
            return;
        }

        if ($this->db->DBDriver === 'MySQLi') {
            try {
                $settings = $this->db->prefixTable('settings');
                $this->db->query("ALTER TABLE {$settings} DROP FOREIGN KEY fk_settings_updated_by");
            } catch (\Throwable $e) {}
        }
        if ($this->db->fieldExists('updated_by', 'settings')) {
            $this->forge->dropColumn('settings', 'updated_by');
        }
    }
}
