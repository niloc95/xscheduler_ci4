<?php

namespace Tests\Unit\Services;

use App\Models\SettingModel;
use App\Services\LocalizationSettingsService;
use App\Services\ScheduleValidationService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class ScheduleValidationServiceTest extends CIUnitTestCase
{
    public function testValidateProviderScheduleNormalizesActiveRowsAndRejectsIncompleteBreaks(): void
    {
        $service = new ScheduleValidationService($this->buildLocalization('24h'));

        [$clean, $errors] = $service->validateProviderSchedule([
            'monday' => [
                'is_active' => '1',
                'start_time' => '9:00',
                'end_time' => '17:00',
                'break_start' => '12:00',
                'break_end' => '13:00',
            ],
            'tuesday' => [
                'is_active' => 'on',
                'start_time' => '09:00',
                'end_time' => '17:00',
                'break_start' => '12:30',
                'break_end' => '',
            ],
        ]);

        $this->assertSame([
            'monday' => [
                'is_active' => 1,
                'start_time' => '09:00:00',
                'end_time' => '17:00:00',
                'break_start' => '12:00:00',
                'break_end' => '13:00:00',
            ],
        ], $clean);
        $this->assertSame(
            'Provide both break start and end times. Use 24-hour HH:MM (e.g. 09:00).',
            $errors['tuesday'] ?? null
        );
    }

    public function testValidateAgainstBusinessHoursReportsOpeningAndBreakViolations(): void
    {
        $service = new ScheduleValidationService($this->buildLocalization('12h'));

        $result = $service->validateAgainstBusinessHours('08:30:00', '12:30:00', [
            'is_active' => true,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'break_start' => '12:00:00',
            'break_end' => '13:00:00',
        ]);

        $this->assertFalse($result['valid']);
        $this->assertSame([
            'Appointment starts before business hours (09:00 AM).',
            'Appointment overlaps with break time (12:00 PM - 01:00 PM).',
        ], $result['errors']);
    }

    private function buildLocalization(string $timeFormat): LocalizationSettingsService
    {
        $settings = $this->createMock(SettingModel::class);
        $settings->expects($this->once())
            ->method('getByKeys')
            ->willReturn([
                'localization.time_format' => $timeFormat,
                'localization.timezone' => 'UTC',
                'localization.first_day' => 'Monday',
                'localization.currency' => 'USD',
            ]);

        return new LocalizationSettingsService($settings);
    }
}