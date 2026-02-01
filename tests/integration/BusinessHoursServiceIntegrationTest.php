<?php

namespace Tests\Integration;

use App\Services\BusinessHoursService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use DateTime;
use DateTimeZone;

/**
 * Integration tests for BusinessHoursService
 * Tests database-dependent methods that query business_hours table
 * 
 * @internal
 */
final class BusinessHoursServiceIntegrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = 'App';
    protected BusinessHoursService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BusinessHoursService();
    }

    /**
     * Test validation passes for appointment within business hours
     */
    public function testValidateAppointmentTimeWithinHours(): void
    {
        // Setup: Monday 9:00 AM - 5:00 PM business hours
        $this->seedBusinessHours(1, '09:00:00', '17:00:00'); // Monday
        
        // Monday at 10:00 AM - 11:00 AM (within hours)
        $start = new DateTime('2026-02-02 10:00:00', new DateTimeZone('UTC')); // Monday
        $end = new DateTime('2026-02-02 11:00:00', new DateTimeZone('UTC'));
        
        $result = $this->service->validateAppointmentTime($start, $end);
        
        $this->assertTrue($result['valid'], 'Appointment within business hours should be valid');
        $this->assertNull($result['reason']);
        $this->assertNotNull($result['hours']);
        $this->assertEquals('09:00:00', $result['hours']['start_time']);
        $this->assertEquals('17:00:00', $result['hours']['end_time']);
    }

    /**
     * Test validation fails for appointment on closed day
     */
    public function testValidateAppointmentTimeOnClosedDay(): void
    {
        // Setup: No business hours for Sunday (weekday 0)
        // Don't seed anything for Sunday
        
        // Sunday at 10:00 AM - 11:00 AM
        $start = new DateTime('2026-02-01 10:00:00', new DateTimeZone('UTC')); // Sunday
        $end = new DateTime('2026-02-01 11:00:00', new DateTimeZone('UTC'));
        
        $result = $this->service->validateAppointmentTime($start, $end);
        
        $this->assertFalse($result['valid'], 'Appointment on closed day should be invalid');
        $this->assertStringContainsString('closed on Sunday', $result['reason']);
        $this->assertNull($result['hours']);
    }

    /**
     * Test validation fails for appointment starting before business hours
     */
    public function testValidateAppointmentTimeBeforeBusinessHours(): void
    {
        // Setup: Monday 9:00 AM - 5:00 PM
        $this->seedBusinessHours(1, '09:00:00', '17:00:00');
        
        // Monday at 8:00 AM - 9:00 AM (before opening)
        $start = new DateTime('2026-02-02 08:00:00', new DateTimeZone('UTC')); // Monday
        $end = new DateTime('2026-02-02 09:00:00', new DateTimeZone('UTC'));
        
        $result = $this->service->validateAppointmentTime($start, $end);
        
        $this->assertFalse($result['valid'], 'Appointment before business hours should be invalid');
        $this->assertStringContainsString('outside our business hours', $result['reason']);
        $this->assertStringContainsString('9:00 AM', $result['reason']);
        $this->assertStringContainsString('5:00 PM', $result['reason']);
    }

    /**
     * Test validation fails for appointment starting after business hours
     */
    public function testValidateAppointmentTimeAfterBusinessHours(): void
    {
        // Setup: Monday 9:00 AM - 5:00 PM
        $this->seedBusinessHours(1, '09:00:00', '17:00:00');
        
        // Monday at 6:00 PM - 7:00 PM (after closing)
        $start = new DateTime('2026-02-02 18:00:00', new DateTimeZone('UTC')); // Monday
        $end = new DateTime('2026-02-02 19:00:00', new DateTimeZone('UTC'));
        
        $result = $this->service->validateAppointmentTime($start, $end);
        
        $this->assertFalse($result['valid'], 'Appointment after business hours should be invalid');
        $this->assertStringContainsString('outside our business hours', $result['reason']);
    }

    /**
     * Test validation fails for appointment extending past closing time
     */
    public function testValidateAppointmentTimeExtendsPastClosing(): void
    {
        // Setup: Monday 9:00 AM - 5:00 PM
        $this->seedBusinessHours(1, '09:00:00', '17:00:00');
        
        // Monday at 4:30 PM - 5:30 PM (extends past 5:00 PM close)
        $start = new DateTime('2026-02-02 16:30:00', new DateTimeZone('UTC')); // Monday
        $end = new DateTime('2026-02-02 17:30:00', new DateTimeZone('UTC'));
        
        $result = $this->service->validateAppointmentTime($start, $end);
        
        $this->assertFalse($result['valid'], 'Appointment extending past closing should be invalid');
        $this->assertStringContainsString('extend past our closing time', $result['reason']);
    }

    /**
     * Test validation passes for appointment at edge of business hours (opening time)
     */
    public function testValidateAppointmentTimeAtOpeningTime(): void
    {
        // Setup: Monday 9:00 AM - 5:00 PM
        $this->seedBusinessHours(1, '09:00:00', '17:00:00');
        
        // Monday at 9:00 AM - 10:00 AM (exactly at opening)
        $start = new DateTime('2026-02-02 09:00:00', new DateTimeZone('UTC')); // Monday
        $end = new DateTime('2026-02-02 10:00:00', new DateTimeZone('UTC'));
        
        $result = $this->service->validateAppointmentTime($start, $end);
        
        $this->assertTrue($result['valid'], 'Appointment at opening time should be valid');
        $this->assertNull($result['reason']);
    }

    /**
     * Test validation passes for appointment ending exactly at closing time
     */
    public function testValidateAppointmentTimeEndingAtClosingTime(): void
    {
        // Setup: Monday 9:00 AM - 5:00 PM
        $this->seedBusinessHours(1, '09:00:00', '17:00:00');
        
        // Monday at 4:00 PM - 5:00 PM (ends exactly at closing)
        $start = new DateTime('2026-02-02 16:00:00', new DateTimeZone('UTC')); // Monday
        $end = new DateTime('2026-02-02 17:00:00', new DateTimeZone('UTC'));
        
        $result = $this->service->validateAppointmentTime($start, $end);
        
        $this->assertTrue($result['valid'], 'Appointment ending at closing time should be valid');
        $this->assertNull($result['reason']);
    }

    /**
     * Test getBusinessHoursForDate returns hours for working day
     */
    public function testGetBusinessHoursForDateReturnsHours(): void
    {
        // Setup: Tuesday 10:00 AM - 6:00 PM
        $this->seedBusinessHours(2, '10:00:00', '18:00:00'); // Tuesday
        
        // Tuesday date
        $date = '2026-02-03'; // Tuesday
        
        $result = $this->service->getBusinessHoursForDate($date);
        
        $this->assertNotNull($result, 'Should return hours for working day');
        $this->assertEquals(2, $result['weekday']);
        $this->assertEquals('10:00:00', $result['start_time']);
        $this->assertEquals('18:00:00', $result['end_time']);
    }

    /**
     * Test getBusinessHoursForDate returns null for closed day
     */
    public function testGetBusinessHoursForDateReturnsNullForClosedDay(): void
    {
        // Setup: No hours for Sunday
        
        // Sunday date
        $date = '2026-02-01'; // Sunday
        
        $result = $this->service->getBusinessHoursForDate($date);
        
        $this->assertNull($result, 'Should return null for closed day');
    }

    /**
     * Test isWorkingDay returns true for working day
     */
    public function testIsWorkingDayReturnsTrueForWorkingDay(): void
    {
        // Setup: Wednesday hours
        $this->seedBusinessHours(3, '09:00:00', '17:00:00'); // Wednesday
        
        // Wednesday date
        $date = '2026-02-04'; // Wednesday
        
        $result = $this->service->isWorkingDay($date);
        
        $this->assertTrue($result, 'Should return true for working day');
    }

    /**
     * Test isWorkingDay returns false for closed day
     */
    public function testIsWorkingDayReturnsFalseForClosedDay(): void
    {
        // Setup: No hours for Saturday
        
        // Saturday date
        $date = '2026-02-07'; // Saturday
        
        $result = $this->service->isWorkingDay($date);
        
        $this->assertFalse($result, 'Should return false for closed day');
    }

    /**
     * Test getWeeklyHours returns all days
     */
    public function testGetWeeklyHoursReturnsAllDays(): void
    {
        // Setup: Multiple days
        $this->seedBusinessHours(1, '09:00:00', '17:00:00'); // Monday
        $this->seedBusinessHours(2, '09:00:00', '17:00:00'); // Tuesday
        $this->seedBusinessHours(3, '09:00:00', '17:00:00'); // Wednesday
        $this->seedBusinessHours(4, '09:00:00', '17:00:00'); // Thursday
        $this->seedBusinessHours(5, '09:00:00', '17:00:00'); // Friday
        
        $result = $this->service->getWeeklyHours();
        
        $this->assertIsArray($result);
        $this->assertCount(5, $result, 'Should return 5 working days');
        $this->assertArrayHasKey(1, $result); // Monday
        $this->assertArrayHasKey(2, $result); // Tuesday
        $this->assertArrayHasKey(5, $result); // Friday
        $this->assertEquals('09:00:00', $result[1]['start_time']);
    }

    /**
     * Helper method to seed business hours for testing
     */
    private function seedBusinessHours(int $weekday, string $startTime, string $endTime): void
    {
        $db = \Config\Database::connect();
        
        // Clear existing data for this weekday
        $db->table('business_hours')->where('weekday', $weekday)->delete();
        
        // Insert test data
        $db->table('business_hours')->insert([
            'weekday' => $weekday,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    protected function tearDown(): void
    {
        // Clean up test data
        $db = \Config\Database::connect();
        $db->table('business_hours')->truncate();
        
        parent::tearDown();
    }
}
