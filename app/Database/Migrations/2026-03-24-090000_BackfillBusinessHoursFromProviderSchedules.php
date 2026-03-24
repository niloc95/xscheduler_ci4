<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;
use App\Models\BusinessHourModel;

class BackfillBusinessHoursFromProviderSchedules extends MigrationBase
{
    public function up(): void
    {
        if (!$this->db->tableExists('provider_schedules') || !$this->db->tableExists('business_hours')) {
            return;
        }

        $scheduleTable = $this->db->prefixTable('provider_schedules');

        $scheduleRows = $this->db->query(
            "SELECT provider_id, day_of_week, start_time, end_time, break_start, break_end, is_active
             FROM `{$scheduleTable}`
             ORDER BY provider_id ASC, id ASC"
        )->getResultArray();

        if ($scheduleRows === []) {
            return;
        }

        $entriesByProvider = [];

        foreach ($scheduleRows as $row) {
            if (empty($row['provider_id'])) {
                continue;
            }

            $entriesByProvider[(int) $row['provider_id']][(string) $row['day_of_week']] = [
                'is_active' => (int) ($row['is_active'] ?? 0),
                'start_time' => $row['start_time'] ?? null,
                'end_time' => $row['end_time'] ?? null,
                'break_start' => $row['break_start'] ?? null,
                'break_end' => $row['break_end'] ?? null,
            ];
        }

        if ($entriesByProvider === []) {
            return;
        }

        $businessHourModel = new BusinessHourModel();

        $this->db->transStart();

        foreach ($entriesByProvider as $providerId => $entries) {
            $businessHourModel->syncFromProviderSchedule((int) $providerId, $entries);
        }

        $this->db->transComplete();
    }

    public function down(): void
    {
        // Irreversible data backfill.
    }
}