<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

/**
 * Add a three-column composite covering index on xs_appointments for the
 * ConflictService overlap query.
 *
 * ConflictService::getConflictingAppointments() filters:
 *   WHERE provider_id = ? AND status != 'cancelled'
 *     AND ((start_at <= ? AND end_at > ?)
 *       OR (start_at <  ? AND end_at >= ?)
 *       OR (start_at >= ? AND end_at <= ?))
 *
 * The existing idx_appts_provider_start (provider_id, start_at) narrows to a
 * provider, but MySQL must then hit the table for each row to check end_at.
 * Adding end_at as the third column lets the engine satisfy all three overlap
 * conditions from the index alone (covering index), eliminating table row
 * lookups for the end_at comparison.
 *
 * The idx_appts_provider_start index is dropped here because
 * idx_appts_provider_start_end is a strict superset — MySQL will use the wider
 * index for any query that previously used the narrower one.
 */
class Migration_AddConflictQueryIndexToAppointments extends MigrationBase
{
    public function up(): void
    {
        if (!$this->db->tableExists('appointments')) {
            return;
        }

        // Replace the two-column provider+start index with a three-column
        // covering index that also includes end_at.
        $this->dropIndexIfExists('appointments', 'idx_appts_provider_start');
        $this->createIndexIfMissing('appointments', 'idx_appts_provider_start_end', ['provider_id', 'start_at', 'end_at']);
    }

    public function down(): void
    {
        if (!$this->db->tableExists('appointments')) {
            return;
        }

        $this->dropIndexIfExists('appointments', 'idx_appts_provider_start_end');
        $this->createIndexIfMissing('appointments', 'idx_appts_provider_start', ['provider_id', 'start_at']);
    }
}
