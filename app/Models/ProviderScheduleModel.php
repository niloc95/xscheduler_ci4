<?php

namespace App\Models;

use CodeIgniter\Model;
use App\Services\LocalizationSettingsService;

class ProviderScheduleModel extends Model
{
    protected $table            = 'xs_provider_schedules';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    protected $allowedFields = [
        'provider_id',
        'day_of_week',
        'start_time',
        'end_time',
        'break_start',
        'break_end',
        'is_active',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    private ?LocalizationSettingsService $localization = null;

    protected $validationRules = [
        'provider_id' => 'required|integer',
        'day_of_week' => 'required|in_list[monday,tuesday,wednesday,thursday,friday,saturday,sunday]',
    'start_time'  => 'required|valid_date[H:i:s]',
    'end_time'    => 'required|valid_date[H:i:s]',
    'break_start' => 'permit_empty|valid_date[H:i:s]',
    'break_end'   => 'permit_empty|valid_date[H:i:s]',
        'is_active'   => 'in_list[0,1]',
    ];

    /**
     * Get weekly schedule grouped by day for a provider.
     */
    public function getByProvider(int $providerId): array
    {
        $rows = $this->where('provider_id', $providerId)
            ->orderBy('id', 'ASC')
            ->findAll();

        $schedule = [];
        foreach ($rows as $row) {
            $schedule[$row['day_of_week']] = $row;
        }

        return $schedule;
    }

    /**
     * Replace the provider schedule with provided day entries.
     *
     * @param int   $providerId Provider identifier.
     * @param array $entries    Array of rows keyed by day_of_week.
     */
    public function saveSchedule(int $providerId, array $entries): bool
    {
        $this->db->transStart();

        $this->where('provider_id', $providerId)->delete();

        foreach ($entries as $day => $data) {
            if (empty($data['is_active'])) {
                continue;
            }

            $start = $this->normaliseTime($data['start_time'] ?? null);
            $end   = $this->normaliseTime($data['end_time'] ?? null);
            $breakStart = $this->normaliseTime($data['break_start'] ?? null);
            $breakEnd   = $this->normaliseTime($data['break_end'] ?? null);

            $record = [
                'provider_id' => $providerId,
                'day_of_week' => $day,
                'start_time'  => $start,
                'end_time'    => $end,
                'break_start' => $breakStart,
                'break_end'   => $breakEnd,
                'is_active'   => (int)($data['is_active'] ?? 0),
            ];

            $this->insert($record, false);
        }

        $this->db->transComplete();

        return $this->db->transStatus();
    }

    /**
     * Delete all schedule entries for a provider.
     */
    public function deleteByProvider(int $providerId): bool
    {
        return (bool) $this->where('provider_id', $providerId)->delete();
    }

    /**
     * Find a single active schedule row for a provider/day.
     */
    public function getActiveDay(int $providerId, string $dayOfWeek): ?array
    {
        return $this->where('provider_id', $providerId)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', 1)
            ->first();
    }

    private function normaliseTime(?string $time): ?string
    {
        return $this->getLocalization()->normaliseTimeInput($time);
    }

    private function getLocalization(): LocalizationSettingsService
    {
        if ($this->localization === null) {
            $this->localization = new LocalizationSettingsService();
        }

        return $this->localization;
    }
}
