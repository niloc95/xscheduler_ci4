<?php

namespace Tests\Integration;

use App\Models\AppointmentModel;
use App\Models\ProviderScheduleModel;
use App\Models\UserModel;
use App\Services\Appointment\AppointmentFormatterService;
use App\Services\Appointment\AppointmentQueryService;
use App\Services\Calendar\CalendarRangeService;
use App\Services\Calendar\DayViewService;
use App\Services\Calendar\EventLayoutService;
use App\Services\Calendar\TimeGridService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Integration tests for DayViewService
 * Tests multi-provider scenarios, working hours, and overlap handling
 * 
 * @internal
 */
final class DayViewServiceIntegrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = 'App';
    protected DayViewService $service;
    protected AppointmentModel $appointmentModel;
    protected ProviderScheduleModel $scheduleModel;
    protected UserModel $userModel;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->appointmentModel = new AppointmentModel();
        $this->scheduleModel = new ProviderScheduleModel();
        $this->userModel = new UserModel();
        
        // Initialize service with dependencies
        $this->service = new DayViewService(
            new CalendarRangeService(),
            new AppointmentQueryService(),
            new AppointmentFormatterService(),
            new TimeGridService(),
            new EventLayoutService(),
            $this->scheduleModel
        );
    }

    /**
     * Test: Multi-provider day view with overlapping appointments
     * Validates that overlapping appointments are assigned correct columns
     */
    public function testMultiProviderDayViewWithOverlappingAppointments(): void
    {
        // Seed two providers
        $provider1Id = $this->seedProvider('Dr. Sarah Chen', 'provider');
        $provider2Id = $this->seedProvider('Dr. James Okafor', 'provider');
        
        // Seed provider schedules (Monday 08:00-17:00)
        $this->seedProviderSchedule($provider1Id, 'monday', '08:00:00', '17:00:00', true);
        $this->seedProviderSchedule($provider2Id, 'monday', '08:00:00', '17:00:00', true);
        
        // Seed overlapping appointments for provider 1 on Monday March 9, 2026
        $customerId = $this->seedCustomer('John Doe');
        $serviceId = $this->seedService('Consultation', 30);
        
        $apt1 = $this->seedAppointment([
            'provider_id' => $provider1Id,
            'customer_id' => $customerId,
            'service_id' => $serviceId,
            'start_at' => '2026-03-09 09:00:00',
            'end_at' => '2026-03-09 09:30:00',
            'status' => 'confirmed',
        ]);
        
        $apt2 = $this->seedAppointment([
            'provider_id' => $provider1Id,
            'customer_id' => $customerId,
            'service_id' => $serviceId,
            'start_at' => '2026-03-09 09:15:00',
            'end_at' => '2026-03-09 09:45:00',
            'status' => 'confirmed',
        ]);
        
        // Build day view for Monday March 9, 2026
        $result = $this->service->build('2026-03-09', [
            'provider_ids' => [$provider1Id, $provider2Id],
        ]);
        
        // Assertions
        $this->assertIsArray($result);
        $this->assertArrayHasKey('providerColumns', $result);
        $this->assertCount(2, $result['providerColumns']);
        
        // Find provider 1's column
        $provider1Column = null;
        foreach ($result['providerColumns'] as $col) {
            if ($col['provider']['id'] === $provider1Id) {
                $provider1Column = $col;
                break;
            }
        }
        
        $this->assertNotNull($provider1Column);
        $this->assertArrayHasKey('workingHours', $provider1Column);
        $this->assertEquals('08:00', $provider1Column['workingHours']['startTime']);
        $this->assertEquals('17:00', $provider1Column['workingHours']['endTime']);
        $this->assertEquals('provider_schedule', $provider1Column['workingHours']['source']);
        $this->assertTrue($provider1Column['workingHours']['isActive']);
        
        // Check that appointments have overlap metadata
        $appointments = $result['appointments'];
        $this->assertCount(2, $appointments);
        
        // Both appointments should have _column and _columns_total metadata
        foreach ($appointments as $apt) {
            $this->assertArrayHasKey('_column', $apt);
            $this->assertArrayHasKey('_columns_total', $apt);
            $this->assertEquals(2, $apt['_columns_total'], 'Two overlapping appointments should require 2 columns');
        }
        
        // Appointments should be in different columns
        $this->assertNotEquals(
            $appointments[0]['_column'],
            $appointments[1]['_column'],
            'Overlapping appointments must be in different columns'
        );
    }

    /**
     * Test: Provider with inactive schedule falls back to business hours
     */
    public function testProviderWithInactiveScheduleFallsBackToBusinessHours(): void
    {
        $providerId = $this->seedProvider('Dr. Not Working', 'provider');
        
        // Seed inactive schedule (Saturday)
        $this->seedProviderSchedule($providerId, 'saturday', '09:00:00', '17:00:00', false);
        
        // Build day view for Saturday March 14, 2026
        $result = $this->service->build('2026-03-14', [
            'provider_ids' => [$providerId],
        ]);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('providerColumns', $result);
        $this->assertCount(1, $result['providerColumns']);
        
        $column = $result['providerColumns'][0];
        $this->assertArrayHasKey('workingHours', $column);
        $this->assertEquals('business_hours', $column['workingHours']['source']);
        $this->assertFalse($column['workingHours']['isActive']);
    }

    /**
     * Test: Provider with custom working hours (different from business hours)
     */
    public function testProviderWithCustomWorkingHours(): void
    {
        $providerId = $this->seedProvider('Dr. Early Bird', 'provider');
        
        // Seed custom schedule (Monday 07:00-15:00)
        $this->seedProviderSchedule($providerId, 'monday', '07:00:00', '15:00:00', true);
        
        // Build day view for Monday March 9, 2026
        $result = $this->service->build('2026-03-09', [
            'provider_ids' => [$providerId],
        ]);
        
        $column = $result['providerColumns'][0];
        $this->assertEquals('07:00', $column['workingHours']['startTime']);
        $this->assertEquals('15:00', $column['workingHours']['endTime']);
        $this->assertEquals('provider_schedule', $column['workingHours']['source']);
        $this->assertTrue($column['workingHours']['isActive']);
    }

    /**
     * Test: Multiple providers with different working hours
     */
    public function testMultipleProvidersWithDifferentWorkingHours(): void
    {
        $provider1Id = $this->seedProvider('Dr. Morning Person', 'provider');
        $provider2Id = $this->seedProvider('Dr. Night Owl', 'provider');
        
        // Provider 1: 07:00-15:00
        $this->seedProviderSchedule($provider1Id, 'monday', '07:00:00', '15:00:00', true);
        
        // Provider 2: 12:00-20:00
        $this->seedProviderSchedule($provider2Id, 'monday', '12:00:00', '20:00:00', true);
        
        // Build day view
        $result = $this->service->build('2026-03-09', [
            'provider_ids' => [$provider1Id, $provider2Id],
        ]);
        
        $this->assertCount(2, $result['providerColumns']);
        
        // Find each provider's column
        $columns = [];
        foreach ($result['providerColumns'] as $col) {
            $columns[$col['provider']['id']] = $col;
        }
        
        // Verify provider 1's hours
        $this->assertEquals('07:00', $columns[$provider1Id]['workingHours']['startTime']);
        $this->assertEquals('15:00', $columns[$provider1Id]['workingHours']['endTime']);
        
        // Verify provider 2's hours
        $this->assertEquals('12:00', $columns[$provider2Id]['workingHours']['startTime']);
        $this->assertEquals('20:00', $columns[$provider2Id]['workingHours']['endTime']);
    }

    // Helper methods

    protected function seedProvider(string $name, string $role): int
    {
        return $this->userModel->insert([
            'username' => strtolower(str_replace(' ', '', $name)),
            'email' => strtolower(str_replace(' ', '', $name)) . '@example.com',
            'password_hash' => password_hash('password', PASSWORD_DEFAULT),
            'name' => $name,
            'role' => $role,
            'status' => 'active',
        ]);
    }

    protected function seedCustomer(string $name): int
    {
        [$firstName, $lastName] = array_pad(explode(' ', $name, 2), 2, 'Customer');

        $table = $this->db->table('xs_customers');
        $data = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => strtolower(str_replace(' ', '', $name)) . '@customer.com',
            'phone' => '+1234567890',
        ];

        if ($this->db->fieldExists('hash', 'xs_customers')) {
            $data['hash'] = hash('sha256', uniqid('customer_', true));
        }

        $table->insert($data);

        return (int) $this->db->insertID();
    }

    protected function seedService(string $name, int $duration): int
    {
        return model('ServiceModel')->insert([
            'name' => $name,
            'duration_min' => $duration,
            'price' => 100.00,
            'active' => 1,
        ]);
    }

    protected function seedProviderSchedule(
        int $providerId,
        string $dayOfWeek,
        string $startTime,
        string $endTime,
        bool $isActive
    ): int {
        return $this->scheduleModel->insert([
            'provider_id' => $providerId,
            'day_of_week' => $dayOfWeek,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'is_active' => $isActive ? 1 : 0,
        ]);
    }

    protected function seedAppointment(array $data): int
    {
        return $this->appointmentModel->insert($data);
    }
}
