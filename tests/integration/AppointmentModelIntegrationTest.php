<?php

namespace Tests\Integration;

use App\Models\AppointmentModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Integration tests for AppointmentModel
 * Tests database queries with JOINs and complex relationships
 * 
 * @internal
 */
final class AppointmentModelIntegrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = 'App';
    protected AppointmentModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new AppointmentModel();
    }

    /**
     * Test getWithRelations returns appointment with all relations
     */
    public function testGetWithRelationsReturnsAppointmentWithRelations(): void
    {
        // Setup: Create test data
        $customerId = $this->createTestCustomer('John', 'Doe', 'john@example.com', '555-0100');
        $providerId = $this->createTestProvider('Dr. Smith', 'provider@example.com', '#3B82F6');
        $serviceId = $this->createTestService('General Consultation', 60, 100.00);
        $appointmentId = $this->createTestAppointment($customerId, $providerId, $serviceId);
        
        // Execute
        $result = $this->model->getWithRelations($appointmentId);
        
        // Assert
        $this->assertNotNull($result, 'Should return appointment data');
        $this->assertEquals($appointmentId, $result['id']);
        
        // Check customer data
        $this->assertEquals('John Doe', $result['customer_name']);
        $this->assertEquals('john@example.com', $result['customer_email']);
        $this->assertEquals('555-0100', $result['customer_phone']);
        
        // Check service data
        $this->assertEquals('General Consultation', $result['service_name']);
        $this->assertEquals(60, $result['service_duration']);
        $this->assertEquals(100.00, $result['service_price']);
        
        // Check provider data
        $this->assertEquals('Dr. Smith', $result['provider_name']);
        $this->assertEquals('#3B82F6', $result['provider_color']);
    }

    /**
     * Test getWithRelations returns null for non-existent appointment
     */
    public function testGetWithRelationsReturnsNullForNonExistentAppointment(): void
    {
        // Execute with non-existent ID
        $result = $this->model->getWithRelations(99999);
        
        // Assert
        $this->assertNull($result, 'Should return null for non-existent appointment');
    }

    /**
     * Test getWithRelations handles customer with only first name
     */
    public function testGetWithRelationsHandlesCustomerWithOnlyFirstName(): void
    {
        // Setup: Customer with no last name
        $customerId = $this->createTestCustomer('Jane', null, 'jane@example.com', '555-0101');
        $providerId = $this->createTestProvider('Dr. Jones', 'jones@example.com', '#10B981');
        $serviceId = $this->createTestService('Follow-up', 30, 75.00);
        $appointmentId = $this->createTestAppointment($customerId, $providerId, $serviceId);
        
        // Execute
        $result = $this->model->getWithRelations($appointmentId);
        
        // Assert
        $this->assertNotNull($result);
        $this->assertEquals('Jane', trim($result['customer_name']), 'Should handle null last name gracefully');
    }

    /**
     * Test getManyWithRelations returns multiple appointments
     */
    public function testGetManyWithRelationsReturnsMultipleAppointments(): void
    {
        // Setup: Create multiple test appointments
        $customerId1 = $this->createTestCustomer('Alice', 'Brown', 'alice@example.com', '555-0201');
        $customerId2 = $this->createTestCustomer('Bob', 'Green', 'bob@example.com', '555-0202');
        $providerId = $this->createTestProvider('Dr. Wilson', 'wilson@example.com', '#F59E0B');
        $serviceId = $this->createTestService('Check-up', 45, 85.00);
        
        $appt1 = $this->createTestAppointment($customerId1, $providerId, $serviceId, '2026-02-10 09:00:00', '2026-02-10 10:00:00');
        $appt2 = $this->createTestAppointment($customerId2, $providerId, $serviceId, '2026-02-10 14:00:00', '2026-02-10 15:00:00');
        
        // Execute without filters
        $result = $this->model->getManyWithRelations();
        
        // Assert
        $this->assertIsArray($result);
        $this->assertGreaterThanOrEqual(2, count($result), 'Should return at least 2 appointments');
        
        // Verify relations are included
        foreach ($result as $appointment) {
            $this->assertArrayHasKey('customer_name', $appointment);
            $this->assertArrayHasKey('service_name', $appointment);
            $this->assertArrayHasKey('provider_name', $appointment);
        }
    }

    /**
     * Test getManyWithRelations with provider filter
     */
    public function testGetManyWithRelationsWithProviderFilter(): void
    {
        // Setup: Create appointments for different providers
        $customerId = $this->createTestCustomer('Charlie', 'Davis', 'charlie@example.com', '555-0301');
        $provider1Id = $this->createTestProvider('Dr. Lee', 'lee@example.com', '#8B5CF6');
        $provider2Id = $this->createTestProvider('Dr. Martinez', 'martinez@example.com', '#EC4899');
        $serviceId = $this->createTestService('Physical', 60, 120.00);
        
        $appt1 = $this->createTestAppointment($customerId, $provider1Id, $serviceId);
        $appt2 = $this->createTestAppointment($customerId, $provider2Id, $serviceId);
        
        // Execute with provider filter
        $result = $this->model->getManyWithRelations(['provider_id' => $provider1Id]);
        
        // Assert
        $this->assertIsArray($result);
        $this->assertGreaterThan(0, count($result));
        
        // Verify all results match provider
        foreach ($result as $appointment) {
            $this->assertEquals($provider1Id, $appointment['provider_id']);
            $this->assertEquals('Dr. Lee', $appointment['provider_name']);
        }
    }

    /**
     * Test getManyWithRelations with date range filter
     */
    public function testGetManyWithRelationsWithDateRangeFilter(): void
    {
        // Setup: Create appointments on different dates
        $customerId = $this->createTestCustomer('Diana', 'Evans', 'diana@example.com', '555-0401');
        $providerId = $this->createTestProvider('Dr. Taylor', 'taylor@example.com', '#14B8A6');
        $serviceId = $this->createTestService('Therapy', 50, 95.00);
        
        $appt1 = $this->createTestAppointment($customerId, $providerId, $serviceId, '2026-02-05 10:00:00', '2026-02-05 11:00:00');
        $appt2 = $this->createTestAppointment($customerId, $providerId, $serviceId, '2026-02-15 10:00:00', '2026-02-15 11:00:00');
        $appt3 = $this->createTestAppointment($customerId, $providerId, $serviceId, '2026-02-25 10:00:00', '2026-02-25 11:00:00');
        
        // Execute with date range filter (Feb 10-20)
        $result = $this->model->getManyWithRelations([
            'start' => '2026-02-10 00:00:00',
            'end' => '2026-02-20 23:59:59'
        ]);
        
        // Assert
        $this->assertIsArray($result);
        
        // Should only include appointment on Feb 15
        foreach ($result as $appointment) {
            $startTime = strtotime($appointment['start_time']);
            $this->assertGreaterThanOrEqual(strtotime('2026-02-10'), $startTime);
            $this->assertLessThanOrEqual(strtotime('2026-02-20'), $startTime);
        }
    }

    /**
     * Test getManyWithRelations with service filter
     */
    public function testGetManyWithRelationsWithServiceFilter(): void
    {
        // Setup: Create appointments for different services
        $customerId = $this->createTestCustomer('Eve', 'Foster', 'eve@example.com', '555-0501');
        $providerId = $this->createTestProvider('Dr. Anderson', 'anderson@example.com', '#6366F1');
        $service1Id = $this->createTestService('Service A', 30, 50.00);
        $service2Id = $this->createTestService('Service B', 60, 100.00);
        
        $appt1 = $this->createTestAppointment($customerId, $providerId, $service1Id);
        $appt2 = $this->createTestAppointment($customerId, $providerId, $service2Id);
        
        // Execute with service filter
        $result = $this->model->getManyWithRelations(['service_id' => $service1Id]);
        
        // Assert
        $this->assertIsArray($result);
        foreach ($result as $appointment) {
            $this->assertEquals($service1Id, $appointment['service_id']);
            $this->assertEquals('Service A', $appointment['service_name']);
        }
    }

    /**
     * Test getManyWithRelations with status filter
     */
    public function testGetManyWithRelationsWithStatusFilter(): void
    {
        // Setup: Create appointments with different statuses
        $customerId = $this->createTestCustomer('Frank', 'Garcia', 'frank@example.com', '555-0601');
        $providerId = $this->createTestProvider('Dr. Brown', 'brown@example.com', '#EF4444');
        $serviceId = $this->createTestService('Consultation', 45, 80.00);
        
        $appt1 = $this->createTestAppointment($customerId, $providerId, $serviceId, '2026-02-20 10:00:00', '2026-02-20 11:00:00', 'pending');
        $appt2 = $this->createTestAppointment($customerId, $providerId, $serviceId, '2026-02-21 10:00:00', '2026-02-21 11:00:00', 'confirmed');
        
        // Execute with status filter
        $result = $this->model->getManyWithRelations(['status' => 'confirmed']);
        
        // Assert
        $this->assertIsArray($result);
        foreach ($result as $appointment) {
            $this->assertEquals('confirmed', $appointment['status']);
        }
    }

    /**
     * Test getManyWithRelations with limit
     */
    public function testGetManyWithRelationsWithLimit(): void
    {
        // Setup: Create several appointments
        $customerId = $this->createTestCustomer('Grace', 'Harris', 'grace@example.com', '555-0701');
        $providerId = $this->createTestProvider('Dr. White', 'white@example.com', '#10B981');
        $serviceId = $this->createTestService('Appointment', 30, 60.00);
        
        for ($i = 0; $i < 5; $i++) {
            $this->createTestAppointment($customerId, $providerId, $serviceId);
        }
        
        // Execute with limit
        $result = $this->model->getManyWithRelations([], 2);
        
        // Assert
        $this->assertIsArray($result);
        $this->assertCount(2, $result, 'Should respect limit parameter');
    }

    /**
     * Test getManyWithRelations returns empty array when no appointments match
     */
    public function testGetManyWithRelationsReturnsEmptyArrayWhenNoMatches(): void
    {
        // Execute with filter that won't match anything
        $result = $this->model->getManyWithRelations(['provider_id' => 99999]);
        
        // Assert
        $this->assertIsArray($result);
        $this->assertEmpty($result, 'Should return empty array when no appointments match');
    }

    // ========== Helper Methods ==========

    private function createTestCustomer(string $firstName, ?string $lastName, string $email, string $phone): int
    {
        $db = \Config\Database::connect();
        $db->table('xs_customers')->insert([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        return $db->insertID();
    }

    private function createTestProvider(string $name, string $email, string $color): int
    {
        $db = \Config\Database::connect();
        $db->table('xs_users')->insert([
            'name' => $name,
            'email' => $email,
            'password' => password_hash('password', PASSWORD_DEFAULT),
            'role' => 'provider',
            'color' => $color,
            'is_active' => true,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        return $db->insertID();
    }

    private function createTestService(string $name, int $duration, float $price): int
    {
        $db = \Config\Database::connect();
        $db->table('xs_services')->insert([
            'name' => $name,
            'duration_min' => $duration,
            'price' => $price,
            'active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        return $db->insertID();
    }

    private function createTestAppointment(
        int $customerId, 
        int $providerId, 
        int $serviceId,
        string $startTime = '2026-02-15 10:00:00',
        string $endTime = '2026-02-15 11:00:00',
        string $status = 'pending'
    ): int {
        $db = \Config\Database::connect();
        $db->table('xs_appointments')->insert([
            'customer_id' => $customerId,
            'user_id' => $providerId,
            'provider_id' => $providerId,
            'service_id' => $serviceId,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'status' => $status,
            'hash' => hash('sha256', uniqid('test_', true)),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        return $db->insertID();
    }

    protected function tearDown(): void
    {
        // Clean up test data
        $db = \Config\Database::connect();
        $db->table('xs_appointments')->truncate();
        $db->table('xs_customers')->truncate();
        $db->table('xs_services')->truncate();
        // Note: Don't truncate xs_users as it may have system users
        
        parent::tearDown();
    }
}
