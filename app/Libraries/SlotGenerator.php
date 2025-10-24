<?php

namespace App\Libraries;

use App\Models\BusinessHourModel;
use App\Models\ProviderScheduleModel;
use App\Models\BlockedTimeModel;
use App\Models\AppointmentModel;
use App\Models\ServiceModel;

class SlotGenerator
{
    protected BusinessHourModel $businessHours;
    protected BlockedTimeModel $blockedTimes;
    protected AppointmentModel $appointments;
    protected ServiceModel $services;
    protected ProviderScheduleModel $providerSchedules;

    public function __construct()
    {
        $this->businessHours = new BusinessHourModel();
        $this->blockedTimes  = new BlockedTimeModel();
        $this->appointments  = new AppointmentModel();
        $this->services      = new ServiceModel();
        $this->providerSchedules = new ProviderScheduleModel();
    }

    /**
     * Get available slots for a provider/service/date
     * @return array [ ['start' => 'HH:MM', 'end' => 'HH:MM'] ] in local time
     */
    public function getAvailableSlots(int $providerId, int $serviceId, string $date): array
    {
        $service = $this->services->find($serviceId);
        if (!$service) return [];
        $duration = (int)($service['duration_min'] ?? 30);

        $dayName = strtolower(date('l', strtotime($date))); // monday ... sunday
        $providerSchedule = $this->providerSchedules->getActiveDay($providerId, $dayName);

        $breaks = [];
        if ($providerSchedule) {
            $dayStart = strtotime($date . ' ' . $providerSchedule['start_time']);
            $dayEnd   = strtotime($date . ' ' . $providerSchedule['end_time']);

            if (!empty($providerSchedule['break_start']) && !empty($providerSchedule['break_end'])) {
                $breaks[] = [
                    'start' => substr($providerSchedule['break_start'], 0, 5),
                    'end'   => substr($providerSchedule['break_end'], 0, 5),
                ];
            }
        } else {
            $weekday = (int) date('w', strtotime($date)); // 0=Sun
            $bh = $this->businessHours
                ->where([
                    'provider_id' => $providerId,
                    'weekday'     => $weekday,
                ])
                ->first();

            if (!$bh) {
                $this->businessHours->resetQuery();
                $bh = $this->businessHours
                    ->where([
                        'provider_id' => null,
                        'weekday'     => $weekday,
                    ])
                    ->first();
            }

            if (!$bh) {
                $this->businessHours->resetQuery();
                $bh = $this->businessHours
                    ->where([
                        'provider_id' => 0,
                        'weekday'     => $weekday,
                    ])
                    ->first();
            }

            if (!$bh) {
                return [];
            }

            $this->businessHours->resetQuery();

            if (!empty($bh['breaks_json'])) {
                $decoded = json_decode($bh['breaks_json'], true);
                if (is_array($decoded)) $breaks = $decoded;
            }

            $dayStart = strtotime($date . ' ' . $bh['start_time']);
            $dayEnd   = strtotime($date . ' ' . $bh['end_time']);
        }

        // Gather busy intervals (appointments + blocks)
        $busy = [];
        // Appointments (exclude cancelled)
        $appts = $this->appointments
            ->where('provider_id', $providerId)
            ->where('status !=', 'cancelled')
            ->where('start_time >=', date('Y-m-d H:i:s', $dayStart))
            ->where('start_time <=', date('Y-m-d H:i:s', $dayEnd))
            ->findAll();
        foreach ($appts as $a) {
            $busy[] = [strtotime($a['start_time']), strtotime($a['end_time'])];
        }
        // Blocked times
        $blocks = $this->blockedTimes
            ->where('provider_id', $providerId)
            ->where('start_time <=', date('Y-m-d H:i:s', $dayEnd))
            ->where('end_time >=', date('Y-m-d H:i:s', $dayStart))
            ->findAll();
        foreach ($blocks as $b) {
            $busy[] = [strtotime($b['start_time']), strtotime($b['end_time'])];
        }
        // Breaks from business hours JSON
        foreach ($breaks as $br) {
            if (!isset($br['start']) || !isset($br['end'])) continue;
            $busy[] = [strtotime($date . ' ' . $br['start']), strtotime($date . ' ' . $br['end'])];
        }

        // Normalize/merge busy intervals
        $busy = $this->mergeIntervals($busy);

        // Generate candidate slots
        $slots = [];
        $step = $duration * 60; // seconds
        for ($start = $dayStart; $start + $step <= $dayEnd; $start += $step) {
            $end = $start + $step;
            if (!$this->overlapsAny([$start, $end], $busy)) {
                $slots[] = [
                    'start' => date('H:i', $start),
                    'end'   => date('H:i', $end),
                ];
            }
        }
        return $slots;
    }

    protected function overlapsAny(array $interval, array $list): bool
    {
        [$s, $e] = $interval;
        foreach ($list as [$bs, $be]) {
            if ($s < $be && $e > $bs) return true; // overlap
        }
        return false;
    }

    protected function mergeIntervals(array $intervals): array
    {
        if (empty($intervals)) return [];
        usort($intervals, fn($a, $b) => $a[0] <=> $b[0]);
        $merged = [$intervals[0]];
        for ($i = 1; $i < count($intervals); $i++) {
            [$cs, $ce] = $merged[count($merged) - 1];
            [$ns, $ne] = $intervals[$i];
            if ($ns <= $ce) {
                $merged[count($merged) - 1][1] = max($ce, $ne);
            } else {
                $merged[] = [$ns, $ne];
            }
        }
        return $merged;
    }
}
