<?php

namespace App\Tests\Integration;

use App\Models\SettingModel;
use App\Services\AuthorizationService;
use App\Services\DashboardApiService;
use App\Services\DashboardPageService;
use App\Services\DashboardService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Dashboard service integration tests.
 * 
 * Run with: php spark test --filter DashboardLandingTest
 */
class DashboardLandingTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace = 'App';

    private int $adminId;
    private int $providerId;
    private int $customerId;
    private int $serviceId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureSetupFlag();
        
        // Seed test data
        $this->seedTestData();
    }

    private function ensureSetupFlag(): void
    {
        $flagPath = WRITEPATH . 'setup_complete.flag';

        if (!is_file($flagPath)) {
            file_put_contents($flagPath, 'test');
        }
    }

    /**
     * Seed test data for dashboard tests
     */
    private function seedTestData(): void
    {
        $db = \Config\Database::connect();
        $now = date('Y-m-d H:i:s');
        $appointmentStart = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $appointmentEnd = date('Y-m-d H:i:s', strtotime('+2 hours'));
        $pendingStart = date('Y-m-d H:i:s', strtotime('+3 hours'));
        $pendingEnd = date('Y-m-d H:i:s', strtotime('+4 hours'));
        
        $db->table('users')->insert([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'admin',
            'status' => 'active',
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->adminId = (int) $db->insertID();
        
        $db->table('users')->insert([
            'name' => 'Dr. Provider',
            'email' => 'provider@test.com',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'provider',
            'status' => 'active',
            'is_active' => 1,
            'color' => '#3B82F6',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->providerId = (int) $db->insertID();

        $db->table('customers')->insert([
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'email' => 'customer@test.com',
            'phone' => '+15555550100',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->customerId = (int) $db->insertID();

        $db->table('services')->insert([
            'name' => 'Consultation',
            'description' => 'Dashboard test service',
            'category_id' => null,
            'duration_min' => 60,
            'price' => 100.00,
            'active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->serviceId = (int) $db->insertID();
        
        $db->table('appointments')->insert([
            'customer_id' => $this->customerId,
            'provider_id' => $this->providerId,
            'service_id' => $this->serviceId,
            'hash' => hash('sha256', 'dashboard-test-confirmed-' . $now),
            'start_at' => $appointmentStart,
            'end_at' => $appointmentEnd,
            'status' => 'confirmed',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        
        $db->table('appointments')->insert([
            'customer_id' => $this->customerId,
            'provider_id' => $this->providerId,
            'service_id' => $this->serviceId,
            'hash' => hash('sha256', 'dashboard-test-pending-' . $now),
            'start_at' => $pendingStart,
            'end_at' => $pendingEnd,
            'status' => 'pending',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $settings = [
            'localization.timezone' => 'UTC',
            'localization.time_format' => '24h',
            'localization.first_day' => 'monday',
            'localization.currency' => 'USD',
        ];

        foreach ($settings as $key => $value) {
            $existing = $db->table('settings')->where('setting_key', $key)->get()->getRowArray();

            if ($existing !== null) {
                $db->table('settings')
                    ->where('setting_key', $key)
                    ->update([
                        'setting_value' => $value,
                        'setting_type' => 'string',
                        'updated_at' => $now,
                    ]);

                continue;
            }

            $db->table('settings')->insert([
                'setting_key' => $key,
                'setting_value' => $value,
                'setting_type' => 'string',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // These rows were written via the query builder, bypassing
        // SettingModel::upsert()'s cache invalidation. The cache is process-static,
        // so without this an earlier test in the same run pins the stale value.
        SettingModel::clearRequestCache();
    }

    private function authSession(int $userId, string $name, string $email, string $role): array
    {
        return [
            'isLoggedIn' => true,
            'user_id' => $userId,
            'user' => [
                'id' => $userId,
                'name' => $name,
                'email' => $email,
                'role' => $role,
            ],
        ];
    }

    public function testDashboardPageServiceBuildsLandingDataForAdmin(): void
    {
        session()->set($this->authSession($this->adminId, 'Admin User', 'admin@test.com', 'admin'));

        $service = new DashboardPageService();
        $sessionData = $service->resolveLandingSession();

        $this->assertIsArray($sessionData);

        $viewData = $service->buildLandingViewData($sessionData);

        $this->assertSame('Admin User', $viewData['user']['name']);
        $this->assertArrayHasKey('metrics', $viewData);
        $this->assertArrayHasKey('schedule', $viewData);
        $this->assertArrayHasKey('recent_activities', $viewData);
        $this->assertSame(2, (int) ($viewData['metrics']['total'] ?? 0));
    }

    /**
     * The seeded currency is USD. `format_currency()` previously read the setting
     * under a mis-cased key, so it silently fell back to ZAR and the dashboard
     * revenue tile rendered "R…" no matter what the business had configured.
     */
    public function testDashboardRevenueTileUsesTheConfiguredCurrency(): void
    {
        session()->set($this->authSession($this->adminId, 'Admin User', 'admin@test.com', 'admin'));

        // revenue_formatted is produced by the metrics endpoint, which is what the
        // landing page's live poll re-renders the Revenue tile from.
        $response = (new DashboardPageService())->getMetricsEndpointResponse();

        $this->assertSame(200, $response['statusCode']);

        $formatted = (string) ($response['payload']['data']['revenue_formatted'] ?? '');

        $this->assertNotSame('', $formatted, 'Dashboard metrics must expose a pre-formatted revenue value.');
        $this->assertStringStartsWith('$', $formatted);
        $this->assertStringNotContainsString('R', $formatted);
    }

    public function testCurrencyHelpersResolveTheConfiguredCurrencyEndToEnd(): void
    {
        helper('currency');

        // Reads through SettingModel against the real seeded settings row.
        $this->assertSame('USD', get_app_currency());
        $this->assertSame('$', get_app_currency_symbol());
        $this->assertSame('$1,500.00', format_currency(1500));
    }

    public function testDashboardPageServiceMetricsResponseForProvider(): void
    {
        session()->set($this->authSession($this->providerId, 'Dr. Provider', 'provider@test.com', 'provider'));

        $service = new DashboardPageService();
        $response = $service->getMetricsEndpointResponse();

        $this->assertSame(200, $response['statusCode']);
        $this->assertTrue((bool) ($response['payload']['success'] ?? false));
        $this->assertSame(2, (int) ($response['payload']['data']['total'] ?? 0));
        $this->assertSame(1, (int) ($response['payload']['data']['pending'] ?? 0));
    }

    public function testDashboardPageServiceMetricsResponseRequiresAuthenticatedUser(): void
    {
        session()->remove(['isLoggedIn', 'user_id', 'user']);

        $service = new DashboardPageService();
        $response = $service->getMetricsEndpointResponse();

        $this->assertSame(401, $response['statusCode']);
        $this->assertFalse((bool) ($response['payload']['success'] ?? true));
        $this->assertSame('Unauthorized', $response['payload']['error']);
    }

    public function testDashboardApiServiceReturnsChartsPayload(): void
    {
        $service = new DashboardApiService();
        $payload = $service->getChartsPayload('week');

        $this->assertSame('week', $payload['period']);
        $this->assertArrayHasKey('appointmentGrowth', $payload);
        $this->assertArrayHasKey('servicesByProvider', $payload);
        $this->assertArrayHasKey('statusDistribution', $payload);
    }

    public function testDashboardApiServiceNormalizesUnknownPeriod(): void
    {
        $service = new DashboardApiService();

        $this->assertSame('month', $service->normalizePeriod('invalid-period'));
    }

    public function testDashboardApiServiceReturnsStatusPayload(): void
    {
        $service = new DashboardApiService();
        $payload = $service->getStatusPayload();

        $this->assertTrue((bool) ($payload['database_connected'] ?? false));
        $this->assertArrayHasKey('tables', $payload);
        $this->assertArrayHasKey('counts', $payload);
    }

    /**
     * Test: DashboardService getTodayMetrics with provider scope
     */
    public function testDashboardServiceGetTodayMetricsWithProviderScope(): void
    {
        $dashboardService = new DashboardService();
        
        $metrics = $dashboardService->getTodayMetrics($this->providerId);
        
        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('total', $metrics);
        $this->assertArrayHasKey('upcoming', $metrics);
        $this->assertArrayHasKey('pending', $metrics);
        $this->assertArrayHasKey('cancelled', $metrics);
        
        $this->assertEquals(2, $metrics['total']);
        $this->assertEquals(1, $metrics['pending']);
    }

    /**
     * Test: DashboardService getTodayMetrics without scope (admin)
     */
    public function testDashboardServiceGetTodayMetricsWithoutScope(): void
    {
        $dashboardService = new DashboardService();
        
        // Get metrics for all providers (admin)
        $metrics = $dashboardService->getTodayMetrics(null);
        
        $this->assertIsArray($metrics);
        $this->assertGreaterThanOrEqual(2, $metrics['total']);
    }

    public function testDashboardServiceContextIncludesAppointmentScopeFlags(): void
    {
        $dashboardService = new DashboardService();

        $context = $dashboardService->getDashboardContext($this->providerId, 'provider', $this->providerId);

        $this->assertSame('provider', $context['role']);
        $this->assertSame($this->providerId, $context['provider_id']);
        $this->assertSame('provider', $context['user_role']);
        $this->assertArrayHasKey('customer_id', $context);
        $this->assertNull($context['customer_id']);
    }

    /**
     * Test: AuthorizationService role checks
     */
    public function testAuthorizationServiceRoleChecks(): void
    {
        $authService = new AuthorizationService();
        
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
     * Test: Staff user sees only assigned providers
     */
    public function testStaffProviderScopeReturnsAssignedProviders(): void
    {
        $db = \Config\Database::connect();
        $now = date('Y-m-d H:i:s');

        // Create second provider
        $db->table('users')->insert([
            'name' => 'Dr. Second Provider',
            'email' => 'provider2@test.com',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'provider',
            'status' => 'active',
            'is_active' => 1,
            'color' => '#10B981',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $secondProviderId = (int) $db->insertID();

        // Create staff user
        $db->table('users')->insert([
            'name' => 'Staff Member',
            'email' => 'staff@test.com',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'staff',
            'status' => 'active',
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $staffId = (int) $db->insertID();

        // Assign staff to first provider only
        $db->table('provider_staff_assignments')->insert([
            'provider_id' => $this->providerId,
            'staff_id' => $staffId,
            'status' => 'active',
            'assigned_at' => $now,
        ]);

        $authService = new AuthorizationService();
        
        $staffUser = $db->table('users')->where('id', $staffId)->get()->getRowArray();
        
        // Staff scope should return array of assigned provider IDs
        $scope = $authService->getProviderScope('staff', null, $staffUser);
        
        $this->assertIsArray($scope);
        $this->assertContains($this->providerId, $scope);
        $this->assertNotContains($secondProviderId, $scope);
    }

    /**
     * Test: Staff with no assignments gets empty array
     */
    public function testStaffNoAssignmentsReturnsEmptyArray(): void
    {
        $db = \Config\Database::connect();
        $now = date('Y-m-d H:i:s');

        // Create unassigned staff user
        $db->table('users')->insert([
            'name' => 'Unassigned Staff',
            'email' => 'unassigned@test.com',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'staff',
            'status' => 'active',
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $staffId = (int) $db->insertID();

        $authService = new AuthorizationService();
        
        $staffUser = $db->table('users')->where('id', $staffId)->get()->getRowArray();
        
        // Staff with no assignments should get empty array
        $scope = $authService->getProviderScope('staff', null, $staffUser);
        
        $this->assertIsArray($scope);
        $this->assertEmpty($scope);
    }

    /**
     * Test: active_role takes precedence in getUserRole
     */
    public function testGetUserRoleUsesActivRoleFirst(): void
    {
        $authService = new AuthorizationService();

        // User with active_role set (multi-role user)
        $user = [
            'id' => 5,
            'role' => 'staff',
            'active_role' => 'provider', // Takes precedence
        ];

        $role = $authService->getUserRole($user);
        
        $this->assertEquals('provider', $role);
    }

    /**
     * Test: Dashboard schedule pane is scoped for staff
     */
    public function testDashboardSchedulePaneScoppedForStaff(): void
    {
        $db = \Config\Database::connect();
        $now = date('Y-m-d H:i:s');

        // Create staff and assign to provider
        $db->table('users')->insert([
            'name' => 'Staff For Schedule Test',
            'email' => 'staff_sched@test.com',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'staff',
            'status' => 'active',
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $staffId = (int) $db->insertID();

        $db->table('provider_staff_assignments')->insert([
            'provider_id' => $this->providerId,
            'staff_id' => $staffId,
            'status' => 'active',
            'assigned_at' => $now,
        ]);

        $staffUser = $db->table('users')->where('id', $staffId)->get()->getRowArray();
        
        // Simulate logged-in staff
        session()->set([
            'user_id' => $staffId,
            'user' => $staffUser,
            'isLoggedIn' => true,
        ]);

        $authService = new AuthorizationService();
        $scope = $authService->getProviderScope('staff', null, $staffUser);

        $dashboardService = new DashboardService();
        
        // Schedule should use the scope (which is an array of provider IDs)
        $schedule = $dashboardService->getTodaySchedule($scope);
        
        // Should be an array (may be empty if no appointments for the assigned provider today)
        $this->assertIsArray($schedule);
    }

    /**
     * Test: Metrics endpoint respects staff scope
     */
    public function testMetricsEndpointRespectStaffScope(): void
    {
        $db = \Config\Database::connect();
        $now = date('Y-m-d H:i:s');

        $db->table('users')->insert([
            'name' => 'Staff For Metrics Test',
            'email' => 'staff_metrics@test.com',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'staff',
            'status' => 'active',
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $staffId = (int) $db->insertID();

        $db->table('provider_staff_assignments')->insert([
            'provider_id' => $this->providerId,
            'staff_id' => $staffId,
            'status' => 'active',
            'assigned_at' => $now,
        ]);

        $staffUser = $db->table('users')->where('id', $staffId)->get()->getRowArray();
        
        session()->set([
            'user_id' => $staffId,
            'user' => $staffUser,
            'isLoggedIn' => true,
        ]);

        $authService = new AuthorizationService();
        $scope = $authService->getProviderScope('staff', null, $staffUser);

        $dashboardService = new DashboardService();
        
        // Metrics should be calculated for the staff's assigned providers
        $metrics = $dashboardService->getTodayMetrics($scope);
        
        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('total', $metrics);
        $this->assertArrayHasKey('pending', $metrics);
        $this->assertArrayHasKey('confirmed', $metrics);
    }

    protected function tearDown(): void
    {
        session()->destroy();
        parent::tearDown();
        
        // Clear cache
        cache()->clean();
    }
}
