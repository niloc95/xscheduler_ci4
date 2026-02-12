<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class AddCorrelationIdToNotificationQueue extends MigrationBase
{
    public function up()
    {
        if (!$this->db->tableExists('notification_queue')) {
            return;
        }

        $prefixed = $this->db->prefixTable('notification_queue');
        $fields   = $this->db->getFieldNames($prefixed);
        if (in_array('correlation_id', $fields, true)) {
            return;
        }

        $this->forge->addColumn('notification_queue', [
            'correlation_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => true,
            ],
        ]);

        $this->createIndexIfMissing('notification_queue', 'idx_notification_queue_correlation_id', ['correlation_id']);
    }

    public function down()
    {
        if (!$this->db->tableExists('notification_queue')) {
            return;
        }

        $prefixed = $this->db->prefixTable('notification_queue');
        $fields   = $this->db->getFieldNames($prefixed);
        if (!in_array('correlation_id', $fields, true)) {
            return;
        }

        $this->dropIndexIfExists('notification_queue', 'idx_notification_queue_correlation_id');

        $this->forge->dropColumn('notification_queue', 'correlation_id');
    }
}
