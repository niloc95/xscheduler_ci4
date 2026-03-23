<?php

namespace Tests\Unit\Services;

use App\Models\AppointmentModel;
use App\Models\ProviderStaffModel;
use App\Services\Appointment\AppointmentQueryService;
use App\Services\LocalizationSettingsService;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Test\CIUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @internal
 */
final class AppointmentQueryServiceTest extends CIUnitTestCase
{
    public function testGetForRangeScopesProviderToOwnAppointmentsAndConvertsLocalRangeToUtc(): void
    {
        $builderState = $this->makeBuilderState([]);
        $builder = $this->createBuilderMock($builderState);

        $model = $this->createAppointmentModelMock($builder);
        $localization = $this->createLocalizationMock('Africa/Johannesburg');

        $service = new AppointmentQueryService(
            $model,
            $this->createMock(ProviderStaffModel::class),
            $localization
        );

        $service->getForRange('2026-05-10', '2026-05-12', [
            'user_role' => 'provider',
            'scope_to_user_id' => 7,
        ]);

        $this->assertContains(['where', 'xs_appointments.start_at >=', '2026-05-09 22:00:00'], $builderState['calls']);
        $this->assertContains(['where', 'xs_appointments.start_at <=', '2026-05-12 21:59:59'], $builderState['calls']);
        $this->assertContains(['where', 'xs_appointments.provider_id', 7], $builderState['calls']);
        $this->assertContains(['orderBy', 'xs_appointments.start_at', 'ASC'], $builderState['calls']);
    }

    public function testGetForCalendarRestrictsStaffToAssignedProviderIntersection(): void
    {
        $builderState = $this->makeBuilderState([
            ['id' => 10, 'provider_id' => 5, 'start_at' => '2026-05-10 08:00:00'],
        ], 1);
        $builder = $this->createBuilderMock($builderState);

        $model = $this->createAppointmentModelMock($builder);
        $providerStaffModel = $this->createMock(ProviderStaffModel::class);
        $providerStaffModel->expects($this->once())
            ->method('getProvidersForStaff')
            ->with(20, 'active')
            ->willReturn([
                ['id' => 3],
                ['id' => 5],
            ]);

        $service = new AppointmentQueryService(
            $model,
            $providerStaffModel,
            $this->createLocalizationMock('UTC')
        );

        $result = $service->getForCalendar([
            'user_role' => 'staff',
            'scope_to_user_id' => 20,
            'provider_ids' => [5, 9],
            'page' => 2,
            'length' => 25,
        ]);

        $this->assertContains(['whereIn', 'xs_appointments.provider_id', [5]], $builderState['calls']);
        $this->assertContains(['limit', 25, 25], $builderState['calls']);
        $this->assertSame(1, $result['total']);
        $this->assertSame(2, $result['page']);
        $this->assertSame(25, $result['length']);
    }

    public function testGetForCalendarUsesImpossibleFilterWhenStaffRequestsUnassignedProvider(): void
    {
        $builderState = $this->makeBuilderState([], 0);
        $builder = $this->createBuilderMock($builderState);

        $model = $this->createAppointmentModelMock($builder);
        $providerStaffModel = $this->createMock(ProviderStaffModel::class);
        $providerStaffModel->expects($this->once())
            ->method('getProvidersForStaff')
            ->with(20, 'active')
            ->willReturn([
                ['id' => 3],
                ['id' => 5],
            ]);

        $service = new AppointmentQueryService(
            $model,
            $providerStaffModel,
            $this->createLocalizationMock('UTC')
        );

        $service->getForCalendar([
            'user_role' => 'staff',
            'scope_to_user_id' => 20,
            'provider_id' => 9,
        ]);

        $this->assertContains(['where', 'xs_appointments.provider_id', 0], $builderState['calls']);
    }

    public function testGetGroupedByDateUsesLocalizedDateKeys(): void
    {
        $builderState = $this->makeBuilderState([
            [
                'id' => 1,
                'provider_id' => 4,
                'start_at' => '2026-05-10 23:30:00',
            ],
            [
                'id' => 2,
                'provider_id' => 4,
                'start_at' => '2026-05-11 08:00:00',
            ],
        ]);
        $builder = $this->createBuilderMock($builderState);

        $service = new AppointmentQueryService(
            $this->createAppointmentModelMock($builder),
            $this->createMock(ProviderStaffModel::class),
            $this->createLocalizationMock('Africa/Johannesburg')
        );

        $grouped = $service->getGroupedByDate('2026-05-10', '2026-05-11');

        $this->assertArrayHasKey('2026-05-11', $grouped);
        $this->assertCount(2, $grouped['2026-05-11']);
    }

    private function createAppointmentModelMock(BaseBuilder $builder): AppointmentModel
    {
        /** @var AppointmentModel&MockObject $model */
        $model = $this->getMockBuilder(AppointmentModel::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['builder'])
            ->getMock();
        $model->method('builder')->willReturn($builder);

        return $model;
    }

    private function createLocalizationMock(string $timezone): LocalizationSettingsService
    {
        $localization = $this->createMock(LocalizationSettingsService::class);
        $localization->method('getTimezone')->willReturn($timezone);

        return $localization;
    }

    private function makeBuilderState(array $rows, int $count = 0): array
    {
        return [
            'calls' => [],
            'rows' => $rows,
            'count' => $count,
        ];
    }

    private function createBuilderMock(array &$state): BaseBuilder
    {
        /** @var BaseBuilder&MockObject $builder */
        $builder = $this->getMockBuilder(BaseBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['select', 'join', 'where', 'whereIn', 'orderBy', 'limit', 'get', 'countAllResults'])
            ->getMock();

        $builder->method('select')->willReturnCallback(function (...$args) use (&$state, $builder) {
            $state['calls'][] = ['select', $args[0] ?? '*'];
            return $builder;
        });
        $builder->method('join')->willReturnCallback(function (...$args) use (&$state, $builder) {
            $state['calls'][] = ['join', $args[0] ?? '', $args[1] ?? '', $args[2] ?? ''];
            return $builder;
        });
        $builder->method('where')->willReturnCallback(function (...$args) use (&$state, $builder) {
            $state['calls'][] = ['where', $args[0] ?? null, $args[1] ?? null];
            return $builder;
        });
        $builder->method('whereIn')->willReturnCallback(function (...$args) use (&$state, $builder) {
            $state['calls'][] = ['whereIn', $args[0] ?? null, $args[1] ?? []];
            return $builder;
        });
        $builder->method('orderBy')->willReturnCallback(function (...$args) use (&$state, $builder) {
            $state['calls'][] = ['orderBy', $args[0] ?? null, $args[1] ?? ''];
            return $builder;
        });
        $builder->method('limit')->willReturnCallback(function (...$args) use (&$state, $builder) {
            $state['calls'][] = ['limit', $args[0] ?? null, $args[1] ?? 0];
            return $builder;
        });
        $builder->method('countAllResults')->willReturnCallback(function (...$args) use (&$state) {
            $state['calls'][] = ['countAllResults', $args[0] ?? true];
            return $state['count'];
        });
        $builder->method('get')->willReturnCallback(function () use (&$state) {
            return new class($state['rows']) {
                public function __construct(private array $rows)
                {
                }

                public function getResultArray(): array
                {
                    return $this->rows;
                }
            };
        });

        return $builder;
    }
}