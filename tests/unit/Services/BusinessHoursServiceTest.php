<?php

namespace Tests\Unit\Services;

use App\Services\BusinessHoursService;
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
     * Test formatHours returns formatted string
     */
    public function testFormatHoursReturnsFormattedString(): void
    {
        $hours = [
            'weekday' => 1,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00'
        ];
        
        $result = $this->service->formatHours($hours);
        
        $this->assertEquals('9:00 AM - 5:00 PM', $result);
    }

    /**
     * Test formatHours handles noon and midnight correctly
     */
    public function testFormatHoursHandlesNoonAndMidnight(): void
    {
        $hours = [
            'weekday' => 1,
            'start_time' => '00:00:00',
            'end_time' => '12:00:00'
        ];
        
        $result = $this->service->formatHours($hours);
        
        $this->assertStringContainsString('12:00 AM', $result); // Midnight
        $this->assertStringContainsString('12:00 PM', $result); // Noon
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
