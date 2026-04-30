<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class CreateAppointmentCustomFieldsTable extends MigrationBase
{
    public function up()
    {
        if (!$this->db->tableExists($this->db->prefixTable('appointment_custom_fields'))) {
            $this->forge->addField($this->sanitiseFields([
                'id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'appointment_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                ],
                'field_key' => [
                    'type' => 'VARCHAR',
                    'constraint' => 120,
                ],
                'value' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => false,
                ],
                'updated_at' => [
                    'type' => 'DATETIME',
                    'null' => false,
                ],
            ]));

            $this->forge->addKey('id', true);
            $this->forge->addKey('appointment_id');
            $this->forge->addKey('field_key');
            $this->forge->addUniqueKey(['appointment_id', 'field_key'], 'idx_appt_custom_field_unique');
            $this->forge->addForeignKey('appointment_id', 'appointments', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('appointment_custom_fields');
        }

        $this->backfillLegacyCustomerCustomFields();
        $this->seedSensitiveSettings();
    }

    public function down()
    {
        if ($this->db->tableExists($this->db->prefixTable('appointment_custom_fields'))) {
            $this->forge->dropTable('appointment_custom_fields', true, true);
        }

        if ($this->db->tableExists($this->db->prefixTable('settings'))) {
            $keys = [];
            for ($i = 1; $i <= 6; $i++) {
                $keys[] = "booking.custom_field_{$i}_sensitive";
            }

            $this->db->table('settings')->whereIn('setting_key', $keys)->delete();
        }
    }

    private function backfillLegacyCustomerCustomFields(): void
    {
        if (!$this->db->tableExists($this->db->prefixTable('customers')) || !$this->db->fieldExists('custom_fields', 'customers')) {
            return;
        }

        $appointmentTable = $this->db->prefixTable('appointments');
        $customerTable = $this->db->prefixTable('customers');
        $customTable = $this->db->prefixTable('appointment_custom_fields');

        $rows = $this->db->query(
            "SELECT a.id AS appointment_id, c.custom_fields
             FROM `{$appointmentTable}` a
             INNER JOIN `{$customerTable}` c ON c.id = a.customer_id
             WHERE c.custom_fields IS NOT NULL AND c.custom_fields != ''"
        )->getResultArray();

        $now = date('Y-m-d H:i:s');

        foreach ($rows as $row) {
            $appointmentId = (int) ($row['appointment_id'] ?? 0);
            $json = (string) ($row['custom_fields'] ?? '');
            if ($appointmentId <= 0 || $json === '') {
                continue;
            }

            $decoded = json_decode($json, true);
            if (!is_array($decoded)) {
                continue;
            }

            foreach ($decoded as $fieldKey => $value) {
                $fieldKey = trim((string) $fieldKey);
                $stringValue = trim((string) $value);
                if ($fieldKey === '' || $stringValue === '') {
                    continue;
                }

                $this->db->query(
                    "INSERT INTO `{$customTable}` (`appointment_id`, `field_key`, `value`, `created_at`, `updated_at`)
                     VALUES (?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE `id` = `id`",
                    [$appointmentId, $fieldKey, $stringValue, $now, $now]
                );
            }
        }
    }

    private function seedSensitiveSettings(): void
    {
        if (!$this->db->tableExists($this->db->prefixTable('settings'))) {
            return;
        }

        $settingsTable = $this->db->prefixTable('settings');

        for ($i = 1; $i <= 6; $i++) {
            $key = "booking.custom_field_{$i}_sensitive";

            $exists = $this->db->query(
                "SELECT id FROM `{$settingsTable}` WHERE setting_key = ? LIMIT 1",
                [$key]
            )->getFirstRow();

            if ($exists) {
                continue;
            }

            $this->db->query(
                "INSERT INTO `{$settingsTable}` (`setting_key`, `setting_value`, `setting_type`, `created_at`, `updated_at`)
                 VALUES (?, '0', 'boolean', NOW(), NOW())",
                [$key]
            );
        }
    }
}
