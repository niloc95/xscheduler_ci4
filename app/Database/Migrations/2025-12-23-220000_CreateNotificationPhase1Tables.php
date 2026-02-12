<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class CreateNotificationPhase1Tables extends MigrationBase
{
    public function up()
    {
        // business_notification_rules
        if (!$this->db->tableExists($this->db->prefixTable('business_notification_rules'))) {
            $this->forge->addField($this->sanitiseFields([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'business_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'default'    => 1,
                ],
                'event_type' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                ],
                'channel' => [
                    'type'       => 'ENUM',
                    'constraint' => ['email', 'sms', 'whatsapp'],
                ],
                'is_enabled' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 0,
                ],
                'reminder_offset_minutes' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'null'       => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => false,
                ],
                'updated_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]));

            $this->forge->addKey('id', true);
            $this->forge->addKey(['business_id', 'event_type']);
            $this->forge->addUniqueKey(['business_id', 'event_type', 'channel'], 'uniq_rule_business_event_channel');
            $this->forge->createTable('business_notification_rules');
        }

        // business_integrations
        if (!$this->db->tableExists($this->db->prefixTable('business_integrations'))) {
            $this->forge->addField($this->sanitiseFields([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'business_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'default'    => 1,
                ],
                'channel' => [
                    'type'       => 'ENUM',
                    'constraint' => ['email', 'sms', 'whatsapp'],
                ],
                'provider_name' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                    'null'       => true,
                ],
                'encrypted_config' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'is_active' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 0,
                ],
                'health_status' => [
                    'type'       => 'ENUM',
                    'constraint' => ['unknown', 'healthy', 'unhealthy'],
                    'default'    => 'unknown',
                ],
                'last_tested_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => false,
                ],
                'updated_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]));

            $this->forge->addKey('id', true);
            $this->forge->addKey(['business_id', 'channel']);
            $this->forge->addUniqueKey(['business_id', 'channel'], 'uniq_integration_business_channel');
            $this->forge->createTable('business_integrations');
        }

        // message_templates (phase-ready, not required in Phase 1)
        if (!$this->db->tableExists($this->db->prefixTable('message_templates'))) {
            $this->forge->addField($this->sanitiseFields([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'business_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'default'    => 1,
                ],
                'event_type' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                ],
                'channel' => [
                    'type'       => 'ENUM',
                    'constraint' => ['email', 'sms', 'whatsapp'],
                ],
                'provider' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                    'null'       => true,
                ],
                'provider_template_id' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 128,
                    'null'       => true,
                ],
                'locale' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 16,
                    'default'    => 'en',
                ],
                'subject' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                ],
                'body' => [
                    'type' => 'TEXT',
                    'null' => false,
                ],
                'is_active' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 1,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => false,
                ],
                'updated_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]));

            $this->forge->addKey('id', true);
            $this->forge->addKey(['business_id', 'event_type', 'channel']);
            $this->forge->createTable('message_templates');
        }
    }

    public function down()
    {
        if ($this->db->tableExists($this->db->prefixTable('message_templates'))) {
            $this->forge->dropTable('message_templates');
        }

        if ($this->db->tableExists($this->db->prefixTable('business_integrations'))) {
            $this->forge->dropTable('business_integrations');
        }

        if ($this->db->tableExists($this->db->prefixTable('business_notification_rules'))) {
            $this->forge->dropTable('business_notification_rules');
        }
    }
}
