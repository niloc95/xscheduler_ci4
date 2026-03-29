<?php

namespace Tests\Unit\Services;

use App\Models\AppointmentModel;
use App\Models\BusinessHourModel;
use App\Models\CustomerModel;
use App\Models\ProviderScheduleModel;
use App\Models\ServiceModel;
use App\Models\UserModel;
use App\Services\AppointmentDashboardContextService;
use App\Services\AvailabilityService;
use App\Services\DashboardService;
use App\Services\LocalizationSettingsService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class DashboardServiceTest extends CIUnitTestCase
{
    public function testGetDashboardContextUsesLocalizationAndUserData(): void
    {
        $userModel = $this->createMock(UserModel::class);
        $userModel->expects($this->once())
            ->method('find')
            ->with(42)
            ->willReturn([
                'name' => 'Dana Admin',
                'email' => 'dana@example.com',
            ]);

        $localization = $this->createMock(LocalizationSettingsService::class);
        $localization->expects($this->once())
            ->method('getContext')
            ->willReturn(['date_format' => 'd/m/Y']);
        $localization->expects($this->once())
            ->method('getTimezone')
            ->willReturn('Africa/Johannesburg');

        $service = new DashboardService(
            $this->createMock(AppointmentModel::class),
            $userModel,
            $this->createMock(ServiceModel::class),
            $this->createMock(CustomerModel::class),
            $this->createMock(ProviderScheduleModel::class),
            $this->createMock(BusinessHourModel::class),
            $localization,
            $this->createMock(AvailabilityService::class)
        );

        $context = $service->getDashboardContext(42, 'admin', null);

        $this->assertSame('Dana Admin', $context['user_name']);
        $this->assertSame('dana@example.com', $context['user_email']);
        $this->assertSame('admin', $context['user_role']);
        $this->assertSame('Africa/Johannesburg', $context['timezone']);
        $this->assertSame(date('d/m/Y'), $context['current_date']);
    }

    public function testGetAlertsReturnsPendingConfirmationAlert(): void
    {
        $service = new TestDashboardService();
        $service->pendingAlertCount = 3;

        $alerts = $service->getAlerts(77);

        $this->assertCount(1, $alerts);
        $this->assertSame(77, $service->capturedProviderIdForAlerts);
        $this->assertSame('confirmation_pending', $alerts[0]['type']);
        $this->assertSame('warning', $alerts[0]['severity']);
        $this->assertSame('3 appointment(s) awaiting confirmation', $alerts[0]['message']);
    }

    public function testGetAlertsReturnsEmptyArrayWhenNoPendingAppointmentsExist(): void
    {
        $service = new TestDashboardService();
        $service->pendingAlertCount = 0;

        $this->assertSame([], $service->getAlerts());
    }

    public function testGetCachedMetricsMemoizesPerScopeAndInvalidateCacheClearsProviderAndAdminKeys(): void
    {
        $service = new TestDashboardService();

        $firstAdmin = $service->getCachedMetrics();
        $secondAdmin = $service->getCachedMetrics();
        $firstProvider = $service->getCachedMetrics(9);
        $secondProvider = $service->getCachedMetrics(9);

        $this->assertSame($firstAdmin, $secondAdmin);
        $this->assertSame($firstProvider, $secondProvider);
        $this->assertSame(1, $service->metricCallCount['admin']);
        $this->assertSame(1, $service->metricCallCount['9']);

        $service->invalidateCache(9);

        $thirdAdmin = $service->getCachedMetrics();
        $thirdProvider = $service->getCachedMetrics(9);

        $this->assertNotSame($firstAdmin['sequence'], $thirdAdmin['sequence']);
        $this->assertNotSame($firstProvider['sequence'], $thirdProvider['sequence']);
        $this->assertSame(2, $service->metricCallCount['admin']);
        $this->assertSame(2, $service->metricCallCount['9']);
    }

    public function testFormatRecentActivitiesMapsStatusesToReadablePayload(): void
    {
        $service = new TestDashboardService();

        $activities = $service->formatRecentActivities([
            [
                'customer_name' => 'Pat Doe',
                'service_name' => 'Consultation',
                'status' => 'booked',
                'updated_at' => '2026-03-20 14:00:00',
            ],
            [
                'customer_name' => 'Chris Poe',
                'service_name' => 'Review',
                'status' => 'cancelled',
                'updated_at' => '2026-03-21 09:00:00',
            ],
            [
                'customer_name' => 'Alex Roe',
                'service_name' => 'Follow Up',
                'status' => 'rescheduled',
                'updated_at' => '2026-03-22 10:30:00',
            ],
        ]);

        $this->assertSame('Pat Doe', $activities[0]['user_name']);
        $this->assertSame('Scheduled appointment for Consultation', $activities[0]['activity']);
        $this->assertSame('active', $activities[0]['status']);
        $this->assertSame('cancelled', $activities[1]['status']);
        $this->assertSame('Rescheduled appointment for Follow Up', $activities[2]['activity']);
        $this->assertSame('pending', $activities[2]['status']);
        $this->assertSame('2026-03-22', $activities[2]['date']);
    }

    public function testGetDashboardContextMergesAppointmentScopeFlags(): void
    {
        $userModel = $this->createMock(UserModel::class);
        $userModel->method('find')->with(7)->willReturn([
            'name' => 'Priya Provider',
            'email' => 'priya@example.com',
        ]);

        $localization = $this->createMock(LocalizationSettingsService::class);
        $localization->method('getContext')->willReturn(['date_format' => 'Y-m-d']);
        $localization->method('getTimezone')->willReturn('UTC');

        $appointmentContext = $this->createMock(AppointmentDashboardContextService::class);
        $appointmentContext->expects($this->once())
            ->method('build')
            ->with('provider', 7, [
                'name' => 'Priya Provider',
                'email' => 'priya@example.com',
            ])
            ->willReturn([
                'role' => 'provider',
                'provider_id' => 7,
                'filter_by_provider' => true,
                'filter_by_staff' => false,
            ]);

        $service = new DashboardService(
            $this->createMock(AppointmentModel::class),
            $userModel,
            $this->createMock(ServiceModel::class),
            $this->createMock(CustomerModel::class),
            $this->createMock(ProviderScheduleModel::class),
            $this->createMock(BusinessHourModel::class),
            $localization,
            $this->createMock(AvailabilityService::class),
            $appointmentContext
        );

        $context = $service->getDashboardContext(7, 'provider', 7);

        $this->assertSame('Priya Provider', $context['user_name']);
        $this->assertSame('provider', $context['role']);
        $this->assertSame(7, $context['provider_id']);
        $this->assertTrue($context['filter_by_provider']);
        $this->assertFalse($context['filter_by_staff']);
    }
}

final class TestDashboardService extends DashboardService
{
    public int $pendingAlertCount = 0;
    public ?int $capturedProviderIdForAlerts = null;
    public array $metricCallCount = [
        'admin' => 0,
    ];
    private array $cacheStore = [];
    private int $sequence = 0;

    public function __construct()
    {
        parent::__construct(
            null,
            null,
            null,
            null,
            null,
            null,
            $this->buildLocalizationStub(),
            null
        );
    }

    public function getTodayMetrics(?int $providerId = null): array
    {
        $key = (string) ($providerId ?? 'admin');
        $this->metricCallCount[$key] = ($this->metricCallCount[$key] ?? 0) + 1;
        $this->sequence++;

        return [
            'scope' => $key,
            'sequence' => $this->sequence,
        ];
    }

    protected function countPendingConfirmationAlerts(?int $providerId, string $dayStartUtc): int
    {
        $this->capturedProviderIdForAlerts = $providerId;
        return $this->pendingAlertCount;
    }

    protected function rememberCache(string $cacheKey, int $ttl, callable $resolver): array
    {
        if (!array_key_exists($cacheKey, $this->cacheStore)) {
            $this->cacheStore[$cacheKey] = $resolver();
        }

        return $this->cacheStore[$cacheKey];
    }

    protected function deleteCacheKey(string $cacheKey): void
    {
        unset($this->cacheStore[$cacheKey]);
    }

    private function buildLocalizationStub(): LocalizationSettingsService
    {
        return new class extends LocalizationSettingsService {
            public function __construct()
            {
            }

            public function getTimezone(): string
            {
                return 'UTC';
            }

            public function getContext(): array
            {
                return ['date_format' => 'Y-m-d'];
            }
        };
    }
}