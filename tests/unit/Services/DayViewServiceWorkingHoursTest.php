<?php

namespace Tests\Unit\Services;

use App\Models\ProviderScheduleModel;
use App\Services\Appointment\AppointmentFormatterService;
use App\Services\Appointment\AppointmentQueryService;
use App\Services\Calendar\CalendarRangeService;
use App\Services\Calendar\DayViewService;
use App\Services\Calendar\EventLayoutService;
use App\Services\Calendar\TimeGridService;
use CodeIgniter\Test\CIUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionMethod;

final class ProviderScheduleModelFake extends ProviderScheduleModel
{
    /** @var array<string, mixed>|null */
    private ?array $row;

    public function __construct(?array $row)
    {
        $this->row = $row;
    }

    public function where($key = null, $value = null, ?bool $escape = null)
    {
        return $this;
    }

    public function first()
    {
        return $this->row;
    }
}

/**
 * @internal
 */
final class DayViewServiceWorkingHoursTest extends CIUnitTestCase
{
    public function testUsesProviderScheduleWhenActive(): void
    {
        $scheduleModel = $this->buildScheduleModelMock([
            'provider_id' => 7,
            'day_of_week' => 1,
            'start_time' => '09:00:00',
            'end_time' => '16:00:00',
            'break_start' => '12:00:00',
            'break_end' => '13:00:00',
            'is_active' => 1,
        ]);

        $service = $this->buildService($scheduleModel, '08:00', '17:00');
        $result = $this->invokeGetProviderWorkingHours($service, 7, 1);

        $this->assertSame('09:00', $result['startTime']);
        $this->assertSame('16:00', $result['endTime']);
        $this->assertSame('12:00', $result['breakStart']);
        $this->assertSame('13:00', $result['breakEnd']);
        $this->assertSame('provider_schedule', $result['source']);
        $this->assertTrue($result['isActive']);
    }

    public function testFallsBackToBusinessHoursWhenProviderScheduleInactive(): void
    {
        $scheduleModel = $this->buildScheduleModelMock([
            'provider_id' => 7,
            'day_of_week' => 1,
            'start_time' => '09:00:00',
            'end_time' => '16:00:00',
            'break_start' => null,
            'break_end' => null,
            'is_active' => 0,
        ]);

        $service = $this->buildService($scheduleModel, '08:00', '17:00');
        $result = $this->invokeGetProviderWorkingHours($service, 7, 1);

        $this->assertSame('08:00', $result['startTime']);
        $this->assertSame('17:00', $result['endTime']);
        $this->assertSame('business_hours', $result['source']);
        $this->assertFalse($result['isActive']);
    }

    public function testFallsBackToBusinessHoursWhenProviderScheduleMissing(): void
    {
        $scheduleModel = $this->buildScheduleModelMock(null);

        $service = $this->buildService($scheduleModel, '08:00', '17:00');
        $result = $this->invokeGetProviderWorkingHours($service, 7, 1);

        $this->assertSame('08:00', $result['startTime']);
        $this->assertSame('17:00', $result['endTime']);
        $this->assertSame('business_hours', $result['source']);
        $this->assertFalse($result['isActive']);
    }

    /**
     * @param array<string, mixed>|null $scheduleRow
     */
    private function buildScheduleModelMock(?array $scheduleRow): ProviderScheduleModel
    {
        return new ProviderScheduleModelFake($scheduleRow);
    }

    private function buildService(ProviderScheduleModel $scheduleModel, string $dayStart, string $dayEnd): DayViewService
    {
        /** @var TimeGridService&MockObject $timeGrid */
        $timeGrid = $this->getMockBuilder(TimeGridService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getDayStart', 'getDayEnd'])
            ->getMock();

        $timeGrid->method('getDayStart')->willReturn($dayStart);
        $timeGrid->method('getDayEnd')->willReturn($dayEnd);

        return new DayViewService(
            $this->getMockBuilder(CalendarRangeService::class)->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder(AppointmentQueryService::class)->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder(AppointmentFormatterService::class)->disableOriginalConstructor()->getMock(),
            $timeGrid,
            $this->getMockBuilder(EventLayoutService::class)->disableOriginalConstructor()->getMock(),
            $scheduleModel
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function invokeGetProviderWorkingHours(DayViewService $service, int $providerId, int $dayOfWeek): array
    {
        $method = new ReflectionMethod($service, 'getProviderWorkingHours');
        $method->setAccessible(true);

        /** @var array<string, mixed> $result */
        $result = $method->invoke($service, $providerId, $dayOfWeek);

        return $result;
    }
}
