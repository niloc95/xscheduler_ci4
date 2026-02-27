<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class HardenAppointmentsSchema extends MigrationBase
{
    public function up(): void
    {
        if (!$this->db->tableExists('appointments')) {
            return;
        }

        if (!$this->db->fieldExists('customer_id', 'appointments')) {
            return;
        }

        $db = $this->db;
        $appointmentsTable = $db->prefixTable('appointments');
        $customersTable = $db->prefixTable('customers');

        $legacyEmail = 'legacy-appointments@webschedulr.invalid';
        $legacyCustomerId = null;

        $nullCustomerCount = $db->query(
            "SELECT COUNT(*) AS c FROM `{$appointmentsTable}` WHERE customer_id IS NULL OR customer_id = 0"
        )->getFirstRow();

        if ($nullCustomerCount && (int) ($nullCustomerCount->c ?? 0) > 0) {
            $existing = $db->query(
                "SELECT id FROM `{$customersTable}` WHERE email = ? LIMIT 1",
                [$legacyEmail]
            )->getFirstRow();

            if ($existing && !empty($existing->id)) {
                $legacyCustomerId = (int) $existing->id;
            } else {
                $now = date('Y-m-d H:i:s');
                $db->query(
                    "INSERT INTO `{$customersTable}` (first_name, last_name, email, phone, address, notes, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                    ['Legacy', 'Customer', $legacyEmail, null, null, 'Auto-created to backfill legacy appointments.', $now, $now]
                );
                $legacyCustomerId = (int) $db->insertID();
            }

            if ($legacyCustomerId > 0) {
                $db->query(
                    "UPDATE `{$appointmentsTable}` SET customer_id = ? WHERE customer_id IS NULL OR customer_id = 0",
                    [$legacyCustomerId]
                );
            }
        }

        $this->forge->modifyColumn('appointments', $this->sanitiseFields([
            'customer_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
        ]));

        if ($this->db->fieldExists('start_at', 'appointments')) {
            $this->createIndexIfMissing('appointments', 'idx_appts_provider_start', ['provider_id', 'start_at']);
            $this->createIndexIfMissing('appointments', 'idx_appts_start_at', ['start_at']);
            $this->createIndexIfMissing('appointments', 'idx_appts_status', ['status']);
        }

        $this->dropIndexIfExists('appointments', 'idx_provider_start_status');
        $this->dropIndexIfExists('appointments', 'idx_start_end_time');
        $this->dropIndexIfExists('appointments', 'idx_status_start');
    }

    public function down(): void
    {
        if (!$this->db->tableExists('appointments')) {
            return;
        }

        $this->dropIndexIfExists('appointments', 'idx_appts_start_at');
        $this->dropIndexIfExists('appointments', 'idx_appts_status');

        $this->forge->modifyColumn('appointments', $this->sanitiseFields([
            'customer_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
        ]));

        if ($this->db->fieldExists('start_at', 'appointments')) {
            $this->createIndexIfMissing('appointments', 'idx_provider_start_status', ['provider_id', 'start_at', 'status']);
            $this->createIndexIfMissing('appointments', 'idx_start_end_time', ['start_at', 'end_at']);
            $this->createIndexIfMissing('appointments', 'idx_status_start', ['status', 'start_at']);
        }
    }
}
