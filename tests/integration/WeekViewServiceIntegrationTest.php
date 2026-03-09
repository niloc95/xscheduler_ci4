<?php

namespace Tests\Integration;

use App\Models\AppointmentModel;
use App\Models\ProviderScheduleModel;
use App\Models\UserModel;
use App\Services\Appointment\AppointmentFormatterService;
use App\Services\Appointment\AppointmentQueryService;
use App\Services\Calendar\CalendarRangeService;
use App\Services\Calendar\EventLayoutService;
use App\Services\Calendar\TimeGridService;
use App\Services\Calendar\WeekViewService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Integration tests for WeekViewService
 * Tests multi-provider week scenarios, working hours, and overlap handling
 * 
 * @internal
 */
final class WeekViewServiceIntegrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = 'App';
    protected WeekViewService $service;
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
        $this->service = new WeekViewService(
            new CalendarRangeService(),
            new AppointmentQueryService(),
            new AppointmentFormatterService(),
            new TimeGridService(),
            new EventLayoutService()
        );
    }

    /**
     * Test: Week view with multiple providers and overlapping appointments
     */
    public function testWeekViewWithMultipleProvidersAndOverlaps(): void
    {
        // Seed two providers
        $provider1Id = $this->seedProvider('Dr. Sarah Chen', 'provider');
        $provider2Id = $this->seedProvider('Dr. James Okafor', 'provider');
        
        // Seed customer and service
        $customerId = $this->seedCustomer('Alice Johnson');
        $serviceId = $this->seedService('Consultation', 30);
        
        // Seed overlapping appointments on Monday (2026-03-09)
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
        
        // Seed non-overlapping appointment for provider 2 on Tuesday
        $apt3 = $this->seedAppointment([
            'provider_id' => $provider2Id,
            'customer_id' => $customerId,
            'service_id' => $serviceId,
            'start_at' => '2026-03-10 10:00:00',
            'end_at' => '2026-03-10 10:30:00',
            'status' => 'confirmed',
        ]);
        
        // Build week view for week of March 9, 2026 (Monday)
        $result = $this->service->build('2026-03-09', [
            'provider_ids' => [$provider1Id, $provider2Id],
        ]);
        
        // Assertions
        $this->assertIsArray($result);
        $this->assertArrayHasKey('days', $result);
        $this->assertArrayHasKey('appointments', $result);
        $this->assertCount(7, $result['days']); // Full week
        
        // Verify appointments have overlap metadata
        $appointments = $result['appointments'];
        $this->assertCount(3, $appointments);
        
        // Find the two overlapping appointments (Monday, provider 1)
        $mondayProvider1Apts = array_filter($appointments, function ($apt) use ($provider1Id) {
            return $apt['providerId'] === $provider1Id 
                && str_starts_with($apt['start_at'], '2026-03-09');
        });
        
        $this->assertCount(2, $mondayProvider1Apts);
        
        // Verify overlap metadata exists
        foreach ($mondayProvider1Apts as $apt) {
            $this->assertArrayHasKey('_column', $apt);
            $this->assertArrayHasKey('_columns_total', $apt);
            $this->assertEquals(2, $apt['_columns_total'], 'Overlapping appointments should require 2 columns');
        }
    }

    /**
     * Test: Week view filters appointments by date range correctly
     */
    public function testWeekViewFiltersAppointmentsByDateRange(): void
    {
        $providerId = $this->seedProvider('Dr. Week Test', 'provider');
        $customerId = $this->seedCustomer('Bob Wilson');
        $serviceId = $this->seedService('Follow-up', 20);
        
        // Seed appointments in different weeks
        // Week of March 9-15, 2026
        $inWeekApt = $this->seedAppointment([
            'provider_id' => $providerId,
            'customer_id' => $customerId,
            'service_id' => $serviceId,
            'start_at' => '2026-03-10 14:00:00', // Tuesday
            'end_at' => '2026-03-10 14:20:00',
            'status' => 'confirmed',
        ]);
        
        // Previous week (should NOT appear)
        $prevWeekApt = $this->seedAppointment([
            'provider_id' => $providerId,
            'customer_id' => $customerId,
            'service_id' => $serviceId,
            'start_at' => '2026-03-02 14:00:00', // Previous Monday
            'end_at' => '2026-03-02 14:20:00',
            'status' => 'confirmed',
        ]);
        
        // Next week (should NOT appear)
        $nextWeekApt = $this->seedAppointment([
            'provider_id' => $providerId,
            'customer_id' => $customerId,
            'service_id' => $serviceId,
            'start_at' => '2026-03-16 14:00:00', // Next Monday
            'end_at' => '2026-03-16 14:20:00',
            'status' => 'confirmed',
        ]);
        
        // Build week view for week of March 9, 2026
        $result = $this->service->build('2026-03-09', [
            'provider_ids' => [$providerId],
        ]);
        
        $appointments = $result['appointments'];
        
        // Should only contain the one appointment in the target week
        $this->assertCount(1, $appointments);
        $this->assertEquals($inWeekApt, $appointments[0]['id']);
    }

    /**
     * Test: Week view handles appointments across multiple days
     */
    public function testWeekViewHandlesAppointmentsAcrossMultipleDays(): void
    {
        $providerId = $this->seedProvider('Dr. Multi Day', 'provider');
        $customerId = $this->seedCustomer('Charlie Brown');
        $serviceId = $this->seedService('Assessment', 45);
        
        // Seed appointments on different days of the week
        $days = [
            '2026-03-09' => 'Monday',
            '2026-03-10' => 'Tuesday',
            '2026-03-11' => 'Wednesday',
            '2026-03-12' => 'Thursday',
            '2026-03-13' => 'Friday',
        ];
        
        $appointmentIds = [];
        foreach (array_keys($days) as $date) {
            $appointmentIds[] = $this->seedAppointment([
                'provider_id' => $providerId,
                'customer_id' => $customerId,
                'service_id' => $serviceId,
                'start_at' => "$date 10:00:00",
                'end_at' => "$date 10:45:00",
                'status' => 'confirmed',
            ]);
        }
        
        // Build week view
        $result = $this->service->build('2026-03-09', [
            'provider_ids' => [$providerId],
        ]);
        
        // Should have 5 appointments (Mon-Fri)
        $this->assertCount(5, $result['appointments']);
        
        // Verify each appointment is on a different day
        $appointmentDates = array_map(function ($apt) {
            return substr($apt['start_at'], 0, 10);
        }, $result['appointments']);
        
        $this->assertCount(5, array_unique($appointmentDates));
    }

    /**
     * Test: Week view with same-time appointments for different providers
     */
    public function testWeekViewWithSameTimeAppointmentsForDifferentProviders(): void
    {
        $provider1Id = $this->seedProvider('Dr. Provider A', 'provider');
        $provider2Id = $this->seedProvider('Dr. Provider B', 'provider');
        $customerId = $this->seedCustomer('David Lee');
        $serviceId = $this->seedService('Appointment', 30);
        
        // Both providers have appointment at same time (different customers though)
        $customer2Id = $this->seedCustomer('Eve Martinez');
        
        $apt1 = $this->seedAppointment([
            'provider_id' => $provider1Id,
            'customer_id' => $customerId,
            'service_id' => $serviceId,
            'start_at' => '2026-03-09 11:00:00',
            'end_at' => '2026-03-09 11:30:00',
            'status' => 'confirmed',
        ]);
        
        $apt2 = $this->seedAppointment([
            'provider_id' => $provider2Id,
            'customer_id' => $customer2Id,
            'service_id' => $serviceId,
            'start_at' => '2026-03-09 11:00:00',
            'end_at' => '2026-03-09 11:30:00',
            'status' => 'confirmed',
        ]);
        
        // Build week view
        $result = $this->service->build('2026-03-09', [
            'provider_ids' => [$provider1Id, $provider2Id],
        ]);
        
        // Should have 2 appointments
        $this->assertCount(2, $result['appointments']);
        
        // Each should be in its own column (no overlap between different providers)
        foreach ($result['appointments'] as $apt) {
            $this->assertEquals(1, $apt['_columns_total'], 
                'Same-time appointments for different providers should not overlap');
            $this->assertEquals(0, $apt['_column']);
        }
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
        return model('CustomerModel')->insert([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '', $name)) . '@customer.com',
            'phone' => '+1234567890',
        ]);
    }

    protected function seedService(string $name, int $duration): int
    {
        return model('ServiceModel')->insert([
            'name' => $name,
            'duration' => $duration,
            'price' => 100.00,
            'is_active' => 1,
        ]);
    }

    protected function seedAppointment(array $data): int
    {
        return $this->appointmentModel->insert($data);
    }
}
