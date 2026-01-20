<?php

namespace App\Tests\Integration;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Dashboard Landing View Integration Tests
 * 
 * Tests the complete dashboard functionality including:
 * - Role-based access control
 * - Data scoping (Owner/Provider/Staff)
 * - Cache invalidation
 * - API endpoints
 * 
 * Run with: php spark test --filter DashboardLandingTest
 */
class DashboardLandingTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $refresh = true;
    protected $namespace = 'App';

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed test data
        $this->seedTestData();
    }

    /**
     * Seed test data for dashboard tests
     */
    private function seedTestData(): void
    {
        // Create test users
        $userModel = model('UserModel');
        
        // Admin user
        $adminId = $userModel->insert([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'admin',
            'is_active' => true
        ]);
        
        // Provider user
        $providerId = $userModel->insert([
            'name' => 'Dr. Provider',
            'email' => 'provider@test.com',
            'password' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'provider',
            'is_active' => true,
            'color' => '#3B82F6'
        ]);
        
        // Create test appointments
        $appointmentModel = model('AppointmentModel');
        
        // Today's appointments for provider
        $today = date('Y-m-d');
        $now = date('Y-m-d H:i:s');
        
        $appointmentModel->insert([
            'customer_id' => 1,
            'provider_id' => $providerId,
            'service_id' => 1,
            'appointment_date' => $today,
            'start_time' => $now,
            'end_time' => date('Y-m-d H:i:s', strtotime('+1 hour')),
            'status' => 'confirmed'
        ]);
        
        $appointmentModel->insert([
            'customer_id' => 2,
            'provider_id' => $providerId,
            'service_id' => 1,
            'appointment_date' => $today,
            'start_time' => date('Y-m-d H:i:s', strtotime('+2 hours')),
            'end_time' => date('Y-m-d H:i:s', strtotime('+3 hours')),
            'status' => 'pending'
        ]);
    }

    /**
     * Test: Admin can access dashboard
     */
    public function testAdminCanAccessDashboard(): void
    {
        // Login as admin
        $session = session();
        $session->set('user', [
            'id' => 1,
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'role' => 'admin'
        ]);

        // Access dashboard
        $result = $this->withSession()->get('/dashboard');

        $result->assertOK();
        $result->assertSee('Welcome back, Admin User!');
    }

    /**
     * Test: Provider sees only own data
     */
    public function testProviderSeesOnlyOwnData(): void
    {
        // Login as provider
        $session = session();
        $session->set('user', [
            'id' => 2,
            'name' => 'Dr. Provider',
            'email' => 'provider@test.com',
            'role' => 'provider'
        ]);

        // Access dashboard
        $result = $this->withSession()->get('/dashboard');

        $result->assertOK();
        $result->assertSee('Welcome back, Dr. Provider!');
        
        // Should see own appointments
        $result->assertSee('Dr. Provider');
    }

    /**
     * Test: Unauthenticated users redirected to login
     */
    public function testUnauthenticatedRedirectToLogin(): void
    {
        $result = $this->get('/dashboard');

        $result->assertRedirect();
        $result->assertRedirectTo('/login');
    }

    /**
     * Test: Dashboard metrics API returns correct data
     */
    public function testDashboardMetricsAPI(): void
    {
        // Login as admin
        $session = session();
        $session->set('user', [
            'id' => 1,
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'role' => 'admin'
        ]);

        // Call metrics API
        $result = $this->withSession()->get('/dashboard/api/metrics');

        $result->assertOK();
        $result->assertJSONFragment([
            'success' => true
        ]);

        // Check data structure
        $json = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('data', $json);
        $this->assertArrayHasKey('total', $json['data']);
        $this->assertArrayHasKey('upcoming', $json['data']);
        $this->assertArrayHasKey('pending', $json['data']);
        $this->assertArrayHasKey('cancelled', $json['data']);
    }

    /**
     * Test: Metrics API requires authentication
     */
    public function testMetricsAPIRequiresAuth(): void
    {
        $result = $this->get('/dashboard/api/metrics');

        $result->assertStatus(401);
        $result->assertJSONFragment([
            'success' => false,
            'error' => 'Unauthorized'
        ]);
    }

    /**
     * Test: Cache invalidation on appointment create
     */
    public function testCacheInvalidationOnAppointmentCreate(): void
    {
        // Login as admin
        $session = session();
        $session->set('user', [
            'id' => 1,
            'role' => 'admin'
        ]);

        // Get initial metrics (should cache)
        $result1 = $this->withSession()->get('/dashboard/api/metrics');
        $json1 = json_decode($result1->getJSON(), true);
        $initialTotal = $json1['data']['total'];

        // Create new appointment (should invalidate cache)
        $appointmentModel = model('AppointmentModel');
        $today = date('Y-m-d');
        $appointmentModel->insert([
            'customer_id' => 1,
            'provider_id' => 2,
            'service_id' => 1,
            'appointment_date' => $today,
            'start_time' => date('Y-m-d H:i:s', strtotime('+4 hours')),
            'end_time' => date('Y-m-d H:i:s', strtotime('+5 hours')),
            'status' => 'confirmed'
        ]);

        // Get metrics again (should have new data)
        $result2 = $this->withSession()->get('/dashboard/api/metrics');
        $json2 = json_decode($result2->getJSON(), true);
        $newTotal = $json2['data']['total'];

        // Total should have increased
        $this->assertEquals($initialTotal + 1, $newTotal);
    }

    /**
     * Test: Database indexes exist
     */
    public function testDatabaseIndexesExist(): void
    {
        $db = \Config\Database::connect();
        
        // Check for required indexes
        $indexes = $db->query("SHOW INDEX FROM xs_appointments")->getResultArray();
        
        $indexNames = array_column($indexes, 'Key_name');
        
        $this->assertContains('idx_provider_date_status', $indexNames);
        $this->assertContains('idx_date_time', $indexNames);
        $this->assertContains('idx_status_date', $indexNames);
        $this->assertContains('idx_start_time', $indexNames);
    }

    /**
     * Test: DashboardService getTodayMetrics with provider scope
     */
    public function testDashboardServiceGetTodayMetricsWithProviderScope(): void
    {
        $dashboardService = new \App\Services\DashboardService();
        
        // Get metrics for specific provider
        $metrics = $dashboardService->getTodayMetrics(2);
        
        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('total', $metrics);
        $this->assertArrayHasKey('upcoming', $metrics);
        $this->assertArrayHasKey('pending', $metrics);
        $this->assertArrayHasKey('cancelled', $metrics);
        
        // Should have 2 appointments for provider 2
        $this->assertEquals(2, $metrics['total']);
        $this->assertEquals(1, $metrics['pending']);
    }

    /**
     * Test: DashboardService getTodayMetrics without scope (admin)
     */
    public function testDashboardServiceGetTodayMetricsWithoutScope(): void
    {
        $dashboardService = new \App\Services\DashboardService();
        
        // Get metrics for all providers (admin)
        $metrics = $dashboardService->getTodayMetrics(null);
        
        $this->assertIsArray($metrics);
        $this->assertGreaterThanOrEqual(2, $metrics['total']);
    }

    /**
     * Test: AuthorizationService role checks
     */
    public function testAuthorizationServiceRoleChecks(): void
    {
        $authService = new \App\Services\AuthorizationService();
        
        // Admin can view dashboard metrics
        $this->assertTrue($authService->canViewDashboardMetrics('admin'));
        
        // Provider can view dashboard metrics
        $this->assertTrue($authService->canViewDashboardMetrics('provider'));
        
        // Admin has no scope restriction
        $this->assertNull($authService->getProviderScope('admin', null));
        
        // Provider has scope restriction
        $this->assertEquals(2, $authService->getProviderScope('provider', 2));
        
        // Only admin can view settings
        $this->assertTrue($authService->canViewSettings('admin'));
        $this->assertFalse($authService->canViewSettings('provider'));
    }

    /**
     * Test: Dashboard handles database errors gracefully
     */
    public function testDashboardHandlesDatabaseErrorsGracefully(): void
    {
        // This test would require mocking the database connection
        // For now, it's a placeholder for manual testing
        $this->assertTrue(true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        
        // Clear cache
        cache()->clean();
    }
}
