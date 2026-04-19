<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class AddReminderOffsetsJsonToNotificationRules extends MigrationBase
{
    public function up(): void
    {
        $table = $this->db->prefixTable('business_notification_rules');
        if (!$this->db->tableExists($table)) {
            return;
        }

        $fields = $this->db->getFieldNames($table);
        if (in_array('reminder_offsets_json', $fields, true)) {
            return;
        }

        $this->forge->addColumn('business_notification_rules', [
            'reminder_offsets_json' => [
                'type' => 'TEXT',
                'null' => true,
                'after' => 'reminder_offset_minutes',
            ],
        ]);
    }

    public function down(): void
    {
        $table = $this->db->prefixTable('business_notification_rules');
        if (!$this->db->tableExists($table)) {
            return;
        }

        $fields = $this->db->getFieldNames($table);
        if (!in_array('reminder_offsets_json', $fields, true)) {
            return;
        }

        $this->forge->dropColumn('business_notification_rules', 'reminder_offsets_json');
    }
}
