<?php

namespace Tests\Unit\Services;

use App\Models\AppointmentModel;
use App\Services\Appointment\AppointmentManualNotificationService;
use App\Services\Appointment\AppointmentQueryService;
use App\Services\AppointmentNotificationService;
use App\Services\AppointmentEventService;
use App\Services\NotificationSmsService;
use App\Services\NotificationTemplateService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class AppointmentManualNotificationServiceTest extends CIUnitTestCase
{
    public function testSendRejectsMissingAppointment(): void
    {
        $appointmentModel = $this->createMock(AppointmentModel::class);
        $appointmentModel->expects($this->once())
            ->method('find')
            ->with(101)
            ->willReturn(null);

        $service = $this->makeService($appointmentModel);

        $result = $service->send(101, 'email');

        $this->assertFalse($result['success']);
        $this->assertSame(404, $result['statusCode']);
        $this->assertSame('Appointment not found', $result['message']);
        $this->assertSame(['appointment_id' => 101], $result['errors']);
    }

    public function testSendRejectsInvalidChannel(): void
    {
        $appointmentModel = $this->createMock(AppointmentModel::class);
        $appointmentModel->expects($this->once())
            ->method('find')
            ->with(101)
            ->willReturn([
                'id' => 101,
                'status' => 'pending',
            ]);

        $service = $this->makeService($appointmentModel);

        $result = $service->send(101, 'pager');

        $this->assertFalse($result['success']);
        $this->assertSame(400, $result['statusCode']);
        $this->assertSame('Invalid channel. Must be one of: email, sms, whatsapp', $result['message']);
    }

    public function testSendEmailUsesDerivedEventTypeAndReturnsSuccessPayload(): void
    {
        $appointmentModel = $this->createMock(AppointmentModel::class);
        $appointmentModel->expects($this->once())
            ->method('find')
            ->with(15)
            ->willReturn([
                'id' => 15,
                'status' => 'cancelled',
            ]);

        $emailService = $this->createMock(AppointmentNotificationService::class);
        $emailService->expects($this->once())
            ->method('sendEventEmail')
            ->with('appointment_cancelled', 15, 1)
            ->willReturn(true);

        $service = $this->makeService(
            $appointmentModel,
            null,
            $emailService
        );

        $result = $service->send(15, 'email');

        $this->assertTrue($result['success']);
        $this->assertSame('email', $result['data']['channel']);
        $this->assertSame('appointment_cancelled', $result['data']['event_type']);
        $this->assertSame('Email notification sent successfully', $result['data']['message']);
    }

    public function testSendEmailReturnsFailurePayloadWhenNotificationServiceFails(): void
    {
        $appointmentModel = $this->createMock(AppointmentModel::class);
        $appointmentModel->method('find')->willReturn([
            'id' => 15,
            'status' => 'pending',
        ]);

        $emailService = $this->createMock(AppointmentNotificationService::class);
        $emailService->expects($this->once())
            ->method('sendEventEmail')
            ->with('appointment_confirmed', 15, 1)
            ->willReturn(false);

        $service = $this->makeService(
            $appointmentModel,
            null,
            $emailService
        );

        $result = $service->send(15, 'email');

        $this->assertFalse($result['success']);
        $this->assertSame(400, $result['statusCode']);
        $this->assertSame('Email not sent. Check if email is enabled for this event type and SMTP is configured.', $result['message']);
    }

    public function testSendSmsUsesFallbackBodyWhenTemplateIsEmpty(): void
    {
        $appointmentModel = $this->createMock(AppointmentModel::class);
        $appointmentModel->expects($this->once())
            ->method('find')
            ->with(88)
            ->willReturn([
                'id' => 88,
                'status' => 'confirmed',
            ]);

        $queryService = $this->createMock(AppointmentQueryService::class);
        $queryService->expects($this->once())
            ->method('getDetailById')
            ->with(88)
            ->willReturn([
                'customer_name' => 'Pat Doe',
                'customer_email' => 'pat@example.com',
                'customer_phone' => '+15550001111',
                'service_name' => 'Exam',
                'service_duration' => 30,
                'provider_name' => 'Dr. Rivera',
                'start_at' => '2026-05-01 10:00:00',
                'location_name' => 'Main Office',
                'location_address' => '123 Main',
                'location_contact' => '+15551234567',
            ]);

        $templateService = $this->createMock(NotificationTemplateService::class);
        $templateService->expects($this->once())
            ->method('render')
            ->with('appointment_confirmed', 'sms', $this->callback(static function (array $payload): bool {
                return $payload['customer_name'] === 'Pat Doe'
                    && $payload['customer_phone'] === '+15550001111'
                    && $payload['service_name'] === 'Exam';
            }))
            ->willReturn(['body' => '   ']);

        $smsService = $this->createMock(NotificationSmsService::class);
        $smsService->expects($this->once())
            ->method('sendSms')
            ->with(1, '+15550001111', 'Your appointment has been updated.')
            ->willReturn(['ok' => true]);

        $service = $this->makeService(
            $appointmentModel,
            $queryService,
            null,
            $smsService,
            $templateService
        );

        $result = $service->send(88, 'sms', 'appointment_confirmed');

        $this->assertTrue($result['success']);
        $this->assertSame('sms', $result['data']['channel']);
        $this->assertSame('appointment_confirmed', $result['data']['event_type']);
    }

    public function testSendSmsRejectsMissingCustomerPhone(): void
    {
        $appointmentModel = $this->createMock(AppointmentModel::class);
        $appointmentModel->method('find')->willReturn([
            'id' => 88,
            'status' => 'confirmed',
        ]);

        $queryService = $this->createMock(AppointmentQueryService::class);
        $queryService->expects($this->once())
            ->method('getDetailById')
            ->with(88)
            ->willReturn([
                'customer_phone' => '',
            ]);

        $smsService = $this->createMock(NotificationSmsService::class);
        $smsService->expects($this->never())->method('sendSms');

        $service = $this->makeService(
            $appointmentModel,
            $queryService,
            null,
            $smsService
        );

        $result = $service->send(88, 'sms');

        $this->assertFalse($result['success']);
        $this->assertSame(400, $result['statusCode']);
        $this->assertSame('SMS not sent. Customer phone number not available.', $result['message']);
    }

    public function testSendWhatsAppQueuesDispatchAndReturnsSuccessPayload(): void
    {
        $appointmentModel = $this->createMock(AppointmentModel::class);
        $appointmentModel->expects($this->once())
            ->method('find')
            ->with(77)
            ->willReturn([
                'id' => 77,
                'status' => 'confirmed',
            ]);

        $eventService = $this->createMock(AppointmentEventService::class);
        $eventService->expects($this->once())
            ->method('dispatch')
            ->with('appointment_confirmed', 77, ['whatsapp'], 1);

        $service = $this->makeService(
            $appointmentModel,
            null,
            null,
            null,
            null,
            $eventService
        );

        $result = $service->send(77, 'whatsapp', 'appointment_confirmed');

        $this->assertTrue($result['success']);
        $this->assertSame('whatsapp', $result['data']['channel']);
        $this->assertSame('WhatsApp notification queued for delivery', $result['data']['message']);
    }

    private function makeService(
        AppointmentModel $appointmentModel,
        ?AppointmentQueryService $queryService = null,
        ?AppointmentNotificationService $emailService = null,
        ?NotificationSmsService $smsService = null,
        ?NotificationTemplateService $templateService = null,
        ?AppointmentEventService $eventService = null,
    ): AppointmentManualNotificationService {
        return new AppointmentManualNotificationService(
            $appointmentModel,
            $queryService ?? $this->createMock(AppointmentQueryService::class),
            $emailService ?? $this->createMock(AppointmentNotificationService::class),
            $smsService ?? $this->createMock(NotificationSmsService::class),
            $templateService ?? $this->createMock(NotificationTemplateService::class),
            $eventService ?? $this->createMock(AppointmentEventService::class)
        );
    }
}