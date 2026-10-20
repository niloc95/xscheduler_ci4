<?php

namespace Tests\Unit\Services;

use App\Models\SettingModel;
use App\Services\BusinessHoursService;
use App\Services\LocalizationSettingsService;
use CodeIgniter\Test\CIUnitTestCase;
use DateTime;
use DateTimeZone;

/**
 * Unit tests for BusinessHoursService
 * 
 * Tests core business logic without database dependencies
 * Database integration should be tested via integration tests
 * 
 * @internal
 */
final class BusinessHoursServiceTest extends CIUnitTestCase
{
    protected BusinessHoursService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BusinessHoursService();
    }


    /**
     * formatHours follows the localization.time_format setting (default 24h).
     */
    public function testFormatHoursUsesTwentyFourHourFormatByDefault(): void
    {
        $service = new BusinessHoursService($this->localizationWithTimeFormat('24h'));

        $this->assertSame('09:00 - 17:00', $service->formatHours([
            'weekday' => 1,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ]));
    }

    /**
     * In 12h mode, midnight and noon render as 12:00 AM / 12:00 PM.
     */
    public function testFormatHoursHandlesNoonAndMidnightInTwelveHourFormat(): void
    {
        $service = new BusinessHoursService($this->localizationWithTimeFormat('12h'));

        $result = $service->formatHours([
            'weekday' => 1,
            'start_time' => '00:00:00',
            'end_time' => '12:00:00',
        ]);

        $this->assertStringContainsString('12:00 AM', $result); // Midnight
        $this->assertStringContainsString('12:00 PM', $result); // Noon
    }

    private function localizationWithTimeFormat(string $format): LocalizationSettingsService
    {
        $settings = $this->createMock(SettingModel::class);
        $settings->method('getByKeys')->willReturnCallback(
            static fn (array $keys): array => array_merge(
                array_fill_keys($keys, null),
                in_array('localization.time_format', $keys, true) ? ['localization.time_format' => $format] : []
            )
        );

        return new LocalizationSettingsService($settings);
    }

    /**
     * Test formatHours returns 'Closed' for missing times
     */
    public function testFormatHoursReturnsClosedForMissingTimes(): void
    {
        $hours = [
            'weekday' => 0,
            'start_time' => null,
            'end_time' => null
        ];
        
        $result = $this->service->formatHours($hours);
        
        $this->assertEquals('Closed', $result);
    }

    /**
     * Note: Database-dependent tests (validateAppointmentTime, getBusinessHoursForDate,
     * isWorkingDay, getWeeklyHours) should be tested in integration tests where
     * the database is properly seeded with business_hours data.
     */
}
