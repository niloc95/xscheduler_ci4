<?php

namespace Tests\Unit\Services;

use App\Models\SettingModel;
use App\Services\CalendarConfigService;
use App\Services\LocalizationSettingsService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class CalendarConfigServiceTest extends CIUnitTestCase
{
    public function testGetBlockedPeriodsDecodesJsonAndRejectsInvalidPayloads(): void
    {
        $localization = $this->createMock(LocalizationSettingsService::class);
        $localization->method('isTwelveHour')->willReturn(false);
        $localization->method('getFirstDayOfWeek')->willReturn(0);
        $localization->method('getTimezone')->willReturn('UTC');

        $settings = $this->createMock(SettingModel::class);
        $settings->expects($this->once())
            ->method('getByKeys')
            ->willReturn([
                'business.blocked_periods' => '[{"start":"2026-04-01","end":"2026-04-02"}]',
            ]);

        $service = new CalendarConfigService($localization, $settings);

        $this->assertSame([
            ['start' => '2026-04-01', 'end' => '2026-04-02'],
        ], $service->getBlockedPeriods());

        $invalidSettings = $this->createMock(SettingModel::class);
        $invalidSettings->expects($this->once())
            ->method('getByKeys')
            ->willReturn([
                'business.blocked_periods' => '{invalid-json',
            ]);

        $invalidService = new CalendarConfigService($localization, $invalidSettings);

        $this->assertSame([], $invalidService->getBlockedPeriods());
    }

    public function testGetJavaScriptConfigCombinesLocalizedSettingsAndScopedBusinessHours(): void
    {
        $localization = $this->createMock(LocalizationSettingsService::class);
        $localization->method('isTwelveHour')->willReturn(true);
        $localization->method('getFirstDayOfWeek')->willReturn(1);
        $localization->method('getTimezone')->willReturn('Europe/Paris');

        $settings = $this->createMock(SettingModel::class);
        $settings->expects($this->once())
            ->method('getByKeys')
            ->willReturn([
                'localization.locale' => 'fr',
                'booking.day_start' => '07:30:00',
                'booking.day_end' => '18:15:00',
                'business.blocked_periods' => [
                    ['start' => '2026-05-01', 'end' => '2026-05-03'],
                ],
                'calendar.slot_duration' => 15,
                'calendar.default_view' => 'day',
                'calendar.show_weekends' => false,
            ]);

        $service = new TestCalendarConfigService(
            $localization,
            $settings,
            [
                [
                    'daysOfWeek' => [1],
                    'startTime' => '08:00',
                    'endTime' => '16:00',
                ],
            ]
        );

        $config = $service->getJavaScriptConfig(42);

        $this->assertSame(42, $service->capturedProviderId);
        $this->assertSame('day', $config['defaultView']);
        $this->assertSame(1, $config['firstDay']);
        $this->assertSame('00:15:00', $config['slotDuration']);
        $this->assertSame('07:30', $config['slotMinTime']);
        $this->assertSame('18:15', $config['slotMaxTime']);
        $this->assertSame('h:mm a', $config['timeFormat']);
        $this->assertFalse((bool) $config['showWeekends']);
        $this->assertSame('Europe/Paris', $config['timezone']);
        $this->assertSame('fr', $config['locale']);
        $this->assertSame([
            ['start' => '2026-05-01', 'end' => '2026-05-03'],
        ], $config['blockedPeriods']);
        $this->assertSame([
            [
                'daysOfWeek' => [1],
                'startTime' => '08:00',
                'endTime' => '16:00',
            ],
        ], $config['businessHours']);
    }
}

final class TestCalendarConfigService extends CalendarConfigService
{
    public ?int $capturedProviderId = null;

    public function __construct(
        LocalizationSettingsService $localization,
        SettingModel $settings,
        private readonly array $businessHours
    ) {
        parent::__construct($localization, $settings);
    }

    public function getBusinessHoursForCalendar(?int $providerId = null): array
    {
        $this->capturedProviderId = $providerId;

        return $this->businessHours;
    }
}