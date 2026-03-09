<?php

namespace App\Services\Calendar;

use App\Models\SettingModel;

/**
 * Shared provider working-hours resolver for day/today view services.
 */
trait ProviderWorkingHoursTrait
{
    /**
     * Resolve provider working hours for a day, with business-hours fallback.
     *
     * @return array{startTime:string,endTime:string,breakStart:?string,breakEnd:?string,source:string,isActive:bool}
     */
    protected function getProviderWorkingHours(int $providerId, int $dayOfWeek): array
    {
        // Placeholder provider should remain active on business-hours defaults.
        if ($providerId === 0) {
            $hours = $this->getBusinessHours();
            $hours['isActive'] = true;
            return $hours;
        }

        $schedule = $this->lookupProviderSchedule($providerId, $dayOfWeek);

        if ($schedule && !empty($schedule['is_active'])) {
            return [
                'startTime'  => substr((string) $schedule['start_time'], 0, 5),
                'endTime'    => substr((string) $schedule['end_time'], 0, 5),
                'breakStart' => !empty($schedule['break_start']) ? substr((string) $schedule['break_start'], 0, 5) : null,
                'breakEnd'   => !empty($schedule['break_end']) ? substr((string) $schedule['break_end'], 0, 5) : null,
                'source'     => 'provider_schedule',
                'isActive'   => true,
            ];
        }

        $hours = $this->getBusinessHours();
        $hours['isActive'] = false;
        return $hours;
    }

    /**
     * Business-hours fallback using canonical booking settings.
     *
     * @return array{startTime:string,endTime:string,breakStart:?string,breakEnd:?string,source:string,isActive:bool}
     */
    protected function getBusinessHours(): array
    {
        if (property_exists($this, 'timeGrid') && $this->timeGrid instanceof TimeGridService) {
            return [
                'startTime'  => $this->timeGrid->getDayStart(),
                'endTime'    => $this->timeGrid->getDayEnd(),
                'breakStart' => null,
                'breakEnd'   => null,
                'source'     => 'business_hours',
                'isActive'   => true,
            ];
        }

        $settings = new SettingModel();
        return [
            'startTime'  => (string) $settings->getValue('booking.day_start', '08:00'),
            'endTime'    => (string) $settings->getValue('booking.day_end', '17:00'),
            'breakStart' => $settings->getValue('booking.break_start', null),
            'breakEnd'   => $settings->getValue('booking.break_end', null),
            'source'     => 'business_hours',
            'isActive'   => true,
        ];
    }

    /**
     * Internal provider schedule lookup that supports both service property names.
     */
    private function lookupProviderSchedule(int $providerId, int $dayOfWeek): ?array
    {
        $model = null;
        if (property_exists($this, 'providerScheduleModel')) {
            $model = $this->providerScheduleModel;
        } elseif (property_exists($this, 'providerSchedule')) {
            $model = $this->providerSchedule;
        }

        if (!$model) {
            return null;
        }

        $row = $model
            ->where('provider_id', $providerId)
            ->where('day_of_week', $dayOfWeek)
            ->first();

        return is_array($row) ? $row : null;
    }
}
