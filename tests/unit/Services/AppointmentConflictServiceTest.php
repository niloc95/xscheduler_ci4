<?php

namespace Tests\Unit\Services;

use App\Models\AppointmentModel;
use App\Models\BlockedTimeModel;
use App\Services\Appointment\AppointmentConflictService;
use CodeIgniter\Test\CIUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @internal
 */
final class AppointmentConflictServiceTest extends CIUnitTestCase
{
    public function testHasConflictUsesConflictingAppointments(): void
    {
        /** @var AppointmentConflictService&MockObject $service */
        $service = $this->getMockBuilder(AppointmentConflictService::class)
            ->onlyMethods(['getConflictingAppointments'])
            ->getMock();

        $service->method('getConflictingAppointments')
            ->willReturnOnConsecutiveCalls([], [['id' => 9]]);

        $this->assertFalse($service->hasConflict(1, '2026-02-27 08:00:00', '2026-02-27 09:00:00'));
        $this->assertTrue($service->hasConflict(1, '2026-02-27 08:00:00', '2026-02-27 09:00:00'));
    }

    public function testGetBlockedTimesForPeriodReturnsRows(): void
    {
        $blockedRows = [
            ['id' => 1, 'start_at' => '2026-02-27 08:00:00', 'end_at' => '2026-02-27 09:00:00'],
        ];

        $blockedModel = $this->getMockBuilder(BlockedTimeModel::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['groupStart', 'where', 'orWhere', 'groupEnd', 'findAll'])
            ->getMock();

        $blockedModel->method('groupStart')->willReturnSelf();
        $blockedModel->method('where')->willReturnSelf();
        $blockedModel->method('orWhere')->willReturnSelf();
        $blockedModel->method('groupEnd')->willReturnSelf();
        $blockedModel->method('findAll')->willReturn($blockedRows);

        $appointmentModel = $this->getMockBuilder(AppointmentModel::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['builder'])
            ->getMock();

        $service = new AppointmentConflictService($appointmentModel, $blockedModel);

        $result = $service->getBlockedTimesForPeriod(2, '2026-02-27 08:00:00', '2026-02-27 09:00:00');

        $this->assertSame($blockedRows, $result);
    }

    public function testGetConflictingAppointmentsReturnsRows(): void
    {
        $rows = [
            ['id' => 42, 'start_at' => '2026-02-27 08:00:00', 'end_at' => '2026-02-27 09:00:00'],
        ];

        $builder = new class($rows) {
            private array $rows;

            public function __construct(array $rows)
            {
                $this->rows = $rows;
            }

            public function where(...$args): self
            {
                return $this;
            }

            public function groupStart(): self
            {
                return $this;
            }

            public function groupEnd(): self
            {
                return $this;
            }

            public function orGroupStart(): self
            {
                return $this;
            }

            public function get()
            {
                return new class($this->rows) {
                    private array $rows;

                    public function __construct(array $rows)
                    {
                        $this->rows = $rows;
                    }

                    public function getResultArray(): array
                    {
                        return $this->rows;
                    }
                };
            }
        };

        $appointmentModel = $this->getMockBuilder(AppointmentModel::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['builder'])
            ->getMock();
        $appointmentModel->method('builder')->willReturn($builder);

        $blockedModel = $this->getMockBuilder(BlockedTimeModel::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['groupStart', 'where', 'orWhere', 'groupEnd', 'findAll'])
            ->getMock();

        $service = new AppointmentConflictService($appointmentModel, $blockedModel);

        $result = $service->getConflictingAppointments(3, '2026-02-27 08:00:00', '2026-02-27 09:00:00');

        $this->assertSame($rows, $result);
    }
}
