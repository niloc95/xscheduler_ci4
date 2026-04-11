<?php

namespace Tests\Unit\Controllers;

use App\Controllers\CustomerManagement;
use App\Models\CustomerModel;
use App\Services\BookingSettingsService;
use App\Services\CustomerDeletionService;
use App\Services\CustomerAppointmentService;
use CodeIgniter\Test\CIUnitTestCase;
use ReflectionProperty;

final class CustomerManagementControllerTest extends CIUnitTestCase
{
    public function testConstructorUsesProvidedDependencies(): void
    {
        $customers = $this->createMock(CustomerModel::class);
        $bookingSettings = $this->createMock(BookingSettingsService::class);
        $appointmentService = $this->createMock(CustomerAppointmentService::class);
        $customerDeletionService = $this->createMock(CustomerDeletionService::class);

        $controller = new CustomerManagement($customers, $bookingSettings, $appointmentService, $customerDeletionService);

        $this->assertSame($customers, $this->readProperty($controller, 'customers'));
        $this->assertSame($bookingSettings, $this->readProperty($controller, 'bookingSettings'));
        $this->assertSame($appointmentService, $this->readProperty($controller, 'appointmentService'));
        $this->assertSame($customerDeletionService, $this->readProperty($controller, 'customerDeletionService'));
    }

    private function readProperty(object $instance, string $property): mixed
    {
        $reflection = new ReflectionProperty($instance, $property);
        $reflection->setAccessible(true);

        return $reflection->getValue($instance);
    }
}