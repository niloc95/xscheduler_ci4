<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class CreateNotificationOptOutsTable extends MigrationBase
{
    public function up()
    {
        if ($this->db->tableExists($this->db->prefixTable('notification_opt_outs'))) {
            return;
        }

        $this->forge->addField([
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
            'channel' => [
                'type' => 'ENUM',
                'constraint' => ['email', 'sms', 'whatsapp'],
            ],
            'recipient' => [
                'type' => 'VARCHAR',
                'constraint' => 190,
            ],
            'reason' => [
                'type' => 'VARCHAR',
                'constraint' => 190,
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
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['business_id', 'channel', 'recipient'], 'uniq_opt_out');
        $this->forge->addKey(['business_id', 'channel']);
        $this->forge->createTable('notification_opt_outs');
    }

    public function down()
    {
        if ($this->db->tableExists($this->db->prefixTable('notification_opt_outs'))) {
            $this->forge->dropTable('notification_opt_outs');
        }
    }
}
