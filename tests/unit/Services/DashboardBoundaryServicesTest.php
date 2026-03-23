<?php

namespace Tests\Unit\Services;

use App\Services\AuthorizationService;
use App\Services\DashboardApiService;
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