<?php

namespace Tests\Unit\Services\Appointment;

use App\Models\AppointmentModel;
use App\Models\CustomerModel;
use App\Services\Appointment\AppointmentDateTimeNormalizer;
use App\Services\Appointment\AppointmentFormMutationService;
use App\Services\Appointment\AppointmentFormSubmissionService;
use App\Services\AppointmentBookingService;
use App\Services\AppointmentNotificationService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Validation\ValidationInterface;

final class AppointmentFormMutationServiceTest extends CIUnitTestCase
{
    public function testCreateFromFormPayloadReturnsValidationFailureContract(): void
    {
        $validation = $this->createMock(ValidationInterface::class);
        $validation->method('setRules')->willReturnSelf();
        $validation->method('run')->willReturn(false);
        $validation->method('getErrors')->willReturn(['customer_email' => 'The customer_email field is required.']);

        $service = new AppointmentFormMutationService(
            $validation,
            new AppointmentDateTimeNormalizer('UTC'),
            $this->createMock(AppointmentFormSubmissionService::class),
            $this->createMock(AppointmentBookingService::class),
            $this->createMock(CustomerModel::class),
            $this->createMock(AppointmentModel::class),
            $this->createMock(AppointmentNotificationService::class)
        );

        $result = $service->createFromFormPayload([], 'UTC');

        $this->assertFalse($result['success']);
        $this->assertSame(422, $result['statusCode']);
        $this->assertSame('Validation failed', $result['message']);
        $this->assertSame('errors', $result['flashType']);
        $this->assertArrayHasKey('customer_email', $result['errors']);
    }

    public function testCreateFromFormPayloadNormalizesSuccessfulBookingResult(): void
    {
        $validation = $this->createMock(ValidationInterface::class);
        $validation->method('setRules')->willReturnSelf();
        $validation->method('run')->willReturn(true);

        $submission = $this->createMock(AppointmentFormSubmissionService::class);
        $submission->method('buildCreateBookingData')->willReturn(['provider_id' => 1]);

        $booking = $this->createMock(AppointmentBookingService::class);
        $booking->method('createAppointment')->willReturn([
            'success' => true,
            'message' => 'Appointment booked successfully! Confirmation will be sent shortly.',
            'appointmentId' => 88,
        ]);

        $service = new AppointmentFormMutationService(
            $validation,
            new AppointmentDateTimeNormalizer('UTC'),
            $submission,
            $booking,
            $this->createMock(CustomerModel::class),
            $this->createMock(AppointmentModel::class),
            $this->createMock(AppointmentNotificationService::class)
        );

        $result = $service->createFromFormPayload([
            'appointment_date' => '2031-01-06',
            'appointment_time' => '09:00',
        ], 'UTC');

        $this->assertTrue($result['success']);
        $this->assertSame(200, $result['statusCode']);
        $this->assertSame(88, $result['appointmentId']);
        $this->assertSame(base_url('appointments'), $result['redirect']);
    }

    public function testUpdateFromFormPayloadReturnsNotFoundMarkerForMissingAppointment(): void
    {
        $appointmentModel = $this->createMock(AppointmentModel::class);
        $appointmentModel->method('findByHash')->with('missing-hash')->willReturn(null);

        $service = new AppointmentFormMutationService(
            $this->createMock(ValidationInterface::class),
            new AppointmentDateTimeNormalizer('UTC'),
            $this->createMock(AppointmentFormSubmissionService::class),
            $this->createMock(AppointmentBookingService::class),
            $this->createMock(CustomerModel::class),
            $appointmentModel,
            $this->createMock(AppointmentNotificationService::class)
        );

        $result = $service->updateFromFormPayload('missing-hash', [], 'UTC');

        $this->assertTrue($result['notFound']);
        $this->assertSame('Appointment not found', $result['message']);
    }

    public function testUpdateFromFormPayloadUpdatesCustomerAndNormalizesSuccess(): void
    {
        $validation = $this->createMock(ValidationInterface::class);
        $validation->method('setRules')->willReturnSelf();
        $validation->method('run')->willReturn(true);

        $appointmentModel = $this->createMock(AppointmentModel::class);
        $appointmentModel->method('findByHash')->with('apt-hash')->willReturn([
            'id' => 21,
            'customer_id' => 45,
            'start_at' => '2031-01-06 09:00:00',
        ]);

        $submission = $this->createMock(AppointmentFormSubmissionService::class);
        $submission->method('buildCustomerUpdateData')->willReturn(['first_name' => 'Updated']);
        $submission->method('buildUpdateAppointmentData')->willReturn(['status' => 'confirmed']);

        $customerModel = $this->createMock(CustomerModel::class);
        $customerModel->expects($this->once())->method('update')->with(45, ['first_name' => 'Updated', 'phone' => '']);

        $booking = $this->createMock(AppointmentBookingService::class);
        $booking->method('updateAppointment')->willReturn([
            'success' => true,
            'message' => 'Appointment updated successfully!',
        ]);

        $notifications = $this->createMock(AppointmentNotificationService::class);
        $notifications->expects($this->once())
            ->method('resetReminderSentIfTimeChanged')
            ->with(21, '2031-01-06 09:00:00', '2031-01-06 10:00:00');

        $service = new AppointmentFormMutationService(
            $validation,
            new AppointmentDateTimeNormalizer('UTC'),
            $submission,
            $booking,
            $customerModel,
            $appointmentModel,
            $notifications
        );

        $result = $service->updateFromFormPayload('apt-hash', [
            'appointment_date' => '2031-01-06',
            'appointment_time' => '10:00',
            'status' => 'confirmed',
        ], 'UTC');

        $this->assertTrue($result['success']);
        $this->assertSame('Appointment updated successfully!', $result['message']);
        $this->assertSame(base_url('appointments'), $result['redirect']);
    }
}