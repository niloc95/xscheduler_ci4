<?php

namespace Tests\Unit\Services;

use App\Models\AppointmentModel;
use App\Models\LocationModel;
use App\Services\Appointment\AppointmentAvailabilityService;
use App\Services\Appointment\AppointmentDateTimeNormalizer;
use App\Services\Appointment\AppointmentMutationService;
use App\Services\AppointmentBookingService;
use App\Services\AvailabilityService;
use App\Services\LocalizationSettingsService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class AppointmentBoundaryServicesTest extends CIUnitTestCase
{
    public function testAppointmentMutationServiceCreateRejectsInvalidNormalizedDateTime(): void
    {
        $bookingService = $this->createMock(AppointmentBookingService::class);
        $bookingService->expects($this->never())->method('createAppointment');

        $service = new AppointmentMutationService(
            $this->createMock(AppointmentModel::class),
            $bookingService,
            new AppointmentDateTimeNormalizer('UTC')
        );

        $result = $service->createFromApiPayload([
            'date' => 'not-a-date',
            'start' => 'not-a-time',
        ], 'America/New_York');

        $this->assertFalse($result['success']);
        $this->assertSame(400, $result['statusCode']);
        $this->assertSame('Invalid appointment date/time input', $result['message']);
    }

    public function testAppointmentMutationServiceCreateMapsConflictFromBookingService(): void
    {
        $bookingService = $this->createMock(AppointmentBookingService::class);
        $bookingService->expects($this->once())
            ->method('createAppointment')
            ->with($this->callback(static function (array $payload): bool {
                return $payload['provider_id'] === 7
                    && $payload['service_id'] === 11
                    && $payload['appointment_date'] === '2026-04-10'
                    && $payload['appointment_time'] === '09:00'
                    && $payload['customer_first_name'] === 'Pat Doe';
            }), 'UTC')
            ->willReturn([
                'success' => false,
                'message' => 'Conflicting appointment exists',
                'errors' => ['slot' => 'taken'],
            ]);

        $service = new AppointmentMutationService(
            $this->createMock(AppointmentModel::class),
            $bookingService,
            new AppointmentDateTimeNormalizer('UTC')
        );

        $result = $service->createFromApiPayload([
            'date' => '2026-04-10',
            'start' => '09:00',
            'providerId' => 7,
            'serviceId' => 11,
            'name' => 'Pat Doe',
            'email' => 'pat@example.com',
            'phone' => '+15550001111',
            'notes' => 'Morning appointment',
        ], 'UTC');

        $this->assertFalse($result['success']);
        $this->assertSame(409, $result['statusCode']);
        $this->assertSame('CONFLICT', $result['code']);
        $this->assertSame(['slot' => 'taken'], $result['errors']);
    }

    public function testAppointmentMutationServiceUpdateRejectsMissingAppointment(): void
    {
        $appointmentModel = $this->createMock(AppointmentModel::class);
        $appointmentModel->expects($this->once())
            ->method('find')
            ->with(15)
            ->willReturn(null);

        $service = new AppointmentMutationService(
            $appointmentModel,
            $this->createMock(AppointmentBookingService::class),
            new AppointmentDateTimeNormalizer('UTC')
        );

        $result = $service->updateFromApiPayload(15, ['status' => 'confirmed']);

        $this->assertFalse($result['success']);
        $this->assertSame(404, $result['statusCode']);
        $this->assertSame(['appointment_id' => 15], $result['errors']);
    }

    public function testAppointmentMutationServiceUpdateRejectsInvalidStatus(): void
    {
        $appointmentModel = $this->createMock(AppointmentModel::class);
        $appointmentModel->method('find')->willReturn(['id' => 15, 'status' => 'pending']);

        $service = new AppointmentMutationService(
            $appointmentModel,
            $this->createMock(AppointmentBookingService::class),
            new AppointmentDateTimeNormalizer('UTC')
        );

        $result = $service->updateFromApiPayload(15, ['status' => 'invalid-status']);

        $this->assertFalse($result['success']);
        $this->assertSame(400, $result['statusCode']);
        $this->assertSame('Invalid status', $result['message']);
        $this->assertSame(['valid_statuses' => ['pending', 'confirmed', 'completed', 'cancelled', 'no-show']], $result['errors']);
    }

    public function testAppointmentMutationServiceUpdateRejectsEmptyUpdatePayload(): void
    {
        $appointmentModel = $this->createMock(AppointmentModel::class);
        $appointmentModel->method('find')->willReturn(['id' => 15, 'status' => 'pending']);

        $service = new AppointmentMutationService(
            $appointmentModel,
            $this->createMock(AppointmentBookingService::class),
            new AppointmentDateTimeNormalizer('UTC')
        );

        $result = $service->updateFromApiPayload(15, []);

        $this->assertFalse($result['success']);
        $this->assertSame(400, $result['statusCode']);
        $this->assertSame('No updatable fields provided', $result['message']);
    }

    public function testAppointmentMutationServiceUpdateMapsRescheduleConflictFromBookingService(): void
    {
        $appointmentModel = $this->createMock(AppointmentModel::class);
        $appointmentModel->expects($this->once())
            ->method('find')
            ->with(15)
            ->willReturn([
                'id' => 15,
                'status' => 'pending',
                'provider_id' => 7,
                'service_id' => 11,
            ]);

        $bookingService = $this->createMock(AppointmentBookingService::class);
        $bookingService->expects($this->once())
            ->method('updateAppointment')
            ->with(
                15,
                [
                    'appointment_date' => '2026-05-20',
                    'appointment_time' => '14:00',
                ],
                $this->isType('string'),
                'appointment_rescheduled',
                ['email', 'whatsapp']
            )
            ->willReturn([
                'success' => false,
                'message' => 'Conflicts with 1 existing appointment(s)',
                'errors' => [],
            ]);

        $service = new AppointmentMutationService(
            $appointmentModel,
            $bookingService,
            new AppointmentDateTimeNormalizer('UTC')
        );

        $result = $service->updateFromApiPayload(15, [
            'date' => '2026-05-20',
            'start' => '14:00',
            'timezone' => 'UTC',
        ], 'UTC');

        $this->assertFalse($result['success']);
        $this->assertSame(422, $result['statusCode']);
        $this->assertSame('Conflicts with 1 existing appointment(s)', $result['message']);
        $this->assertSame([], $result['errors']);
    }

    public function testAppointmentMutationServiceUpdateStatusNormalizesAliasAndReturnsSuccessShape(): void
    {
        $appointmentModel = $this->createMock(AppointmentModel::class);
        $appointmentModel->expects($this->once())
            ->method('find')
            ->with(21)
            ->willReturn(['id' => 21, 'status' => 'pending']);

        $bookingService = $this->createMock(AppointmentBookingService::class);
        $bookingService->expects($this->once())
            ->method('updateAppointment')
            ->with(
                21,
                ['status' => 'no-show'],
                $this->isType('string'),
                'appointment_no_show',
                ['email', 'whatsapp']
            )
            ->willReturn(['success' => true]);

        $service = new AppointmentMutationService(
            $appointmentModel,
            $bookingService,
            new AppointmentDateTimeNormalizer('UTC')
        );

        $result = $service->updateStatus(21, 'no_show');

        $this->assertTrue($result['success']);
        $this->assertSame('no-show', $result['data']['status']);
        $this->assertSame('Appointment status updated successfully', $result['message']);
    }

    public function testAppointmentMutationServiceCancelMapsBookingServiceSuccess(): void
    {
        $appointmentModel = $this->createMock(AppointmentModel::class);
        $appointmentModel->expects($this->once())
            ->method('find')
            ->with(33)
            ->willReturn(['id' => 33, 'status' => 'confirmed']);

        $bookingService = $this->createMock(AppointmentBookingService::class);
        $bookingService->expects($this->once())
            ->method('updateAppointment')
            ->with(
                33,
                ['status' => 'cancelled'],
                $this->isType('string'),
                'appointment_cancelled',
                ['email', 'whatsapp']
            )
            ->willReturn(['success' => true]);

        $service = new AppointmentMutationService(
            $appointmentModel,
            $bookingService,
            new AppointmentDateTimeNormalizer('UTC')
        );

        $result = $service->cancelAppointment(33);

        $this->assertTrue($result['success']);
        $this->assertSame(['ok' => true], $result['data']);
        $this->assertSame('Appointment cancelled successfully', $result['message']);
    }

    public function testAppointmentAvailabilityServiceRejectsMissingRequiredFields(): void
    {
        $service = new AppointmentAvailabilityService(
            $this->createMock(AvailabilityService::class),
            $this->createMock(LocalizationSettingsService::class),
            $this->createMock(LocationModel::class)
        );

        $result = $service->checkFromPayload(['provider_id' => 3]);

        $this->assertFalse($result['success']);
        $this->assertSame(400, $result['statusCode']);
        $this->assertSame('Missing required fields', $result['message']);
    }

    public function testAppointmentAvailabilityServiceRequiresLocationWhenProviderHasActiveLocations(): void
    {
        $locationModel = $this->createMock(LocationModel::class);
        $locationModel->expects($this->once())
            ->method('getProviderLocations')
            ->with(8, true)
            ->willReturn([
                ['id' => 100, 'name' => 'Main Office'],
            ]);

        $localization = $this->createMock(LocalizationSettingsService::class);
        $localization->expects($this->once())
            ->method('getTimezone')
            ->willReturn('UTC');

        $availability = $this->createMock(AvailabilityService::class);
        $availability->expects($this->never())->method('isSlotAvailable');

        $service = new AppointmentAvailabilityService($availability, $localization, $locationModel);

        $result = $service->checkFromPayload([
            'provider_id' => 8,
            'service_id' => 5,
            'start_time' => '2026-05-01 10:00:00',
            'timezone' => 'Invalid/Timezone',
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame(422, $result['statusCode']);
        $this->assertSame('location_id is required for providers with active locations', $result['message']);
    }

    public function testAppointmentAvailabilityServiceRejectsUnavailableRequestedLocation(): void
    {
        $locationModel = $this->createMock(LocationModel::class);
        $locationModel->expects($this->once())
            ->method('getProviderLocations')
            ->with(8, true)
            ->willReturn([
                ['id' => 100, 'name' => 'Main Office'],
                ['id' => 101, 'name' => 'North Office'],
            ]);

        $localization = $this->createMock(LocalizationSettingsService::class);
        $localization->expects($this->never())->method('getTimezone');

        $availability = $this->createMock(AvailabilityService::class);
        $availability->expects($this->never())->method('isSlotAvailable');

        $service = new AppointmentAvailabilityService($availability, $localization, $locationModel);

        $result = $service->checkFromPayload([
            'provider_id' => 8,
            'service_id' => 5,
            'start_time' => '2026-05-01 10:00:00',
            'location_id' => 999,
            'timezone' => 'UTC',
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame(422, $result['statusCode']);
        $this->assertSame('Selected location is unavailable for this provider', $result['message']);
    }
}