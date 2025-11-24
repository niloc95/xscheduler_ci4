<?php

namespace Tests\Unit;

use App\Models\AppointmentModel;
use App\Models\CustomerModel;
use App\Models\ServiceModel;
use App\Models\SettingModel;
use App\Models\UserModel;
use App\Services\AvailabilityService;
use App\Services\BookingSettingsService;
use App\Services\LocalizationSettingsService;
use App\Services\PublicBookingService;
use CodeIgniter\Test\CIUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @internal
 */
final class PublicBookingServiceTest extends CIUnitTestCase
{
    public function testBuildViewContextReturnsProviderServiceMetadata(): void
    {
        $bookingSettings = $this->createMock(BookingSettingsService::class);
        $bookingSettings->method('getFieldConfiguration')->willReturn(['notes' => ['display' => true]]);
        $bookingSettings->method('getCustomFieldConfiguration')->willReturn(['intake_notes' => ['type' => 'textarea']]);

        $localization = $this->createMock(LocalizationSettingsService::class);
        $localization->method('getTimezone')->willReturn('America/New_York');
        $localization->method('getTimeFormat')->willReturn('12h');
        $localization->method('getCurrency')->willReturn('USD');
        $localization->method('getCurrencySymbol')->willReturn('$');

        $settings = $this->createMock(SettingModel::class);
        $settings->method('getByKeys')->with(['business.reschedule'])->willReturn(['business.reschedule' => '48h']);

        $users = $this->createUserModelMock([
            'id' => 7,
            'name' => 'Dr. Rivera',
            'color' => '#aabbcc',
            'role' => 'provider',
            'is_active' => true,
        ]);

        $services = $this->createServiceModelMock([
            'id' => 15,
            'name' => 'New Patient Visit',
            'duration_min' => 45,
            'price' => 150.0,
            'active' => 1,
        ]);

        $service = $this->makeService(
            $bookingSettings,
            $this->createMock(AvailabilityService::class),
            $this->createMock(AppointmentModel::class),
            $this->createMock(CustomerModel::class),
            $services,
            $users,
            $localization,
            $settings
        );

        $context = $service->buildViewContext();

        $this->assertSame('Dr. Rivera', $context['providers'][0]['name']);
        $this->assertSame('New Patient Visit', $context['services'][0]['name']);
        $this->assertSame('America/New_York', $context['timezone']);
        $this->assertSame('48 hours', $context['reschedulePolicy']['label']);
    }

    public function testCreateBookingReturnsFormattedAppointmentPayload(): void
    {
        $bookingSettings = $this->createMock(BookingSettingsService::class);
        $bookingSettings->method('getFieldConfiguration')->willReturn($this->fieldConfigStub());
        $bookingSettings->method('getCustomFieldConfiguration')->willReturn([]);

        $localization = $this->createMock(LocalizationSettingsService::class);
        $localization->method('getTimezone')->willReturn('UTC');
        $localization->method('formatCurrency')->willReturnCallback(static fn (float $value): string => '$' . number_format($value, 2));

        $users = $this->createUserModelMock([
            'id' => 3,
            'name' => 'Dr. Patel',
            'color' => '#3366ff',
            'role' => 'provider',
            'is_active' => true,
        ]);

        $services = $this->createServiceModelMock([
            'id' => 22,
            'name' => 'Initial Consult',
            'duration_min' => 30,
            'price' => 90.0,
            'active' => 1,
        ]);

        $availability = $this->createMock(AvailabilityService::class);
        $availability->method('isSlotAvailable')->willReturn(['available' => true]);

        $customers = $this->createCustomerModelMock([
            'id' => 55,
            'email' => 'guest@example.com',
            'phone' => '+15550001111',
        ]);

        $appointments = $this->getMockBuilder(AppointmentModel::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['insert', 'find'])
            ->getMock();
        $appointments->method('insert')->willReturn(910);
        $appointments->method('find')->willReturn([
            'id' => 910,
            'provider_id' => 3,
            'service_id' => 22,
            'customer_id' => 55,
            'start_time' => '2025-12-01 10:00:00',
            'end_time' => '2025-12-01 10:30:00',
            'status' => 'pending',
            'notes' => 'Prefers telehealth',
        ]);

        $service = $this->makeService(
            $bookingSettings,
            $availability,
            $appointments,
            $customers,
            $services,
            $users,
            $localization,
            $this->createMock(SettingModel::class)
        );

        $result = $service->createBooking([
            'provider_id' => 3,
            'service_id' => 22,
            'slot_start' => '2025-12-01T10:00:00Z',
            'first_name' => 'Pat',
            'email' => 'guest@example.com',
            'notes' => 'Prefers telehealth',
        ]);

        $this->assertArrayHasKey('token', $result);
        $this->assertSame('Initial Consult', $result['service']['name']);
        $this->assertSame('Dr. Patel', $result['provider']['name']);
        $this->assertSame('guest@example.com', $result['customer']['email']);
        $this->assertSame('2025-12-01T10:00:00+00:00', $result['start']);
        $this->assertNotEmpty($result['display_range']);
    }

    public function testLookupAppointmentReturnsEnrichedMetadata(): void
    {
        $appointmentRecord = [
            'id' => 11,
            'provider_id' => 9,
            'service_id' => 4,
            'customer_id' => 500,
            'start_time' => '2025-11-30 14:00:00',
            'end_time' => '2025-11-30 14:45:00',
            'status' => 'confirmed',
            'notes' => 'Bring prior labs',
            'public_token' => 'token-123',
            'customer_email' => 'lookup@example.com',
            'customer_phone' => '+15559998888',
        ];

        $appointments = $this->getMockBuilder(AppointmentModel::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['builder'])
            ->getMock();
        $appointments->method('builder')->willReturn($this->createAppointmentBuilder($appointmentRecord));

        $users = $this->createUserModelMock([
            'id' => 9,
            'name' => 'Dr. Singh',
            'color' => '#117733',
            'role' => 'provider',
            'is_active' => true,
        ]);
        $services = $this->createServiceModelMock([
            'id' => 4,
            'name' => 'Follow-up',
            'duration_min' => 45,
            'price' => 110,
            'active' => 1,
        ]);

        $localization = $this->createMock(LocalizationSettingsService::class);
        $localization->method('getTimezone')->willReturn('UTC');
        $localization->method('formatCurrency')->willReturn('$110.00');

        $service = $this->makeService(
            $this->createMock(BookingSettingsService::class),
            $this->createMock(AvailabilityService::class),
            $appointments,
            $this->createMock(CustomerModel::class),
            $services,
            $users,
            $localization,
            $this->createMock(SettingModel::class)
        );

        $result = $service->lookupAppointment('token-123', 'lookup@example.com');

        $this->assertSame('Dr. Singh', $result['provider']['name']);
        $this->assertSame('Follow-up', $result['service']['name']);
        $this->assertSame('lookup@example.com', $result['customer']['email']);
        $this->assertSame('+15559998888', $result['customer']['phone']);
        $this->assertStringContainsString('Sun', $result['display_range']);
    }

    /**
     * @return array<string, array<string, bool>>
     */
    private function fieldConfigStub(): array
    {
        return [
            'first_name' => ['display' => true, 'required' => false],
            'last_name' => ['display' => false, 'required' => false],
            'email' => ['display' => true, 'required' => false],
            'phone' => ['display' => true, 'required' => false],
            'address' => ['display' => false, 'required' => false],
            'notes' => ['display' => false, 'required' => false],
        ];
    }

    private function makeService(
        BookingSettingsService $bookingSettings,
        AvailabilityService $availability,
        AppointmentModel $appointments,
        CustomerModel $customers,
        ServiceModel $services,
        UserModel $users,
        LocalizationSettingsService $localization,
        SettingModel $settings
    ): PublicBookingService {
        return new PublicBookingService(
            $bookingSettings,
            $availability,
            $appointments,
            $customers,
            $services,
            $users,
            $localization,
            $settings
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function createUserModelMock(array $row): UserModel
    {
        /** @var UserModel&MockObject $mock */
        $mock = $this->getMockBuilder(UserModel::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findAll', 'first', 'find'])
            ->addMethods(['where', 'orderBy'])
            ->getMock();
        $mock->method('where')->willReturnSelf();
        $mock->method('orderBy')->willReturnSelf();
        $mock->method('findAll')->willReturn([$row]);
        $mock->method('first')->willReturn($row);
        $mock->method('find')->willReturn($row);
        return $mock;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function createServiceModelMock(array $row): ServiceModel
    {
        /** @var ServiceModel&MockObject $mock */
        $mock = $this->getMockBuilder(ServiceModel::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findAll', 'first', 'find'])
            ->addMethods(['where', 'orderBy'])
            ->getMock();
        $mock->method('where')->willReturnSelf();
        $mock->method('orderBy')->willReturnSelf();
        $mock->method('findAll')->willReturn([$row]);
        $mock->method('first')->willReturn($row);
        $mock->method('find')->willReturn($row);
        return $mock;
    }

    /**
     * @param array<string, mixed> $customerRow
     */
    private function createCustomerModelMock(array $customerRow): CustomerModel
    {
        /** @var CustomerModel&MockObject $mock */
        $mock = $this->getMockBuilder(CustomerModel::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['insert', 'update', 'find', 'first'])
            ->addMethods(['where'])
            ->getMock();
        $mock->method('where')->willReturnSelf();
        $mock->method('first')->willReturn(null);
        $mock->method('insert')->willReturn($customerRow['id']);
        $mock->method('find')->willReturn($customerRow);
        return $mock;
    }

    /**
     * @param array<string, mixed> $record
     */
    private function createAppointmentBuilder(array $record): object
    {
        return new class($record) {
            /** @param array<string, mixed> $record */
            public function __construct(private array $record)
            {
            }

            public function select(string $fields): self
            {
                return $this;
            }

            public function join(string $table, string $condition, string $type = 'left'): self
            {
                return $this;
            }

            public function where(string $field, string $value): self
            {
                return $this;
            }

            public function get(): object
            {
                $record = $this->record;

                return new class($record) {
                    /** @param array<string, mixed> $record */
                    public function __construct(private array $record)
                    {
                    }

                    /** @return array<string, mixed> */
                    public function getRowArray(): array
                    {
                        return $this->record;
                    }
                };
            }
        };
    }
}
