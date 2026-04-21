<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class AddReminderMetadataToNotificationQueue extends MigrationBase
{
    public function up(): void
    {
        if (!$this->db->tableExists('notification_queue')) {
            return;
        }

        $table = $this->db->prefixTable('notification_queue');
        $fields = $this->db->getFieldNames($table);
        $columns = [];

        if (!in_array('reminder_offset_minutes', $fields, true)) {
            $columns['reminder_offset_minutes'] = [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
            ];
        }

        if (!in_array('schedule_fingerprint', $fields, true)) {
            $columns['schedule_fingerprint'] = [
                'type' => 'VARCHAR',
                'constraint' => 40,
                'null' => true,
            ];
        }

        if ($columns !== []) {
            $this->forge->addColumn('notification_queue', $columns);
        }

        if ($this->db->fieldExists('reminder_offset_minutes', $table)) {
            $this->createIndexIfMissing(
                'notification_queue',
                'idx_nq_reminder_lookup',
                ['appointment_id', 'event_type', 'reminder_offset_minutes']
            );
        }
    }

    public function down(): void
    {
        if (!$this->db->tableExists('notification_queue')) {
            return;
        }

        $table = $this->db->prefixTable('notification_queue');

        $this->dropIndexIfExists('notification_queue', 'idx_nq_reminder_lookup');

        if ($this->db->fieldExists('schedule_fingerprint', $table)) {
            $this->forge->dropColumn('notification_queue', 'schedule_fingerprint');
        }

        if ($this->db->fieldExists('reminder_offset_minutes', $table)) {
            $this->forge->dropColumn('notification_queue', 'reminder_offset_minutes');
        }
    }
}