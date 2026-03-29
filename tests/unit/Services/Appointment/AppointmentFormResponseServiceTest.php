<?php

namespace Tests\Unit\Services\Appointment;

use App\Services\Appointment\AppointmentFormResponseService;
use CodeIgniter\HTTP\Response;
use CodeIgniter\Test\CIUnitTestCase;

final class AppointmentFormResponseServiceTest extends CIUnitTestCase
{
    public function testFromMutationResultReturnsAjaxSuccessPayload(): void
    {
        $service = new AppointmentFormResponseService();
        $response = new Response(config('App'));

        $result = $service->fromMutationResult([
            'success' => true,
            'message' => 'Appointment booked successfully!',
            'redirect' => base_url('appointments'),
            'appointmentId' => 42,
        ], true, $response);

        $this->assertSame(200, $result->getStatusCode());
        $this->assertSame(
            [
                'success' => true,
                'message' => 'Appointment booked successfully!',
                'redirect' => base_url('appointments'),
                'appointmentId' => 42,
            ],
            json_decode($result->getBody(), true)
        );
    }

    public function testFromMutationResultReturnsAjaxFailurePayload(): void
    {
        $service = new AppointmentFormResponseService();
        $response = new Response(config('App'));

        $result = $service->fromMutationResult([
            'success' => false,
            'statusCode' => 409,
            'message' => 'Conflict detected',
            'errors' => ['provider_id' => 'Missing provider'],
            'conflicts' => [['start_at' => '2031-01-07 10:30:00']],
        ], true, $response);

        $this->assertSame(409, $result->getStatusCode());
        $this->assertSame(
            [
                'success' => false,
                'message' => 'Conflict detected',
                'errors' => ['provider_id' => 'Missing provider'],
                'conflicts' => [['start_at' => '2031-01-07 10:30:00']],
            ],
            json_decode($result->getBody(), true)
        );
    }
}