<?php

namespace Tests\Unit\Services;

use App\Services\Appointment\AppointmentFormatterService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class AppointmentFormatterServiceTest extends CIUnitTestCase
{
    public function testFormatForCalendarMapsCanonicalFieldsAndDefaults(): void
    {
        $service = new AppointmentFormatterService();

        $result = $service->formatForCalendar([
            'id' => 12,
            'hash' => 'abc123',
            'start_at' => '2026-05-10 08:00:00',
            'end_at' => '2026-05-10 08:45:00',
            'provider_id' => 4,
            'service_id' => 9,
            'customer_id' => 33,
            'status' => 'confirmed',
            'customer_name' => 'Pat Doe',
            'service_name' => 'Exam',
            'provider_name' => 'Dr. Rivera',
            'service_duration' => 45,
            'service_price' => 125.50,
            'service_buffer_before' => 10,
            'service_buffer_after' => 15,
            'customer_email' => 'pat@example.com',
            'customer_phone' => '+15550001111',
            'notes' => 'Bring reports',
            'location_id' => 99,
            'location_name' => 'Main Office',
            'location_address' => '123 Main St',
            'location_contact' => '+15551234567',
        ]);

        $this->assertSame(12, $result['id']);
        $this->assertSame('abc123', $result['hash']);
        $this->assertSame('Pat Doe', $result['title']);
        $this->assertSame('2026-05-10T08:00:00Z', $result['start']);
        $this->assertSame('2026-05-10T08:45:00Z', $result['end']);
        $this->assertSame('2026-05-10T08:00:00Z', $result['start_utc']);
        $this->assertSame(4, $result['provider_id']);
        $this->assertSame(9, $result['service_id']);
        $this->assertSame(33, $result['customer_id']);
        $this->assertSame('confirmed', $result['status']);
        $this->assertSame('Dr. Rivera', $result['provider_name']);
        $this->assertSame('#3B82F6', $result['provider_color']);
        $this->assertSame(45, $result['service_duration']);
        $this->assertSame(125.50, $result['service_price']);
        $this->assertSame(10, $result['buffer_before']);
        $this->assertSame(15, $result['buffer_after']);
        $this->assertSame('pat@example.com', $result['email']);
        $this->assertSame('+15550001111', $result['phone']);
        $this->assertSame(99, $result['location_id']);
    }

    public function testFormatManyForCalendarFormatsEachRow(): void
    {
        $service = new AppointmentFormatterService();

        $rows = [
            [
                'id' => 1,
                'start_at' => '2026-05-10 08:00:00',
                'end_at' => '2026-05-10 08:30:00',
                'provider_id' => 4,
                'service_id' => 9,
            ],
            [
                'id' => 2,
                'customer_name' => 'Alex Roe',
                'start_at' => '2026-05-10 09:00:00',
                'end_at' => '2026-05-10 09:30:00',
                'provider_id' => 5,
                'service_id' => 10,
            ],
        ];

        $result = $service->formatManyForCalendar($rows);

        $this->assertCount(2, $result);
        $this->assertSame('Appointment #1', $result[0]['title']);
        $this->assertSame('Alex Roe', $result[1]['title']);
        $this->assertSame(5, $result[1]['provider_id']);
    }

    public function testFormatForDetailIncludesAuditFields(): void
    {
        $service = new AppointmentFormatterService();

        $result = $service->formatForDetail([
            'id' => 77,
            'start_at' => '2026-06-01 10:00:00',
            'end_at' => '2026-06-01 10:30:00',
            'provider_id' => 4,
            'service_id' => 9,
            'reminder_sent' => 1,
            'public_token' => 'token-123',
            'created_at' => '2026-05-01 10:00:00',
            'updated_at' => '2026-05-02 10:00:00',
        ]);

        $this->assertSame('2026-05-01 10:00:00', $result['created_at']);
        $this->assertSame('2026-05-02 10:00:00', $result['updated_at']);
        $this->assertTrue($result['reminder_sent']);
        $this->assertSame('token-123', $result['public_token']);
    }

    public function testFormatForApiDetailProducesStableDetailShape(): void
    {
        $service = new AppointmentFormatterService();

        $result = $service->formatForApiDetail([
            'id' => 55,
            'customer_id' => 8,
            'customer_name' => 'Pat Doe',
            'customer_email' => 'pat@example.com',
            'customer_phone' => '+15550001111',
            'provider_id' => 4,
            'provider_name' => 'Dr. Rivera',
            'service_id' => 9,
            'service_name' => 'Exam',
            'start_at' => '2026-05-10 08:00:00',
            'end_at' => '2026-05-10 08:45:00',
            'service_duration' => 45,
            'service_price' => 125.50,
            'status' => 'confirmed',
            'notes' => 'Bring reports',
            'location_id' => 99,
            'location_name' => 'Main Office',
            'location_address' => '123 Main St',
            'location_contact' => '+15551234567',
            'is_paid' => 1,
            'created_at' => '2026-05-01 10:00:00',
            'updated_at' => '2026-05-02 10:00:00',
        ]);

        $this->assertSame(55, $result['id']);
        $this->assertSame(8, $result['customer_id']);
        $this->assertSame('Pat Doe', $result['customer_name']);
        $this->assertSame('Exam', $result['service_name']);
        $this->assertMatchesRegularExpression('/^2026-05-10(T| )/', (string) $result['start']);
        $this->assertMatchesRegularExpression('/^2026-05-10(T| )/', (string) $result['end']);
        $this->assertSame(45, $result['service_duration']);
        $this->assertSame(125.50, $result['service_price']);
        $this->assertSame('confirmed', $result['status']);
        $this->assertTrue($result['is_paid']);
    }
}