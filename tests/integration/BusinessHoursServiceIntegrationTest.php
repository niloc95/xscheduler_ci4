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
    protected $refresh = true;
    protected BusinessHoursService $service;
    private int $providerId;
    /** @var string[] Setting keys seeded by each test (cleaned up in tearDown) */
    private array $seededSettingKeys = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BusinessHoursService();
        $this->providerId = $this->seedProvider();
    }

    /**
     * Test validation passes for appointment within business hours
     */
    public function testValidateAppointmentTimeWithinHours(): void
    {
        // Global hours: 09:00 - 17:00
        $this->seedGlobalHours('09:00', '17:00');

        // Monday at 10:00 AM - 11:00 AM (within hours)
        $start = new DateTime('2026-02-02 10:00:00', new DateTimeZone('UTC')); // Monday
        $end   = new DateTime('2026-02-02 11:00:00', new DateTimeZone('UTC'));

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
        // When no global hours are configured the service returns null → closed.
        // Ensure no work_start/work_end settings exist for this test.
        $this->clearGlobalHours();

        $start = new DateTime('2026-02-02 10:00:00', new DateTimeZone('UTC')); // Monday
        $end   = new DateTime('2026-02-02 11:00:00', new DateTimeZone('UTC'));

        $result = $this->service->validateAppointmentTime($start, $end);

        $this->assertFalse($result['valid'], 'Should be invalid when no global hours are configured');
        $this->assertNotNull($result['reason']);
        $this->assertNull($result['hours']);
    }

    /**
     * Test validation fails for appointment starting before business hours
     */
    public function testValidateAppointmentTimeBeforeBusinessHours(): void
    {
        // Global hours: 09:00 - 17:00
        $this->seedGlobalHours('09:00', '17:00');

        // Monday at 8:00 AM - 9:00 AM (before opening)
        $start = new DateTime('2026-02-02 08:00:00', new DateTimeZone('UTC'));
        $end   = new DateTime('2026-02-02 09:00:00', new DateTimeZone('UTC'));

        $result = $this->service->validateAppointmentTime($start, $end);

        $this->assertFalse($result['valid'], 'Appointment before business hours should be invalid');
        $this->assertStringContainsString('outside our business hours', $result['reason']);
        $this->assertNotNull($result['hours']);
        $this->assertEquals('09:00:00', $result['hours']['start_time']);
        $this->assertEquals('17:00:00', $result['hours']['end_time']);
    }

    /**
     * Test validation fails for appointment starting after business hours
     */
    public function testValidateAppointmentTimeAfterBusinessHours(): void
    {
        // Global hours: 09:00 - 17:00
        $this->seedGlobalHours('09:00', '17:00');

        // Monday at 6:00 PM - 7:00 PM (after closing)
        $start = new DateTime('2026-02-02 18:00:00', new DateTimeZone('UTC'));
        $end   = new DateTime('2026-02-02 19:00:00', new DateTimeZone('UTC'));

        $result = $this->service->validateAppointmentTime($start, $end);

        $this->assertFalse($result['valid'], 'Appointment after business hours should be invalid');
        $this->assertStringContainsString('outside our business hours', $result['reason']);
    }

    /**
     * Test validation fails for appointment extending past closing time
     */
    public function testValidateAppointmentTimeExtendsPastClosing(): void
    {
        // Global hours: 09:00 - 17:00
        $this->seedGlobalHours('09:00', '17:00');

        // Monday at 4:30 PM - 5:30 PM (extends past 5:00 PM close)
        $start = new DateTime('2026-02-02 16:30:00', new DateTimeZone('UTC'));
        $end   = new DateTime('2026-02-02 17:30:00', new DateTimeZone('UTC'));

        $result = $this->service->validateAppointmentTime($start, $end);

        $this->assertFalse($result['valid'], 'Appointment extending past closing should be invalid');
        $this->assertStringContainsString('extend past our closing time', $result['reason']);
    }

    /**
     * Test validation passes for appointment at edge of business hours (opening time)
     */
    public function testValidateAppointmentTimeAtOpeningTime(): void
    {
        // Global hours: 09:00 - 17:00
        $this->seedGlobalHours('09:00', '17:00');

        // Monday at 9:00 AM - 10:00 AM (exactly at opening)
        $start = new DateTime('2026-02-02 09:00:00', new DateTimeZone('UTC'));
        $end   = new DateTime('2026-02-02 10:00:00', new DateTimeZone('UTC'));

        $result = $this->service->validateAppointmentTime($start, $end);

        $this->assertTrue($result['valid'], 'Appointment at opening time should be valid');
        $this->assertNull($result['reason']);
    }

    /**
     * Test validation passes for appointment ending exactly at closing time
     */
    public function testValidateAppointmentTimeEndingAtClosingTime(): void
    {
        // Global hours: 09:00 - 17:00
        $this->seedGlobalHours('09:00', '17:00');

        // Monday at 4:00 PM - 5:00 PM (ends exactly at closing)
        $start = new DateTime('2026-02-02 16:00:00', new DateTimeZone('UTC'));
        $end   = new DateTime('2026-02-02 17:00:00', new DateTimeZone('UTC'));

        $result = $this->service->validateAppointmentTime($start, $end);

        $this->assertTrue($result['valid'], 'Appointment ending at closing time should be valid');
        $this->assertNull($result['reason']);
    }

    /**
     * Test getBusinessHoursForDate returns hours for working day
     */
    public function testGetBusinessHoursForDateReturnsHours(): void
    {
        // Global hours: 10:00 - 18:00
        $this->seedGlobalHours('10:00', '18:00');

        $result = $this->service->getBusinessHoursForDate('2026-02-03'); // Tuesday

        $this->assertNotNull($result, 'Should return hours when global hours are configured');
        $this->assertEquals('10:00:00', $result['start_time']);
        $this->assertEquals('18:00:00', $result['end_time']);
    }
    /**
     * Test getBusinessHoursForDate returns null for closed day
     */
    public function testGetBusinessHoursForDateReturnsNullForClosedDay(): void
    {
        // When no global hours are configured the service returns null.
        $this->clearGlobalHours();

        $result = $this->service->getBusinessHoursForDate('2026-02-01'); // Sunday

        $this->assertNull($result, 'Should return null when global hours are not configured');
    }
    /**
     * Test isWorkingDay returns true for working day
     */
    public function testIsWorkingDayReturnsTrueForWorkingDay(): void
    {
        // With global hours configured, any date is potentially a working day.
        $this->seedGlobalHours('09:00', '17:00');

        $result = $this->service->isWorkingDay('2026-02-04'); // Wednesday

        $this->assertTrue($result, 'Should return true when global hours are configured');
    }
    /**
     * Test isWorkingDay returns false for closed day
     */
    public function testIsWorkingDayReturnsFalseForClosedDay(): void
    {
        // Without global hours configured, no date is a working day.
        $this->clearGlobalHours();

        $result = $this->service->isWorkingDay('2026-02-07'); // Saturday

        $this->assertFalse($result, 'Should return false when global hours are not configured');
    }
    /**
     * Test getWeeklyHours returns all days
     */
    public function testGetWeeklyHoursReturnsAllDays(): void
    {
        // Global hours: 09:00 - 17:00. getWeeklyHours() returns Mon-Fri keyed by weekday.
        $this->seedGlobalHours('09:00', '17:00');

        $result = $this->service->getWeeklyHours();

        $this->assertIsArray($result);
        $this->assertCount(5, $result, 'Should return 5 working days (Mon-Fri)');
        $this->assertArrayHasKey(1, $result); // Monday
        $this->assertArrayHasKey(2, $result); // Tuesday
        $this->assertArrayHasKey(5, $result); // Friday
        $this->assertEquals('09:00:00', $result[1]['start_time']);
        $this->assertEquals('17:00:00', $result[1]['end_time']);
    }

    /**
     * Helper method to seed business hours for testing
     */
    private function seedGlobalHours(string $workStart, string $workEnd): void
    {
        $db = \Config\Database::connect('tests');
        $keys = ['business.work_start', 'business.work_end'];
        $db->table('settings')->whereIn('setting_key', $keys)->delete();
        $db->table('settings')->insert([
            'setting_key' => 'business.work_start', 'setting_value' => $workStart,
            'setting_type' => 'string', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $db->table('settings')->insert([
            'setting_key' => 'business.work_end', 'setting_value' => $workEnd,
            'setting_type' => 'string', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->seededSettingKeys = ['business.work_start', 'business.work_end'];
    }

    private function clearGlobalHours(): void
    {
        $db = \Config\Database::connect('tests');
        $db->table('settings')->whereIn('setting_key', ['business.work_start', 'business.work_end'])->delete();
        $this->seededSettingKeys = [];
    }

    private function seedProvider(): int
    {
        $db = \Config\Database::connect();
        $email = 'business-hours-provider-' . uniqid('', true) . '@example.com';

        $db->table('users')->insert([
            'name' => 'Business Hours Provider',
            'email' => $email,
            'password_hash' => password_hash('password', PASSWORD_DEFAULT),
            'role' => 'provider',
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $db->insertID();
    }

    protected function tearDown(): void
    {
        $db = \Config\Database::connect('tests');
        if (!empty($this->seededSettingKeys)) {
            $db->table('settings')->whereIn('setting_key', $this->seededSettingKeys)->delete();
        }
        $db->table('users')->where('id', $this->providerId)->delete();

        parent::tearDown();
        }
    }
