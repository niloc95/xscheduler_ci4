<?php

namespace Tests\Unit\Services;

use App\Models\SettingModel;
use App\Services\LocalizationSettingsService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class LocalizationSettingsServiceTest extends CIUnitTestCase
{
    protected function tearDown(): void
    {
        session()->destroy();

        parent::tearDown();
    }

    public function testGetTimezonePrefersConfiguredSettingAndSeedsSession(): void
    {
        session()->remove('client_timezone');
        session()->remove('client_timezone_offset');

        $settings = $this->createMock(SettingModel::class);
        $settings->expects($this->once())
            ->method('getByKeys')
            ->with([
                'localization.time_format',
                'localization.timezone',
                'localization.first_day',
                'localization.currency',
            ])
            ->willReturn([
                'localization.time_format' => '24h',
                'localization.timezone' => 'Europe/Paris',
                'localization.first_day' => 'Sunday',
                'localization.currency' => 'EUR',
            ]);

        $service = new LocalizationSettingsService($settings);

        $this->assertSame('Europe/Paris', $service->getTimezone());
        $this->assertSame('Europe/Paris', session('client_timezone'));
        $this->assertIsInt(session('client_timezone_offset'));
    }

    public function testTimeParsingFormattingAndDayMappingRespectTwelveHourSettings(): void
    {
        $settings = $this->createMock(SettingModel::class);
        $settings->expects($this->once())
            ->method('getByKeys')
            ->willReturn([
                'localization.time_format' => '12h',
                'localization.timezone' => 'UTC',
                'localization.first_day' => 'Monday',
                'localization.currency' => 'USD',
            ]);

        $service = new LocalizationSettingsService($settings);

        $this->assertTrue($service->isTwelveHour());
        $this->assertSame(1, $service->getFirstDayOfWeek());
        $this->assertSame('21:15:00', $service->normaliseTimeInput('9:15 PM'));
        $this->assertSame('09:15 PM', $service->formatTimeForDisplay('21:15:00'));
        $this->assertSame('21:15', $service->formatTimeForNativeInput('9:15 PM'));
        $this->assertSame(1275, $service->toMinutes('9:15 PM'));
        $this->assertSame('Use HH:MM AM/PM (e.g. 09:00 AM).', $service->describeExpectedFormat());
    }
}