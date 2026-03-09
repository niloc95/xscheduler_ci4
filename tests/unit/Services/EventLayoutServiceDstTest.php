<?php

namespace Tests\Unit\Services;

use App\Services\Calendar\EventLayoutService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class EventLayoutServiceDstTest extends CIUnitTestCase
{
    public function testColumnAssignmentAcrossDstSpringForwardTransition(): void
    {
        $service = new EventLayoutService();

        // US DST starts on 2026-03-08 at 02:00 local time (America/New_York).
        // Event A spans the jump; Event B overlaps A after the jump; Event C does not overlap.
        $events = [
            [
                'id' => 1,
                'start_at' => '2026-03-08T01:30:00-05:00',
                'end_at' => '2026-03-08T03:30:00-04:00',
            ],
            [
                'id' => 2,
                'start_at' => '2026-03-08T03:00:00-04:00',
                'end_at' => '2026-03-08T04:00:00-04:00',
            ],
            [
                'id' => 3,
                'start_at' => '2026-03-08T04:00:00-04:00',
                'end_at' => '2026-03-08T05:00:00-04:00',
            ],
        ];

        $result = $service->resolveLayout($events);

        $byId = [];
        foreach ($result as $row) {
            $byId[(int) $row['id']] = $row;
        }

        $this->assertArrayHasKey(1, $byId);
        $this->assertArrayHasKey(2, $byId);
        $this->assertArrayHasKey(3, $byId);

        // A and B overlap across DST boundary and must split columns.
        $this->assertSame(2, (int) $byId[1]['_columns_total']);
        $this->assertSame(2, (int) $byId[2]['_columns_total']);
        $this->assertNotSame($byId[1]['_column'], $byId[2]['_column']);

        // C starts exactly when B ends and should not overlap.
        $this->assertSame(1, (int) $byId[3]['_columns_total']);
        $this->assertSame(0, (int) $byId[3]['_column']);
    }
}
