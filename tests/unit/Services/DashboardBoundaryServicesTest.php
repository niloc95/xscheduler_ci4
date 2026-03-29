<?php

namespace Tests\Unit\Services;

use App\Models\AppointmentModel;
use App\Models\ServiceModel;
use App\Models\UserModel;
use App\Services\AppointmentDashboardContextService;
use App\Services\AuthorizationService;
use App\Services\DashboardApiService;
use App\Services\DashboardService;
use App\Services\DashboardPageService;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class DashboardBoundaryServicesTest extends CIUnitTestCase
{
    protected function tearDown(): void
    {
        session()->destroy();

        parent::tearDown();
    }

    public function testDashboardPageServiceReturnsRedirectWhenSessionIsMissing(): void
    {
        $service = new DashboardPageService();

        $result = $service->resolveLandingSession();

        $this->assertInstanceOf(RedirectResponse::class, $result);
        $this->assertStringContainsString('/auth/login', $result->getHeaderLine('Location'));
    }

    public function testDashboardPageServiceRejectsMetricsWithoutAuthenticatedUser(): void
    {
        $service = new DashboardPageService();

        $result = $service->getMetricsEndpointResponse();

        $this->assertSame(401, $result['statusCode']);
        $this->assertFalse((bool) ($result['payload']['success'] ?? true));
        $this->assertSame('Unauthorized', $result['payload']['error']);
    }

    public function testDashboardPageServiceFallbackLandingDataContainsExpectedKeys(): void
    {
        $service = new DashboardPageService();

        $payload = $service->getFallbackLandingViewData();

        $this->assertSame('System Administrator', $payload['user']['name']);
        $this->assertArrayHasKey('metrics', $payload);
        $this->assertArrayHasKey('schedule', $payload);
        $this->assertArrayHasKey('recent_activities', $payload);
        $this->assertSame(0, $payload['metrics']['total']);
    }

    public function testDashboardPageServiceMetricsErrorPayloadUsesFallbackShape(): void
    {
        $service = new DashboardPageService();

        $payload = $service->getMetricsErrorPayload('boom');

        $this->assertFalse((bool) ($payload['success'] ?? true));
        $this->assertSame('Internal Server Error', $payload['error']);
        $this->assertArrayHasKey('data', $payload);
        $this->assertSame(0, $payload['data']['total']);
    }

    public function testDashboardApiServiceNormalizesUnknownPeriodToMonth(): void
    {
        $service = new DashboardApiService();

        $this->assertSame('month', $service->normalizePeriod('not-a-period'));
        $this->assertSame('week', $service->normalizePeriod('week'));
    }

    public function testDashboardApiServiceReturnsChartFallbackPayload(): void
    {
        $service = new DashboardApiService();

        $payload = $service->getChartsFallbackPayload('failure');

        $this->assertSame('failure', $payload['error']);
        $this->assertSame(['No Data'], $payload['appointmentGrowth']['labels']);
        $this->assertSame([0], $payload['statusDistribution']['data']);
    }

    public function testDashboardApiServiceReturnsStatusErrorPayload(): void
    {
        $service = new DashboardApiService();

        $payload = $service->getStatusErrorPayload('db offline');

        $this->assertFalse((bool) ($payload['database_connected'] ?? true));
        $this->assertSame('db offline', $payload['error']);
    }

    public function testDashboardPageServiceBuildLandingViewDataIncludesAppointmentScope(): void
    {
        session()->set('user_id', 14);

        $userModel = $this->createMock(UserModel::class);
        $userModel->method('getStats')->willReturn(['total' => 5]);
        $userModel->method('getTrend')->willReturn(['percentage' => 0, 'direction' => 'neutral']);

        $serviceModel = new class extends ServiceModel {
            public function __construct()
            {
            }

            public function getStats(): array
            {
                return ['total' => 2];
            }

            public function orderBy($column = null, $direction = '', ?bool $escape = null)
            {
                return $this;
            }

            public function findAll(?int $limit = null, int $offset = 0)
            {
                return [];
            }
        };

        $appointmentModel = $this->createMock(AppointmentModel::class);
        $appointmentModel->method('getStats')->willReturn(['upcoming' => 3, 'today' => 1]);
        $appointmentModel->method('getRevenue')->willReturnMap([
            ['month', 200],
            ['week', 75],
            ['today', 25],
        ]);
        $appointmentModel->method('getTrend')->willReturn(['percentage' => 0, 'direction' => 'neutral']);
        $appointmentModel->method('getPendingTrend')->willReturn(['percentage' => 0, 'direction' => 'neutral']);
        $appointmentModel->method('getRevenueTrend')->willReturn(['percentage' => 0, 'direction' => 'neutral']);
        $appointmentModel->method('getRecentActivity')->willReturn([]);

        $dashboardService = $this->createMock(DashboardService::class);
        $dashboardService->method('getDashboardContext')->willReturn(['business_name' => 'WebScheduler']);
        $dashboardService->method('getCachedMetrics')->willReturn(['total' => 2]);
        $dashboardService->method('getTodaySchedule')->willReturn([]);
        $dashboardService->method('getAlerts')->willReturn([]);
        $dashboardService->method('getUpcomingAppointments')->willReturn([]);
        $dashboardService->method('getProviderAvailability')->willReturn([]);
        $dashboardService->method('getBookingStatus')->willReturn(['open' => true]);
        $dashboardService->method('formatRecentActivities')->with([])->willReturn([]);

        $authService = $this->createMock(AuthorizationService::class);
        $authService->method('canViewBookingStatus')->with('provider')->willReturn(true);

        $appointmentScopeService = $this->createMock(AppointmentDashboardContextService::class);
        $appointmentScopeService->expects($this->once())
            ->method('build')
            ->with('provider', 14, ['name' => 'Provider User', 'role' => 'provider'])
            ->willReturn([
                'role' => 'provider',
                'provider_id' => 14,
                'filter_by_provider' => true,
            ]);

        $service = new DashboardPageService(
            $userModel,
            $serviceModel,
            $appointmentModel,
            $dashboardService,
            $authService,
            $appointmentScopeService
        );

        $payload = $service->buildLandingViewData([
            'currentUser' => ['name' => 'Provider User', 'role' => 'provider'],
            'userRole' => 'provider',
            'providerId' => 14,
            'providerScope' => 14,
        ]);

        $this->assertSame('Provider User', $payload['user']['name']);
        $this->assertSame('provider', $payload['appointment_scope']['role']);
        $this->assertSame(14, $payload['appointment_scope']['provider_id']);
        $this->assertTrue($payload['appointment_scope']['filter_by_provider']);
        $this->assertSame(2, $payload['metrics']['total']);
    }

    public function testAuthorizationServiceRoleChecksRemainConsistent(): void
    {
        $service = new AuthorizationService();

        $this->assertTrue($service->canViewDashboardMetrics('admin'));
        $this->assertTrue($service->canViewDashboardMetrics('provider'));
        $this->assertFalse($service->canViewSettings('provider'));
        $this->assertTrue($service->canViewSettings('admin'));
        $this->assertNull($service->getProviderScope('admin', null));
        $this->assertSame(7, $service->getProviderScope('provider', 7));
    }
}