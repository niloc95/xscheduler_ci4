<?php

namespace Tests\Unit\Services\Appointment;

use App\Services\Appointment\AppointmentFormSubmissionService;
use CodeIgniter\Test\CIUnitTestCase;

final class AppointmentFormSubmissionServiceTest extends CIUnitTestCase
{
    public function testBuildCreateBookingDataIncludesNormalizedStartAndCustomFields(): void
    {
        $service = new AppointmentFormSubmissionService();

        $payload = $service->buildCreateBookingData([
            'provider_id' => '5',
            'service_id' => '9',
            'location_id' => '3',
            'customer_id' => '44',
            'customer_first_name' => 'Sam',
            'customer_last_name' => 'Lee',
            'customer_email' => 'sam@example.com',
            'customer_phone' => '1234567890',
            'customer_address' => '123 Street',
            'notes' => 'Bring reports',
            'custom_field_2' => 'Wheelchair access',
        ], [
            'app_date' => '2030-03-12',
            'app_time' => '09:15:00',
        ]);

        $this->assertSame(5, $payload['provider_id']);
        $this->assertSame(9, $payload['service_id']);
        $this->assertSame(3, $payload['location_id']);
        $this->assertSame(44, $payload['customer_id']);
        $this->assertSame('2030-03-12', $payload['appointment_date']);
        $this->assertSame('09:15:00', $payload['appointment_time']);
        $this->assertSame('Bring reports', $payload['notes']);
        $this->assertSame('Wheelchair access', $payload['custom_field_2']);
        $this->assertSame(['email', 'whatsapp'], $payload['notification_types']);
    }

    public function testBuildCustomerUpdateDataSerializesOnlyPresentCustomFields(): void
    {
        $service = new AppointmentFormSubmissionService();

        $payload = $service->buildCustomerUpdateData([
            'customer_first_name' => 'Jordan',
            'customer_last_name' => 'Kim',
            'customer_email' => 'jordan@example.com',
            'customer_phone' => '3213213210',
            'customer_address' => '456 Avenue',
            'custom_field_1' => 'Allergy note',
            'custom_field_4' => '',
        ]);

        $this->assertSame('Jordan', $payload['first_name']);
        $this->assertSame('Kim', $payload['last_name']);
        $this->assertSame('jordan@example.com', $payload['email']);
        $this->assertSame('{"custom_field_1":"Allergy note"}', $payload['custom_fields']);
    }

    public function testBuildUpdateAppointmentDataNormalizesOptionalLocationAndStatus(): void
    {
        $service = new AppointmentFormSubmissionService();

        $payload = $service->buildUpdateAppointmentData([
            'provider_id' => '12',
            'service_id' => '7',
            'status' => 'no_show',
            'notes' => 'Late arrival',
            'location_id' => '',
        ], [
            'app_date' => '2031-01-01',
            'app_time' => '14:00:00',
        ]);

        $this->assertSame(12, $payload['provider_id']);
        $this->assertSame(7, $payload['service_id']);
        $this->assertSame('2031-01-01', $payload['appointment_date']);
        $this->assertSame('14:00:00', $payload['appointment_time']);
        $this->assertSame('no-show', $payload['status']);
        $this->assertSame('Late arrival', $payload['notes']);
        $this->assertNull($payload['location_id']);
    }
}