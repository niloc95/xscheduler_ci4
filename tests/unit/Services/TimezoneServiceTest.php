<?php

namespace Tests\Unit\Services;

use App\Services\TimezoneService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Conversion-contract coverage for TimezoneService, with emphasis on DST.
 *
 * The app was built and operated against Africa/Johannesburg, which has no
 * daylight saving. Moving a business to Europe/London or America/New_York
 * exercises transition handling for the first time, so these cases pin the
 * behaviour before anyone relies on it.
 *
 * @internal
 */
final class TimezoneServiceTest extends CIUnitTestCase
{
    public function testRoundTripsLocalToUtcAndBackForANonDstZone(): void
    {
        $utc = TimezoneService::toStorage('2026-05-15 09:00:00', 'Africa/Johannesburg');
        $this->assertSame('2026-05-15 07:00:00', $utc);
        $this->assertSame('2026-05-15 09:00:00', TimezoneService::toDisplay($utc, 'Africa/Johannesburg'));
    }

    public function testAppliesTheCorrectOffsetEitherSideOfTheNewYorkDstBoundary(): void
    {
        // EST (UTC-5) — January
        $this->assertSame(
            '2026-01-15 14:00:00',
            TimezoneService::toStorage('2026-01-15 09:00:00', 'America/New_York')
        );

        // EDT (UTC-4) — July. Same wall-clock input, different UTC instant.
        $this->assertSame(
            '2026-07-15 13:00:00',
            TimezoneService::toStorage('2026-07-15 09:00:00', 'America/New_York')
        );
    }

    public function testAppliesTheCorrectOffsetEitherSideOfTheLondonDstBoundary(): void
    {
        // GMT (UTC+0) — January
        $this->assertSame(
            '2026-01-15 09:00:00',
            TimezoneService::toStorage('2026-01-15 09:00:00', 'Europe/London')
        );

        // BST (UTC+1) — July
        $this->assertSame(
            '2026-07-15 08:00:00',
            TimezoneService::toStorage('2026-07-15 09:00:00', 'Europe/London')
        );
    }

    public function testHandlesTheAmbiguousHourAtTheFallBackTransition(): void
    {
        // 2026-11-01 01:30 occurs twice in America/New_York. PHP resolves it
        // deterministically to the first (EDT) occurrence; assert it produces a
        // valid, stable instant rather than throwing or returning the input.
        $utc = TimezoneService::toStorage('2026-11-01 01:30:00', 'America/New_York');

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $utc);
        $this->assertSame('2026-11-01 05:30:00', $utc);
        $this->assertSame($utc, TimezoneService::toStorage('2026-11-01 01:30:00', 'America/New_York'));
    }

    public function testHandlesTheNonExistentHourAtTheSpringForwardTransition(): void
    {
        // 2026-03-08 02:30 does not exist in America/New_York — the clock jumps
        // 02:00 -> 03:00. This must not throw or silently return the local string.
        $utc = TimezoneService::toStorage('2026-03-08 02:30:00', 'America/New_York');

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $utc);
        $this->assertNotSame('2026-03-08 02:30:00', $utc);
    }

    public function testOffsetMinutesFollowTheJavascriptSignConvention(): void
    {
        // East of UTC is NEGATIVE, matching Date.prototype.getTimezoneOffset(),
        // because the value is compared against the X-Client-Offset header.
        $this->assertSame(-120, TimezoneService::getOffsetMinutes('Africa/Johannesburg'));

        // West of UTC is POSITIVE, and DST-aware.
        $this->assertSame(300, TimezoneService::getOffsetMinutes('America/New_York', new \DateTime('2026-01-15')));
        $this->assertSame(240, TimezoneService::getOffsetMinutes('America/New_York', new \DateTime('2026-07-15')));

        $this->assertSame(0, TimezoneService::getOffsetMinutes('Europe/London', new \DateTime('2026-01-15')));
        $this->assertSame(-60, TimezoneService::getOffsetMinutes('Europe/London', new \DateTime('2026-07-15')));
    }

    public function testToDisplayIsoCarriesTheOffset(): void
    {
        $this->assertSame(
            '2026-07-15T09:00:00-04:00',
            TimezoneService::toDisplayIso('2026-07-15 13:00:00', 'America/New_York')
        );
    }

    public function testValidatesIanaIdentifiers(): void
    {
        $this->assertTrue(TimezoneService::isValidTimezone('America/New_York'));
        $this->assertTrue(TimezoneService::isValidTimezone('Europe/London'));
        $this->assertTrue(TimezoneService::isValidTimezone('UTC'));
        $this->assertFalse(TimezoneService::isValidTimezone('Not/AZone'));
        $this->assertFalse(TimezoneService::isValidTimezone(''));
    }

    public function testBusinessTimezoneAlwaysResolvesToAUsableZone(): void
    {
        $tz = TimezoneService::businessTimezone();

        $this->assertNotSame('', $tz);
        $this->assertTrue(TimezoneService::isValidTimezone($tz));
    }
}
