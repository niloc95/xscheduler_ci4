<?php

namespace Tests\Unit\Services\Appointment;

use App\Models\AppointmentModel;
use App\Models\ServiceModel;
use App\Models\UserModel;
use App\Services\Appointment\AppointmentFormContextService;
use App\Services\BookingSettingsService;
use App\Services\LocalizationSettingsService;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Test\CIUnitTestCase;

final class AppointmentFormContextServiceTest extends CIUnitTestCase
{
    public function testBuildCreateViewDataReturnsDropdownsAndFieldConfiguration(): void
    {
        $bookingSettings = $this->createMock(BookingSettingsService::class);
        $bookingSettings->method('getFieldConfiguration')->willReturn([
            'first_name' => ['display' => true, 'required' => true],
        ]);
        $bookingSettings->method('getCustomFieldConfiguration')->willReturn([
            'custom_field_1' => ['title' => 'Reference', 'required' => false, 'type' => 'text'],
        ]);

        $localization = $this->createMock(LocalizationSettingsService::class);
        $localization->method('getContext')->willReturn(['timezone' => 'UTC']);

        $userModel = new class extends UserModel {
            public function __construct()
            {
            }

            public function getProvidersWithActiveServices(): array
            {
                return [
                    ['id' => 7, 'name' => 'Dr. Rivera'],
                ];
            }
        };

        $serviceModel = new class extends ServiceModel {
            public function __construct()
            {
            }

            public function where($key = null, $value = null, ?bool $escape = null)
            {
                return $this;
            }

            public function findAll(?int $limit = null, int $offset = 0)
            {
                return [
                    ['id' => 5, 'name' => 'Consultation', 'duration_min' => 45, 'price' => 85.5],
                ];
            }
        };

        $service = new AppointmentFormContextService(
            $bookingSettings,
            $localization,
            new class extends AppointmentModel {
                public function __construct()
                {
                }
            },
            $userModel,
            $serviceModel
        );

        $payload = $service->buildCreateViewData('admin');

        $this->assertSame('Book Appointment', $payload['title']);
        $this->assertSame('appointments', $payload['current_page']);
        $this->assertSame('admin', $payload['user_role']);
        $this->assertSame('Dr. Rivera', $payload['providers'][0]['name']);
        $this->assertSame(45, $payload['services'][0]['duration']);
        $this->assertTrue($payload['fieldConfig']['first_name']['display']);
        $this->assertSame('Reference', $payload['customFields']['custom_field_1']['title']);
    }

    public function testBuildEditViewDataMergesCustomFieldsAndConvertsDisplayTime(): void
    {
        $bookingSettings = $this->createMock(BookingSettingsService::class);
        $bookingSettings->method('getFieldConfiguration')->willReturn([]);
        $bookingSettings->method('getCustomFieldConfiguration')->willReturn([]);

        $localization = $this->createMock(LocalizationSettingsService::class);
        $localization->method('getContext')->willReturn(['timezone' => 'America/New_York']);
        $localization->method('getTimezone')->willReturn('America/New_York');

        $appointmentModel = new class extends AppointmentModel {
            private array $row;

            public function __construct()
            {
                $this->row = [
                    'id' => 11,
                    'hash' => 'apt_hash',
                    'start_at' => '2030-06-15 15:30:00',
                    'customer_first_name' => 'Alex',
                    'customer_custom_fields' => '{"custom_field_1":"VIP"}',
                    'provider_id' => 2,
                    'service_id' => 3,
                ];
            }

            public function select($select = '*', ?bool $escape = null)
            {
                return $this;
            }

            public function join(string $table, string $cond, string $type = '', ?bool $escape = null)
            {
                return $this;
            }

            public function where($key = null, $value = null, ?bool $escape = null)
            {
                return $this;
            }

            public function first()
            {
                return $this->row;
            }
        };

        $service = new AppointmentFormContextService(
            $bookingSettings,
            $localization,
            $appointmentModel,
            new class extends UserModel {
                public function __construct()
                {
                }

                public function getProvidersWithActiveServices(): array
                {
                    return [];
                }
            },
            new class extends ServiceModel {
                public function __construct()
                {
                }

                public function where($key = null, $value = null, ?bool $escape = null)
                {
                    return $this;
                }

                public function findAll(?int $limit = null, int $offset = 0)
                {
                    return [];
                }
            }
        );

        $payload = $service->buildEditViewData('apt_hash', 'provider');

        $this->assertSame('Edit Appointment', $payload['title']);
        $this->assertSame('provider', $payload['user_role']);
        $this->assertSame('Alex', $payload['appointment']['customer_first_name']);
        $this->assertSame('VIP', $payload['appointment']['custom_field_1']);
        $this->assertSame('2030-06-15', $payload['appointment']['date']);
        $this->assertSame('11:30', $payload['appointment']['time']);
        $this->assertFalse($payload['isPastAppointment']);
    }

    public function testBuildEditViewDataThrowsWhenAppointmentMissing(): void
    {
        $service = new AppointmentFormContextService(
            $this->createMock(BookingSettingsService::class),
            $this->createMock(LocalizationSettingsService::class),
            new class extends AppointmentModel {
                public function __construct()
                {
                }

                public function select($select = '*', ?bool $escape = null)
                {
                    return $this;
                }

                public function join(string $table, string $cond, string $type = '', ?bool $escape = null)
                {
                    return $this;
                }

                public function where($key = null, $value = null, ?bool $escape = null)
                {
                    return $this;
                }

                public function first()
                {
                    return null;
                }
            },
            new class extends UserModel {
                public function __construct()
                {
                }

                public function getProvidersWithActiveServices(): array
                {
                    return [];
                }
            },
            new class extends ServiceModel {
                public function __construct()
                {
                }

                public function where($key = null, $value = null, ?bool $escape = null)
                {
                    return $this;
                }

                public function findAll(?int $limit = null, int $offset = 0)
                {
                    return [];
                }
            }
        );

        $this->expectException(PageNotFoundException::class);

        $service->buildEditViewData('missing', 'admin');
    }
}