<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class AddCorrelationIdToNotificationQueue extends MigrationBase
{
    public function up()
    {
        $table = $this->db->prefixTable('notification_queue');
        if (!$this->db->tableExists($table)) {
            return;
        }

        $fields = $this->db->getFieldNames($table);
        if (in_array('correlation_id', $fields, true)) {
            return;
        }

        $this->forge->addColumn('notification_queue', [
            'correlation_id' => [
                'type' => 'VARCHAR',
                'constraint' => 64,
                'null' => true,
                'after' => 'idempotency_key',
            ],
        ]);

        $this->forge->addKey('correlation_id');
        // CI4 Forge won't addKey on existing table via addKey reliably across drivers.
        // Use raw query for the index if possible.
        try {
            $this->db->query('CREATE INDEX idx_notification_queue_correlation_id ON ' . $table . ' (correlation_id)');
        } catch (\Throwable $e) {
            // ignore if index exists or driver doesn't support
        }
    }

    public function down()
    {
        $table = $this->db->prefixTable('notification_queue');
        if (!$this->db->tableExists($table)) {
            return;
        }

        $fields = $this->db->getFieldNames($table);
        if (!in_array('correlation_id', $fields, true)) {
            return;
        }

        try {
            $this->db->query('DROP INDEX idx_notification_queue_correlation_id ON ' . $table);
        } catch (\Throwable $e) {
            // ignore
        }

        $this->forge->dropColumn('notification_queue', 'correlation_id');
    }
}
