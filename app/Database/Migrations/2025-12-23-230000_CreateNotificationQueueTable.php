<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class CreateNotificationQueueTable extends MigrationBase
{
    public function up()
    {
        if ($this->db->tableExists($this->db->prefixTable('notification_queue'))) {
            return;
        }

        $this->forge->addField([
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
            'event_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
            ],
            'appointment_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['queued', 'sent', 'failed', 'cancelled'],
                'default'    => 'queued',
            ],
            'attempts' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
            'max_attempts' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 5,
            ],
            'run_after' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'locked_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'lock_token' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => true,
            ],
            'last_error' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'sent_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'idempotency_key' => [
                'type'       => 'VARCHAR',
                'constraint' => 128,
                'null'       => false,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey(['status', 'run_after']);
        $this->forge->addKey(['business_id', 'channel', 'event_type']);
        $this->forge->addUniqueKey(['business_id', 'idempotency_key'], 'uniq_notification_queue_idem');
        $this->forge->createTable('notification_queue');
    }

    public function down()
    {
        if ($this->db->tableExists($this->db->prefixTable('notification_queue'))) {
            $this->forge->dropTable('notification_queue');
        }
    }
}
