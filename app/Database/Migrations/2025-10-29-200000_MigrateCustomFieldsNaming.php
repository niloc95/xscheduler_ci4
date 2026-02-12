<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

/**
 * Migrate Custom Fields Naming Convention
 * 
 * Converts existing custom_fields JSON data from old format (field_1, field_2, etc.)
 * to new consistent format (custom_field_1, custom_field_2, etc.) to match
 * CustomerManagement controller and BookingSettingsService.
 * 
 * Old format: {"field_1":"value","field_2":"value"}
 * New format: {"custom_field_1":"value","custom_field_2":"value"}
 */
class MigrateCustomFieldsNaming extends MigrationBase
{
    public function up()
    {
        // Guard: skip if customers table doesn't exist yet
        if (!$this->db->tableExists('customers')) {
            return;
        }

        // Get all customers with custom_fields data
        $builder = $this->db->table('customers');
        $customers = $builder->select('id, custom_fields')
                             ->where('custom_fields IS NOT NULL')
                             ->where('custom_fields !=', '')
                             ->get()
                             ->getResultArray();

        $updated = 0;
        $skipped = 0;

        foreach ($customers as $customer) {
            $customFields = json_decode($customer['custom_fields'], true);
            
            if (!is_array($customFields) || empty($customFields)) {
                $skipped++;
                continue;
            }

            // Check if already using new format
            $hasNewFormat = false;
            $hasOldFormat = false;
            
            foreach (array_keys($customFields) as $key) {
                if (strpos($key, 'custom_field_') === 0) {
                    $hasNewFormat = true;
                }
                if (preg_match('/^field_\d+$/', $key)) {
                    $hasOldFormat = true;
                }
            }

            // Skip if already in new format
            if ($hasNewFormat && !$hasOldFormat) {
                $skipped++;
                continue;
            }

            // Convert old format to new format
            $newCustomFields = [];
            foreach ($customFields as $key => $value) {
                // Convert field_1 → custom_field_1, field_2 → custom_field_2, etc.
                if (preg_match('/^field_(\d+)$/', $key, $matches)) {
                    $newKey = 'custom_field_' . $matches[1];
                    $newCustomFields[$newKey] = $value;
                } else {
                    // Keep any other keys as-is
                    $newCustomFields[$key] = $value;
                }
            }

            // Update the record
            $builder->where('id', $customer['id'])
                   ->update(['custom_fields' => json_encode($newCustomFields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
            
            $updated++;
        }

        log_message('info', "[MigrateCustomFieldsNaming] Migration complete: {$updated} records updated, {$skipped} records skipped.");
    }

    public function down()
    {
        // Guard: skip if customers table doesn't exist yet
        if (!$this->db->tableExists('customers')) {
            return;
        }

        // Get all customers with custom_fields data
        $builder = $this->db->table('customers');
        $customers = $builder->select('id, custom_fields')
                             ->where('custom_fields IS NOT NULL')
                             ->where('custom_fields !=', '')
                             ->get()
                             ->getResultArray();

        $reverted = 0;

        foreach ($customers as $customer) {
            $customFields = json_decode($customer['custom_fields'], true);
            
            if (!is_array($customFields) || empty($customFields)) {
                continue;
            }

            // Convert new format back to old format
            $oldCustomFields = [];
            foreach ($customFields as $key => $value) {
                // Convert custom_field_1 → field_1, custom_field_2 → field_2, etc.
                if (preg_match('/^custom_field_(\d+)$/', $key, $matches)) {
                    $oldKey = 'field_' . $matches[1];
                    $oldCustomFields[$oldKey] = $value;
                } else {
                    // Keep any other keys as-is
                    $oldCustomFields[$key] = $value;
                }
            }

            // Update the record
            $builder->where('id', $customer['id'])
                   ->update(['custom_fields' => json_encode($oldCustomFields)]);
            
            $reverted++;
        }

        log_message('info', "[MigrateCustomFieldsNaming] Rollback complete: {$reverted} records reverted.");
    }
}
