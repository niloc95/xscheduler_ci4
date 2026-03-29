<?php

namespace Tests\Unit\Services;

use App\Models\BusinessHourModel;
use App\Models\SettingModel;
use App\Services\BookingSettingsService;
use App\Services\CalendarConfigService;
use App\Services\LocalizationSettingsService;
use App\Services\Settings\GeneralSettingsService;
use App\Services\Settings\SettingsApiService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class SettingsApiServiceTest extends CIUnitTestCase
{
    public function testGetSettingsWithoutPrefixUsesTypedAllSettingsMap(): void
    {
        $settingModel = $this->createMock(SettingModel::class);
        $settingModel->expects($this->once())
            ->method('getAllAsMap')
            ->willReturn([
                'general.company_name' => 'WebScheduler',
                'booking.show_phone' => true,
            ]);

        $service = new SettingsApiService(
            $settingModel,
            $this->createMock(CalendarConfigService::class),
            $this->createMock(LocalizationSettingsService::class),
            $this->createMock(BookingSettingsService::class),
            $this->createMock(BusinessHourModel::class),
            $this->createMock(GeneralSettingsService::class)
        );

        $result = $service->getSettings();

        $this->assertSame('WebScheduler', $result['general.company_name']);
        $this->assertTrue($result['booking.show_phone']);
    }

    public function testGetLocalizationReturnsCompatibilityKeys(): void
    {
        $calendarConfigService = $this->createMock(CalendarConfigService::class);
        $calendarConfigService->expects($this->once())
            ->method('getFirstDayOfWeek')
            ->willReturn(1);

        $localizationSettingsService = $this->createMock(LocalizationSettingsService::class);
        $localizationSettingsService->expects($this->once())
            ->method('getTimezone')
            ->willReturn('Europe/Amsterdam');
        $localizationSettingsService->expects($this->once())
            ->method('getTimeFormat')
            ->willReturn('12h');
        $localizationSettingsService->expects($this->once())
            ->method('isTwelveHour')
            ->willReturn(true);
        $localizationSettingsService->expects($this->once())
            ->method('getContext')
            ->willReturn(['currency' => 'EUR']);

        $service = new SettingsApiService(
            $this->createMock(SettingModel::class),
            $calendarConfigService,
            $localizationSettingsService,
            $this->createMock(BookingSettingsService::class),
            $this->createMock(BusinessHourModel::class),
            $this->createMock(GeneralSettingsService::class)
        );

        $result = $service->getLocalization();

        $this->assertSame('Europe/Amsterdam', $result['timezone']);
        $this->assertSame('12h', $result['timeFormat']);
        $this->assertTrue($result['is12Hour']);
        $this->assertSame(1, $result['firstDayOfWeek']);
        $this->assertSame(1, $result['first_day_of_week']);
        $this->assertSame('EUR', $result['context']['currency']);
    }

    public function testGetBusinessHoursPayloadUsesProviderTemplateWhenDefaultRowsMissing(): void
    {
        $service = new class extends SettingsApiService {
            protected function getDefaultBusinessHourRows(): array
            {
                return [];
            }

            protected function getProviderTemplateBusinessHourRows(): array
            {
                return [
                    [
                        'weekday' => 1,
                        'start_time' => '08:30:00',
                        'end_time' => '17:30:00',
                        'breaks_json' => '[{"start":"12:00","end":"13:00"}]',
                    ],
                ];
            }
        };

        $payload = $service->getBusinessHoursPayload();

        $this->assertSame([], $payload['meta']);
        $this->assertTrue($payload['data']['monday']['isWorkingDay']);
        $this->assertSame('08:30:00', $payload['data']['monday']['startTime']);
        $this->assertSame([['start' => '12:00', 'end' => '13:00']], $payload['data']['monday']['breaks']);
        $this->assertFalse($payload['data']['sunday']['isWorkingDay']);
    }

    public function testGetBusinessHoursPayloadFallsBackToWeekdayDefaultsOnFailure(): void
    {
        $service = new class extends SettingsApiService {
            protected function getDefaultBusinessHourRows(): array
            {
                throw new \RuntimeException('database unavailable');
            }
        };

        $payload = $service->getBusinessHoursPayload();

        $this->assertSame(['fallback' => true], $payload['meta']);
        $this->assertTrue($payload['data']['monday']['isWorkingDay']);
        $this->assertFalse($payload['data']['sunday']['isWorkingDay']);
        $this->assertSame('09:00:00', $payload['data']['friday']['startTime']);
    }

    public function testUpdateSettingsIgnoresTransportKeysAndCountsPersistedRows(): void
    {
        $calls = [];
        $settingModel = $this->createMock(SettingModel::class);
        $settingModel->expects($this->exactly(2))
            ->method('upsert')
            ->willReturnCallback(static function (string $key, $value, string $type, int $userId) use (&$calls): bool {
                $calls[] = [
                    'key' => $key,
                    'value' => $value,
                    'type' => $type,
                    'userId' => $userId,
                ];

                return true;
            });

        $service = new SettingsApiService(
            $settingModel,
            $this->createMock(CalendarConfigService::class),
            $this->createMock(LocalizationSettingsService::class),
            $this->createMock(BookingSettingsService::class),
            $this->createMock(BusinessHourModel::class),
            $this->createMock(GeneralSettingsService::class)
        );

        $result = $service->updateSettings([
            'general.company_name' => 'WebScheduler',
            'booking.fields' => ['email' => ['display' => true]],
            'csrf_test_name' => 'skip-me',
            'form_source' => 'settings-ui',
        ], 7);

        $this->assertSame(2, $result);
        $this->assertSame([
            [
                'key' => 'general.company_name',
                'value' => 'WebScheduler',
                'type' => 'string',
                'userId' => 7,
            ],
            [
                'key' => 'booking.fields',
                'value' => ['email' => ['display' => true]],
                'type' => 'json',
                'userId' => 7,
            ],
        ], $calls);
    }

    public function testUploadLogoDelegatesToGeneralSettingsService(): void
    {
        $generalSettingsService = $this->createMock(GeneralSettingsService::class);
        $generalSettingsService->expects($this->once())
            ->method('uploadLogoForApi')
            ->with(null, 9)
            ->willReturn([
                'status' => 'validation_error',
                'message' => 'No logo file received.',
            ]);

        $service = new SettingsApiService(
            $this->createMock(SettingModel::class),
            $this->createMock(CalendarConfigService::class),
            $this->createMock(LocalizationSettingsService::class),
            $this->createMock(BookingSettingsService::class),
            $this->createMock(BusinessHourModel::class),
            $generalSettingsService
        );

        $result = $service->uploadLogo(null, 9);

        $this->assertSame('validation_error', $result['status']);
        $this->assertSame('No logo file received.', $result['message']);
    }

    public function testUploadIconDelegatesToGeneralSettingsService(): void
    {
        $generalSettingsService = $this->createMock(GeneralSettingsService::class);
        $generalSettingsService->expects($this->once())
            ->method('uploadIconForApi')
            ->with(null, 10)
            ->willReturn([
                'status' => 'validation_error',
                'message' => 'No icon file received.',
            ]);

        $service = new SettingsApiService(
            $this->createMock(SettingModel::class),
            $this->createMock(CalendarConfigService::class),
            $this->createMock(LocalizationSettingsService::class),
            $this->createMock(BookingSettingsService::class),
            $this->createMock(BusinessHourModel::class),
            $generalSettingsService
        );

        $result = $service->uploadIcon(null, 10);

        $this->assertSame('validation_error', $result['status']);
        $this->assertSame('No icon file received.', $result['message']);
    }
}