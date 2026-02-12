<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class CreateNotificationDeliveryLogsTable extends MigrationBase
{
    public function up()
    {
        if ($this->db->tableExists($this->db->prefixTable('notification_delivery_logs'))) {
            return;
        }

        $this->forge->addField($this->sanitiseFields([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'business_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'default' => 1,
            ],
            'queue_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
            ],
            'correlation_id' => [
                'type' => 'VARCHAR',
                'constraint' => 64,
                'null' => true,
            ],
            'channel' => [
                'type' => 'ENUM',
                'constraint' => ['email', 'sms', 'whatsapp'],
            ],
            'event_type' => [
                'type' => 'VARCHAR',
                'constraint' => 64,
            ],
            'appointment_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
            ],
            'recipient' => [
                'type' => 'VARCHAR',
                'constraint' => 190,
                'null' => true,
            ],
            'provider' => [
                'type' => 'VARCHAR',
                'constraint' => 64,
                'null' => true,
            ],
            'status' => [
                'type' => 'ENUM',
                'constraint' => ['success', 'failed', 'cancelled', 'skipped'],
            ],
            'attempt' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 1,
            ],
            'error_message' => [
                'type' => 'TEXT',
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
        $this->forge->addKey(['business_id', 'created_at']);
        $this->forge->addKey(['queue_id']);
        $this->forge->addKey(['correlation_id']);
        $this->forge->addKey(['channel', 'event_type']);
        $this->forge->createTable('notification_delivery_logs');
    }

    public function down()
    {
        if ($this->db->tableExists($this->db->prefixTable('notification_delivery_logs'))) {
            $this->forge->dropTable('notification_delivery_logs');
        }
    }
}
