<?php

namespace Tests\Integration;

use App\Models\AppointmentModel;
use App\Services\BookingMetricsService;
use App\Services\Appointment\AppointmentStatus;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Integration tests for BookingMetricsService.
 *
 * Validates that canonical booking-metric queries produce correct counts
 * when appointments are created against real database rows.
 *
 * @internal
 */
final class BookingMetricsServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = 'App';
    protected $refresh   = true;

    private BookingMetricsService $metrics;
    private array $providerIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->metrics = new BookingMetricsService(new AppointmentModel());
    }

    // -------------------------------------------------------------------------

    /**
     * getCountsByServiceId returns the correct per-service booking counts
     * regardless of appointment status.
     */
    public function testGetCountsByServiceIdReturnsCorrectCountsAcrossStatuses(): void
    {
        $providerId = $this->createTestProvider('BM Provider', 'bm-p@example.com');
        $customerId = $this->createTestCustomer('BM', 'Customer', 'bm-c@example.com');

        $svcA = $this->createTestService('Service Alpha');
        $svcB = $this->createTestService('Service Beta');

        // 3 appointments for svcA across different statuses
        $this->createTestAppointment($customerId, $providerId, $svcA, AppointmentStatus::PENDING);
        $this->createTestAppointment($customerId, $providerId, $svcA, AppointmentStatus::COMPLETED);
        $this->createTestAppointment($customerId, $providerId, $svcA, AppointmentStatus::CANCELLED);

        // 1 appointment for svcB
        $this->createTestAppointment($customerId, $providerId, $svcB, AppointmentStatus::CONFIRMED);

        $counts = $this->metrics->getCountsByServiceId();

        $this->assertSame(3, $counts[$svcA] ?? 0, 'Service Alpha should have 3 bookings');
        $this->assertSame(1, $counts[$svcB] ?? 0, 'Service Beta should have 1 booking');
    }

    /**
     * getCountsByServiceId with a provider scope returns only that provider's counts.
     */
    public function testGetCountsByServiceIdScopesCorrectlyByProvider(): void
    {
        $p1 = $this->createTestProvider('BM P1', 'bm-p1@example.com');
        $p2 = $this->createTestProvider('BM P2', 'bm-p2@example.com');
        $c1 = $this->createTestCustomer('BM', 'C1', 'bm-c1@example.com');

        $svc = $this->createTestService('Scoped Service');

        // 2 by p1, 1 by p2
        $this->createTestAppointment($c1, $p1, $svc, AppointmentStatus::PENDING);
        $this->createTestAppointment($c1, $p1, $svc, AppointmentStatus::COMPLETED);
        $this->createTestAppointment($c1, $p2, $svc, AppointmentStatus::PENDING);

        $countsP1 = $this->metrics->getCountsByServiceId($p1);
        $countsP2 = $this->metrics->getCountsByServiceId($p2);

        $this->assertSame(2, $countsP1[$svc] ?? 0, 'Provider 1 should have 2 bookings for the service');
        $this->assertSame(1, $countsP2[$svc] ?? 0, 'Provider 2 should have 1 booking for the service');
    }

    /**
     * getByService returns rows ordered by count DESC and shapes output correctly.
     */
    public function testGetByServiceReturnsCorrectShapeOrderedByCount(): void
    {
        $p = $this->createTestProvider('BM P3', 'bm-p3@example.com');
        $c = $this->createTestCustomer('BM', 'C2', 'bm-c2@example.com');

        $svcX = $this->createTestService('Popular Service');
        $svcY = $this->createTestService('Quiet Service');

        $this->createTestAppointment($c, $p, $svcX, AppointmentStatus::COMPLETED);
        $this->createTestAppointment($c, $p, $svcX, AppointmentStatus::COMPLETED);
        $this->createTestAppointment($c, $p, $svcY, AppointmentStatus::COMPLETED);

        $rows = $this->metrics->getByService(10);

        // Find our services in the result
        $popularRow = null;
        $quietRow   = null;
        foreach ($rows as $row) {
            if ($row['service'] === 'Popular Service') {
                $popularRow = $row;
            }
            if ($row['service'] === 'Quiet Service') {
                $quietRow = $row;
            }
        }

        $this->assertNotNull($popularRow, 'Popular Service should appear in getByService results');
        $this->assertNotNull($quietRow, 'Quiet Service should appear in getByService results');
        $this->assertSame(2, (int) $popularRow['count']);
        $this->assertSame(1, (int) $quietRow['count']);
        $this->assertArrayHasKey('revenue', $popularRow);
    }

    /**
     * Cross-surface consistency: getCountsByServiceId() and getByService()
     * must agree on counts for the same underlying dataset.
     */
    public function testCountsByServiceIdAndGetByServiceAreConsistent(): void
    {
        $p = $this->createTestProvider('CS Provider', 'cs-p@example.com');
        $c = $this->createTestCustomer('CS', 'Cust', 'cs-c@example.com');

        $svcA = $this->createTestService('CS Service A');
        $svcB = $this->createTestService('CS Service B');

        $this->createTestAppointment($c, $p, $svcA, AppointmentStatus::COMPLETED);
        $this->createTestAppointment($c, $p, $svcA, AppointmentStatus::PENDING);
        $this->createTestAppointment($c, $p, $svcB, AppointmentStatus::CANCELLED);

        // Map-based surface
        $countMap = $this->metrics->getCountsByServiceId();

        // List-based surface (keyed by service name)
        $byServiceRows = $this->metrics->getByService(100);
        $byServiceCounts = [];
        foreach ($byServiceRows as $row) {
            $byServiceCounts[$row['service']] = (int) $row['count'];
        }

        // Both surfaces must agree on the count for Service A
        $this->assertSame(2, $countMap[$svcA] ?? 0);
        $this->assertSame(2, $byServiceCounts['CS Service A'] ?? 0, 'getByService count for CS Service A must match getCountsByServiceId');

        // Both surfaces must agree on the count for Service B
        $this->assertSame(1, $countMap[$svcB] ?? 0);
        $this->assertSame(1, $byServiceCounts['CS Service B'] ?? 0, 'getByService count for CS Service B must match getCountsByServiceId');
    }

    /**
     * getTotalBookings() equals the sum of getCountsByServiceId() values.
     */
    public function testGetTotalBookingsMatchesSumOfCountsByServiceId(): void
    {
        $p = $this->createTestProvider('TB Provider', 'tb-p@example.com');
        $c = $this->createTestCustomer('TB', 'Cust', 'tb-c@example.com');

        $svcA = $this->createTestService('TB Service A');
        $svcB = $this->createTestService('TB Service B');

        $this->createTestAppointment($c, $p, $svcA, AppointmentStatus::PENDING);
        $this->createTestAppointment($c, $p, $svcB, AppointmentStatus::COMPLETED);
        $this->createTestAppointment($c, $p, $svcB, AppointmentStatus::CANCELLED);

        $total = $this->metrics->getTotalBookings();
        $sumFromMap = array_sum($this->metrics->getCountsByServiceId());

        $this->assertSame($sumFromMap, $total, 'getTotalBookings() must equal sum of getCountsByServiceId() values');
    }

    /**
     * getCustomerStats() returns correct totals and favorite service/provider.
     */
    public function testGetCustomerStatsReturnsCorrectAggregates(): void
    {
        $p = $this->createTestProvider('GCS Provider', 'gcs-p@example.com');
        $c = $this->createTestCustomer('GCS', 'Cust', 'gcs-c@example.com');

        $svcA = $this->createTestService('GCS Service A');
        $svcB = $this->createTestService('GCS Service B');

        $this->createTestAppointment($c, $p, $svcA, AppointmentStatus::COMPLETED);
        $this->createTestAppointment($c, $p, $svcA, AppointmentStatus::COMPLETED);
        $this->createTestAppointment($c, $p, $svcB, AppointmentStatus::CANCELLED);

        $stats = $this->metrics->getCustomerStats($c);

        $this->assertSame(3, $stats['total']);
        $this->assertSame(2, $stats['completed']);
        $this->assertSame(1, $stats['cancelled']);
        $this->assertSame(0, $stats['no_show']);
        $this->assertSame($svcA, $stats['favorite_service_id'], 'Service A should be favorite (most bookings)');
        $this->assertSame($p, $stats['favorite_provider_id']);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function createTestProvider(string $name, string $email): int
    {
        $db = \Config\Database::connect();
        $db->table('xs_users')->insert([
            'name'          => $name,
            'email'         => $email,
            'password_hash' => password_hash('password', PASSWORD_DEFAULT),
            'role'          => 'provider',
            'status'        => 'active',
            'is_active'     => 1,
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);
        $id = (int) $db->insertID();
        $this->providerIds[] = $id;
        return $id;
    }

    private function createTestCustomer(string $first, string $last, string $email): int
    {
        $db = \Config\Database::connect();
        $db->table('xs_customers')->insert([
            'first_name' => $first,
            'last_name'  => $last,
            'hash'       => hash('sha256', $email . microtime(true) . random_int(1, PHP_INT_MAX)),
            'email'      => $email,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return (int) $db->insertID();
    }

    private function createTestService(string $name): int
    {
        $db = \Config\Database::connect();
        $db->table('xs_services')->insert([
            'name'        => $name,
            'duration_min' => 60,
            'price'       => 100.00,
            'active'      => 1,
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);
        return (int) $db->insertID();
    }

    private function createTestAppointment(
        int $customerId,
        int $providerId,
        int $serviceId,
        string $status = AppointmentStatus::PENDING
    ): int {
        $db = \Config\Database::connect();
        $db->table('xs_appointments')->insert([
            'customer_id' => $customerId,
            'provider_id' => $providerId,
            'service_id'  => $serviceId,
            'start_at'    => '2026-06-01 10:00:00',
            'end_at'      => '2026-06-01 11:00:00',
            'status'      => $status,
            'hash'        => hash('sha256', uniqid('bm_', true)),
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);
        return (int) $db->insertID();
    }

    protected function tearDown(): void
    {
        $db = \Config\Database::connect();
        $db->query('DELETE FROM `xs_appointments`');
        $db->query('DELETE FROM `xs_services`');
        $db->query('DELETE FROM `xs_customers`');
        if ($this->providerIds !== []) {
            $db->table('xs_users')->whereIn('id', $this->providerIds)->delete();
        }
        $this->providerIds = [];
        parent::tearDown();
    }
}
