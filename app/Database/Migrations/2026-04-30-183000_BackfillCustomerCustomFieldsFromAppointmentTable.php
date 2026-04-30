<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class BackfillCustomerCustomFieldsFromAppointmentTable extends MigrationBase
{
    public function up()
    {
        $customersTable = $this->db->prefixTable('customers');
        $appointmentsTable = $this->db->prefixTable('appointments');
        $appointmentCustomFieldsTable = $this->db->prefixTable('appointment_custom_fields');

        if (!$this->db->tableExists($customersTable)
            || !$this->db->tableExists($appointmentsTable)
            || !$this->db->tableExists($appointmentCustomFieldsTable)
            || !$this->db->fieldExists('custom_fields', 'customers')) {
            return;
        }

        $customerRows = $this->db->table('customers')
            ->select('id, custom_fields')
            ->get()
            ->getResultArray();

        if ($customerRows === []) {
            return;
        }

        $customerPayloads = [];
        foreach ($customerRows as $row) {
            $customerId = (int) ($row['id'] ?? 0);
            if ($customerId <= 0) {
                continue;
            }

            $customerPayloads[$customerId] = $this->decodeCustomFields($row['custom_fields'] ?? null);
        }

        $rows = $this->db->query(
            "SELECT a.customer_id, acf.field_key, acf.value
             FROM `{$appointmentCustomFieldsTable}` acf
             INNER JOIN `{$appointmentsTable}` a ON a.id = acf.appointment_id
             WHERE a.customer_id IS NOT NULL
               AND acf.field_key IS NOT NULL AND acf.field_key != ''
               AND acf.value IS NOT NULL AND acf.value != ''
             ORDER BY a.customer_id ASC,
                      COALESCE(a.updated_at, a.start_at, a.created_at) DESC,
                      COALESCE(acf.updated_at, acf.created_at) DESC,
                      acf.id DESC"
        )->getResultArray();

        if ($rows === []) {
            return;
        }

        $dirtyCustomerIds = [];

        foreach ($rows as $row) {
            $customerId = (int) ($row['customer_id'] ?? 0);
            $fieldKey = trim((string) ($row['field_key'] ?? ''));
            $value = trim((string) ($row['value'] ?? ''));

            if ($customerId <= 0 || $fieldKey === '' || $value === '') {
                continue;
            }

            if (!array_key_exists($customerId, $customerPayloads)) {
                $customerPayloads[$customerId] = [];
            }

            if (array_key_exists($fieldKey, $customerPayloads[$customerId]) && trim((string) $customerPayloads[$customerId][$fieldKey]) !== '') {
                continue;
            }

            $customerPayloads[$customerId][$fieldKey] = $value;
            $dirtyCustomerIds[$customerId] = true;
        }

        if ($dirtyCustomerIds === []) {
            return;
        }

        $builder = $this->db->table('customers');
        $now = date('Y-m-d H:i:s');

        foreach (array_keys($dirtyCustomerIds) as $customerId) {
            $builder->where('id', $customerId)->update([
                'custom_fields' => $this->encodeCustomFields($customerPayloads[$customerId]),
                'updated_at' => $now,
            ]);
        }
    }

    public function down()
    {
        // Data repair migration intentionally has no destructive rollback.
    }

    /**
     * @return array<string, string>
     */
    private function decodeCustomFields(mixed $value): array
    {
        if (is_array($value)) {
            $decoded = $value;
        } elseif (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);
        } else {
            return [];
        }

        if (!is_array($decoded)) {
            return [];
        }

        $fields = [];
        foreach ($decoded as $fieldKey => $fieldValue) {
            $normalizedKey = trim((string) $fieldKey);
            if ($normalizedKey === '') {
                continue;
            }

            $fields[$normalizedKey] = trim((string) $fieldValue);
        }

        return $fields;
    }

    /**
     * @param array<string, string> $fields
     */
    private function encodeCustomFields(array $fields): string
    {
        $encoded = json_encode($fields === [] ? (object) [] : $fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encoded === false ? '{}' : $encoded;
    }
}