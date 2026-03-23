<?php

namespace Tests\Unit\Services;

use App\Services\Appointment\AppointmentDateTimeNormalizer;
use App\Services\TimezoneService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class AppointmentDateTimeNormalizerTest extends CIUnitTestCase
{
    public function testResolveInputTimezoneReturnsValidTimezoneUnchanged(): void
    {
        $normalizer = new AppointmentDateTimeNormalizer('UTC');

        $this->assertSame('Africa/Johannesburg', $normalizer->resolveInputTimezone('Africa/Johannesburg'));
    }

    public function testResolveInputTimezoneFallsBackForInvalidInput(): void
    {
        $normalizer = new AppointmentDateTimeNormalizer('UTC');

        $resolved = $normalizer->resolveInputTimezone('Not/A-Real-Timezone');

        $this->assertSame(TimezoneService::businessTimezone(), $resolved);
    }

    public function testNormalizeDateAndTimeConvertsInputTimezoneToUtcAndAppLocal(): void
    {
        $normalizer = new AppointmentDateTimeNormalizer('America/New_York');

        $result = $normalizer->normalizeDateAndTime('2026-05-01', '09:30', 'Africa/Johannesburg');

        $this->assertTrue($result['success']);
        $this->assertSame('2026-05-01 07:30:00', $result['utc']);
        $this->assertSame('2026-05-01', $result['app_date']);
        $this->assertSame('03:30', $result['app_time']);
    }

    public function testNormalizeDateAndTimeRejectsInvalidInput(): void
    {
        $normalizer = new AppointmentDateTimeNormalizer('UTC');

        $result = $normalizer->normalizeDateAndTime('bad-date', 'bad-time', 'UTC');

        $this->assertFalse($result['success']);
        $this->assertSame('Invalid appointment date/time input', $result['message']);
    }

    public function testNormalizeDateTimeStringConvertsUsingFallbackTimezone(): void
    {
        $normalizer = new AppointmentDateTimeNormalizer('UTC');

        $result = $normalizer->normalizeDateTimeString('2026-07-15 18:45:00', 'Africa/Johannesburg');

        $this->assertTrue($result['success']);
        $this->assertSame('2026-07-15 16:45:00', $result['utc']);
        $this->assertSame('2026-07-15', $result['app_date']);
        $this->assertSame('16:45', $result['app_time']);
    }

    public function testNormalizeDateTimeStringRejectsInvalidInput(): void
    {
        $normalizer = new AppointmentDateTimeNormalizer('UTC');

        $result = $normalizer->normalizeDateTimeString('definitely not a datetime', 'UTC');

        $this->assertFalse($result['success']);
        $this->assertSame('Invalid datetime input', $result['message']);
    }
}