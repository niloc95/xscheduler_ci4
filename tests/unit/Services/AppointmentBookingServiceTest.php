<?php

namespace Tests\Unit\Services;

use App\Models\AppointmentModel;
use App\Models\CustomerModel;
use App\Models\LocationModel;
use App\Models\ServiceModel;
use App\Services\AppointmentBookingService;
use App\Services\AppointmentEventService;
use App\Services\AvailabilityService;
use App\Services\BusinessHoursService;
use App\Services\LocalizationSettingsService;
use App\Services\TimezoneService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class AppointmentBookingServiceTest extends CIUnitTestCase
{
    public function testCreateAppointmentRejectsInvalidService(): void
    {
        $serviceModel = $this->createMock(ServiceModel::class);
        $serviceModel->expects($this->once())
            ->method('find')
            ->with(99)
            ->willReturn(null);

        $service = new AppointmentBookingService(
            $this->createMock(AppointmentModel::class),
            $this->createMock(CustomerModel::class),
            $serviceModel,
            $this->createMock(BusinessHoursService::class),
            $this->createMock(AvailabilityService::class),
            $this->createMock(TimezoneService::class),
            $this->createMock(LocalizationSettingsService::class),
            $this->createMock(LocationModel::class),
            $this->createMock(AppointmentEventService::class)
        );

        $result = $service->createAppointment([
            'service_id' => 99,
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('Invalid service selected', $result['message']);
    }

    public function testCreateAppointmentRejectsMissingProviderLocationSelection(): void
    {
        $serviceModel = $this->createMock(ServiceModel::class);
        $serviceModel->method('find')->willReturn(['id' => 5, 'duration_min' => 30]);

        $localization = $this->createMock(LocalizationSettingsService::class);
        $localization->method('getTimezone')->willReturn('UTC');

        $locationModel = $this->createMock(LocationModel::class);
        $locationModel->expects($this->once())
            ->method('getProviderLocations')
            ->with(7, true)
            ->willReturn([
                ['id' => 200, 'name' => 'Main Office'],
            ]);

        $businessHours = $this->createMock(BusinessHoursService::class);
        $businessHours->expects($this->never())->method('validateAppointmentTime');

        $availability = $this->createMock(AvailabilityService::class);
        $availability->expects($this->never())->method('isSlotAvailable');

        $service = new AppointmentBookingService(
            $this->createMock(AppointmentModel::class),
            $this->createMock(CustomerModel::class),
            $serviceModel,
            $businessHours,
            $availability,
            $this->createMock(TimezoneService::class),
            $localization,
            $locationModel,
            $this->createMock(AppointmentEventService::class)
        );

        $result = $service->createAppointment([
            'service_id' => 5,
            'provider_id' => 7,
            'appointment_date' => '2026-05-15',
            'appointment_time' => '09:00',
        ], 'UTC');

        $this->assertFalse($result['success']);
        $this->assertSame('Please select a location for this provider.', $result['message']);
        $this->assertSame(['location_id' => 'required'], $result['errors']);
    }

    public function testCreateAppointmentUsesExistingCustomerAndDispatchesNotifications(): void
    {
        $serviceModel = $this->createMock(ServiceModel::class);
        $serviceModel->method('find')->willReturn(['id' => 5, 'duration_min' => 30]);

        $localization = $this->createMock(LocalizationSettingsService::class);
        $localization->method('getTimezone')->willReturn('UTC');

        $locationModel = $this->createMock(LocationModel::class);
        $locationModel->expects($this->once())
            ->method('getProviderLocations')
            ->with(7, true)
            ->willReturn([]);
        $locationModel->expects($this->never())->method('getLocationSnapshot');

        $businessHours = $this->createMock(BusinessHoursService::class);
        $businessHours->expects($this->once())
            ->method('validateAppointmentTime')
            ->willReturn(['valid' => true]);

        $availability = $this->createMock(AvailabilityService::class);
        $availability->expects($this->once())
            ->method('isSlotAvailable')
            ->with(7, '2026-05-15 09:00:00', '2026-05-15 09:30:00', 'UTC', null, null)
            ->willReturn(['available' => true]);

        $customerModel = $this->createMock(CustomerModel::class);
        $customerModel->expects($this->once())
            ->method('find')
            ->with(55)
            ->willReturn(['id' => 55, 'email' => 'guest@example.com']);
        $customerModel->expects($this->never())->method('insert');

        $appointmentModel = $this->createMock(AppointmentModel::class);
        $appointmentModel->expects($this->once())
            ->method('insert')
            ->with($this->callback(static function (array $data): bool {
                return $data['customer_id'] === 55
                    && $data['provider_id'] === 7
                    && $data['service_id'] === 5
                    && $data['start_at'] === '2026-05-15 09:00:00'
                    && $data['end_at'] === '2026-05-15 09:30:00'
                    && $data['status'] === 'pending';
            }))
            ->willReturn(123);

        $eventService = new class extends AppointmentEventService {
            public array $calls = [];

            public function dispatch(string $eventType, int $appointmentId, array $channels = self::DEFAULT_CHANNELS, int $businessId = self::DEFAULT_BUSINESS_ID): void
            {
                $this->calls[] = [$eventType, $appointmentId, $channels, $businessId];
            }
        };

        $service = new AppointmentBookingService(
            $appointmentModel,
            $customerModel,
            $serviceModel,
            $businessHours,
            $availability,
            $this->createMock(TimezoneService::class),
            $localization,
            $locationModel,
            $eventService
        );

        $result = $service->createAppointment([
            'service_id' => 5,
            'provider_id' => 7,
            'customer_id' => 55,
            'appointment_date' => '2026-05-15',
            'appointment_time' => '09:00',
            'notification_types' => ['email'],
        ], 'UTC');

        $this->assertTrue($result['success']);
        $this->assertSame(123, $result['appointmentId']);
        $this->assertSame([['appointment_confirmed', 123, ['email'], 1]], $eventService->calls);
    }

    public function testCreateAppointmentReturnsCustomerCreationFailure(): void
    {
        $serviceModel = $this->createMock(ServiceModel::class);
        $serviceModel->method('find')->willReturn(['id' => 5, 'duration_min' => 30]);

        $localization = $this->createMock(LocalizationSettingsService::class);
        $localization->method('getTimezone')->willReturn('UTC');

        $locationModel = $this->createMock(LocationModel::class);
        $locationModel->method('getProviderLocations')->willReturn([]);

        $businessHours = $this->createMock(BusinessHoursService::class);
        $businessHours->method('validateAppointmentTime')->willReturn(['valid' => true]);

        $availability = $this->createMock(AvailabilityService::class);
        $availability->method('isSlotAvailable')->willReturn(['available' => true]);

        $customerModel = new class extends CustomerModel {
            public array $whereCalls = [];

            public function __construct()
            {
            }

            public function where($key = null, $value = null, ?bool $escape = null)
            {
                $this->whereCalls[] = [$key, $value];
                return $this;
            }

            public function first()
            {
                return null;
            }

            public function insert($data = null, bool $returnID = true)
            {
                return false;
            }

            public function errors(bool $forceDB = false): array
            {
                return ['email' => 'invalid'];
            }
        };

        $appointmentModel = $this->createMock(AppointmentModel::class);
        $appointmentModel->expects($this->never())->method('insert');

        $eventService = $this->createMock(AppointmentEventService::class);
        $eventService->expects($this->never())->method('dispatch');

        $service = new AppointmentBookingService(
            $appointmentModel,
            $customerModel,
            $serviceModel,
            $businessHours,
            $availability,
            $this->createMock(TimezoneService::class),
            $localization,
            $locationModel,
            $eventService
        );

        $result = $service->createAppointment([
            'service_id' => 5,
            'provider_id' => 7,
            'appointment_date' => '2026-05-15',
            'appointment_time' => '09:00',
            'customer_first_name' => 'Pat',
            'customer_email' => 'guest@example.com',
        ], 'UTC');

        $this->assertFalse($result['success']);
        $this->assertSame('Failed to create customer record', $result['message']);
        $this->assertSame(['email' => 'invalid'], $result['errors']);
    $this->assertSame([['email', 'guest@example.com']], $customerModel->whereCalls);
    }

    public function testUpdateAppointmentRejectsInvalidStatus(): void
    {
        $appointmentModel = $this->createMock(AppointmentModel::class);
        $appointmentModel->expects($this->once())
            ->method('find')
            ->with(88)
            ->willReturn([
                'id' => 88,
                'service_id' => 5,
                'provider_id' => 7,
                'status' => 'pending',
                'start_at' => '2026-05-14 09:00:00',
                'location_id' => null,
            ]);
        $appointmentModel->expects($this->never())->method('update');

        $serviceModel = $this->createMock(ServiceModel::class);
        $serviceModel->expects($this->once())
            ->method('find')
            ->with(5)
            ->willReturn(['id' => 5, 'duration_min' => 30]);

        $localization = $this->createMock(LocalizationSettingsService::class);
        $localization->expects($this->never())->method('getTimezone');

        $locationModel = $this->createMock(LocationModel::class);
        $locationModel->expects($this->never())->method('getProviderLocations');

        $businessHours = $this->createMock(BusinessHoursService::class);
        $businessHours->expects($this->never())->method('validateAppointmentTime');

        $availability = $this->createMock(AvailabilityService::class);
        $availability->expects($this->never())->method('isSlotAvailable');

        $eventService = new class extends AppointmentEventService {
            public array $calls = [];

            public function dispatch(string $eventType, int $appointmentId, array $channels = self::DEFAULT_CHANNELS, int $businessId = self::DEFAULT_BUSINESS_ID): void
            {
                $this->calls[] = [$eventType, $appointmentId, $channels, $businessId];
            }
        };

        $service = new AppointmentBookingService(
            $appointmentModel,
            $this->createMock(CustomerModel::class),
            $serviceModel,
            $businessHours,
            $availability,
            $this->createMock(TimezoneService::class),
            $localization,
            $locationModel,
            $eventService
        );

        $result = $service->updateAppointment(88, [
            'status' => 'definitely-not-valid',
        ], 'UTC');

        $this->assertFalse($result['success']);
        $this->assertSame('Invalid appointment status', $result['message']);
        $this->assertSame(['status' => 'invalid'], $result['errors']);
        $this->assertSame([], $eventService->calls);
    }

    public function testUpdateAppointmentRejectsUnavailableLocationForProviderWhenTimeIsUnchanged(): void
    {
        $appointmentModel = $this->createMock(AppointmentModel::class);
        $appointmentModel->expects($this->once())
            ->method('find')
            ->with(88)
            ->willReturn([
                'id' => 88,
                'service_id' => 5,
                'provider_id' => 7,
                'status' => 'pending',
                'start_at' => '2026-05-14 09:00:00',
                'location_id' => null,
            ]);
        $appointmentModel->expects($this->never())->method('update');

        $serviceModel = $this->createMock(ServiceModel::class);
        $serviceModel->expects($this->once())
            ->method('find')
            ->with(5)
            ->willReturn(['id' => 5, 'duration_min' => 30]);

        $locationModel = $this->createMock(LocationModel::class);
        $locationModel->expects($this->once())
            ->method('getProviderLocations')
            ->with(7, true)
            ->willReturn([
                ['id' => 200, 'name' => 'Main Office'],
            ]);
        $locationModel->expects($this->never())->method('getLocationSnapshot');

        $businessHours = $this->createMock(BusinessHoursService::class);
        $businessHours->expects($this->never())->method('validateAppointmentTime');

        $availability = $this->createMock(AvailabilityService::class);
        $availability->expects($this->never())->method('isSlotAvailable');

        $eventService = new class extends AppointmentEventService {
            public array $calls = [];

            public function dispatch(string $eventType, int $appointmentId, array $channels = self::DEFAULT_CHANNELS, int $businessId = self::DEFAULT_BUSINESS_ID): void
            {
                $this->calls[] = [$eventType, $appointmentId, $channels, $businessId];
            }
        };

        $service = new AppointmentBookingService(
            $appointmentModel,
            $this->createMock(CustomerModel::class),
            $serviceModel,
            $businessHours,
            $availability,
            $this->createMock(TimezoneService::class),
            $this->createMock(LocalizationSettingsService::class),
            $locationModel,
            $eventService
        );

        $result = $service->updateAppointment(88, [
            'location_id' => 999,
        ], 'UTC');

        $this->assertFalse($result['success']);
        $this->assertSame('Selected location is unavailable for this provider.', $result['message']);
        $this->assertSame(['location_id' => 'invalid'], $result['errors']);
        $this->assertSame([], $eventService->calls);
    }
}