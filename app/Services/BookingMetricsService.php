<?php

/**
 * =============================================================================
 * BOOKING METRICS SERVICE
 * =============================================================================
 *
 * @file        app/Services/BookingMetricsService.php
 * @description Canonical source of truth for all booking-count and
 *              appointment-aggregate metrics used across the application.
 *
 * METHODS:
 * -----------------------------------------------------------------------------
 * - getCountsByServiceId()    : [service_id => count] for every service
 * - getByService()            : per-service name/count/revenue list (analytics)
 * - getPopularServices()      : top-N services with booking count + revenue
 * - getStatsByStatus()        : appointment totals grouped by status
 * - getCustomerStats()        : full stats for one customer (replaces CustomerAppointmentService::getStats)
 *
 * SCOPING:
 * -----------------------------------------------------------------------------
 * All methods accept an optional $providerId.  When supplied only appointments
 * for that provider are counted.  When null the query is global (admin view).
 *
 * COUNT SEMANTICS:
 * -----------------------------------------------------------------------------
 * Service booking counts include ALL appointment statuses by design.
 *
 * @package     App\Services
 * @author      Nilesh Nagin Cara
 * @copyright   2024-2026 Nilesh Nagin Cara
 * =============================================================================
 */

namespace App\Services;

use App\Models\AppointmentModel;
use App\Services\Appointment\AppointmentStatus;
use CodeIgniter\Database\BaseConnection;

class BookingMetricsService
{
    private AppointmentModel $appointments;
    private BaseConnection   $db;

    public function __construct(?AppointmentModel $appointmentModel = null)
    {
        $this->appointments = $appointmentModel ?? new AppointmentModel();
        $this->db           = $this->appointments->db;
    }

    // -------------------------------------------------------------------------
    // Service-level counts
    // -------------------------------------------------------------------------

    /**
     * Return a map of service_id → total booking count (all statuses).
     *
     * @param int|int[]|null $providerScope Restrict to one or many providers.
     * @return array<int, int>       [service_id => count, ...]
     */
    public function getCountsByServiceId(int|array|null $providerScope = null): array
    {
        $apptTable    = $this->db->prefixTable('appointments');
        $builder      = $this->db->table($apptTable)
            ->select('service_id, COUNT(*) AS cnt', false)
            ->groupBy('service_id');

        $this->applyProviderScopeToBuilder($builder, $providerScope);

        $rows = $builder->get()->getResultArray();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['service_id']] = (int) $row['cnt'];
        }

        return $map;
    }

    /**
     * Per-service name + count + revenue list (used by Analytics appointments view).
     * Mirrors the current AppointmentModel::getByService() output shape.
     *
     * @param int      $limit        Max services to return.
     * @param int|int[]|null $providerScope Optional provider scope.
     * @return array   Each row: ['service' => string, 'count' => int, 'revenue' => float]
     */
    public function getByService(int $limit = 10, int|array|null $providerScope = null): array
    {
        $apptTable    = $this->db->prefixTable('appointments');
        $servicesTable = $this->db->prefixTable('services');

        $sql = "
            SELECT
                s.name              AS service,
                COUNT(a.id)         AS count,
                COALESCE(SUM(CASE WHEN a.status NOT IN ('cancelled', 'no-show', 'noshow') THEN s.price ELSE 0 END), 0) AS revenue
            FROM {$apptTable} a
            LEFT JOIN {$servicesTable} s ON a.service_id = s.id
            WHERE s.name IS NOT NULL
        ";

        $bindings = [];
        $sql .= $this->providerScopeSqlClause($providerScope, $bindings, 'a.provider_id');

        $sql .= " GROUP BY s.id, s.name ORDER BY count DESC LIMIT {$limit}";

        return $this->db->query($sql, $bindings)->getResultArray();
    }

    // -------------------------------------------------------------------------
    // Popular services (Services analytics tab)
    // -------------------------------------------------------------------------

    /**
     * Top-N services with booking counts, revenue, and 30-day growth.
     * Mirrors ServiceModel::getPopularServicesWithStats() output shape.
     *
     * @param int      $limit
     * @param int|int[]|null $providerScope
     * @return array   Each row: id, name, price, bookings, revenue, growth
     */
    public function getPopularServices(int $limit = 10, int|array|null $providerScope = null): array
    {
        $apptTable     = $this->db->prefixTable('appointments');
        $servicesTable = $this->db->prefixTable('services');
        $pivotTable    = $this->db->prefixTable('providers_services');

        // When scoped to a provider, INNER JOIN the xs_providers_services pivot so
        // only services in that provider's catalogue appear. Without this, the LEFT JOIN
        // on appointments would return all active services (with bookings=0 for others).
        // Admin (null scope) gets no pivot join — all active services are shown.
        $pivotJoin = '';
        if ($providerScope !== null) {
            $pid       = is_int($providerScope) ? $providerScope : (int) ($providerScope[0] ?? 0);
            $pivotJoin = "INNER JOIN {$pivotTable} ps ON ps.service_id = s.id AND ps.provider_id = {$pid}";
        }

        $bindings = [];
        $providerClause = $this->providerScopeSqlClause($providerScope, $bindings, 'a.provider_id');

        $sql = "
            SELECT
                s.id,
                s.name,
                s.price,
                COUNT(a.id)  AS bookings,
                COALESCE(SUM(CASE WHEN a.status NOT IN ('cancelled', 'no-show', 'noshow') THEN s.price ELSE 0 END), 0) AS revenue,
                ROUND(
                    ((COUNT(CASE WHEN a.start_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY) THEN 1 END) -
                      COUNT(CASE WHEN a.start_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 60 DAY)
                                  AND a.start_at <  DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY) THEN 1 END)) /
                     NULLIF(COUNT(CASE WHEN a.start_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 60 DAY)
                                  AND a.start_at <  DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY) THEN 1 END), 0)
                    ) * 100, 1
                ) AS growth
            FROM {$servicesTable} s
            {$pivotJoin}
            LEFT JOIN {$apptTable} a ON s.id = a.service_id {$providerClause}
            WHERE s.active = 1
            GROUP BY s.id, s.name, s.price
            ORDER BY bookings DESC
            LIMIT {$limit}
        ";

        return $this->db->query($sql, $bindings)->getResultArray();
    }

    // -------------------------------------------------------------------------
    // Dashboard totals
    // -------------------------------------------------------------------------

    /**
     * Total appointment count across all services (all statuses).
     * Canonical replacement for the naive COUNT(*) in ServiceModel::getStats().
     *
     * @param int|int[]|null $providerScope
     * @return int
     */
    public function getTotalBookings(int|array|null $providerScope = null): int
    {
        $apptTable = $this->db->prefixTable('appointments');
        $builder   = $this->db->table($apptTable);

        $this->applyProviderScopeToBuilder($builder, $providerScope);

        return (int) $builder->countAllResults();
    }

    // -------------------------------------------------------------------------
    // Status-level aggregates
    // -------------------------------------------------------------------------

    /**
     * Appointment totals grouped by status.
     *
     * @param int|int[]|null $providerScope
     * @return array<string, int>  ['pending' => n, 'confirmed' => n, ...]
     */
    public function getStatsByStatus(int|array|null $providerScope = null): array
    {
        $apptTable = $this->db->prefixTable('appointments');
        $builder   = $this->db->table($apptTable)
            ->select('status, COUNT(*) AS cnt', false)
            ->groupBy('status');

        $this->applyProviderScopeToBuilder($builder, $providerScope);

        $rows = $builder->get()->getResultArray();

        $out = [
            AppointmentStatus::PENDING   => 0,
            AppointmentStatus::CONFIRMED => 0,
            AppointmentStatus::COMPLETED => 0,
            AppointmentStatus::CANCELLED => 0,
            AppointmentStatus::NO_SHOW   => 0,
        ];

        foreach ($rows as $row) {
            $status = $row['status'];
            if (array_key_exists($status, $out)) {
                $out[$status] = (int) $row['cnt'];
            }
        }

        return $out;
    }

    // -------------------------------------------------------------------------
    // Customer-level stats
    // -------------------------------------------------------------------------

    /**
     * Full appointment statistics for a single customer.
     * Drop-in replacement for CustomerAppointmentService::getStats().
     *
     * @param int $customerId
     * @return array  total, upcoming, completed, cancelled, no_show,
     *                favorite_provider_id, favorite_service_id,
     *                first_appointment, last_appointment
     */
    public function getCustomerStats(int $customerId): array
    {
        $now     = date('Y-m-d H:i:s');
        $newBuilder = fn () => $this->appointments->builder();

        $total = $newBuilder()
            ->where('customer_id', $customerId)
            ->countAllResults();

        $upcoming = $newBuilder()
            ->where('customer_id', $customerId)
            ->where('start_at >=', $now)
            ->whereIn('status', AppointmentStatus::UPCOMING)
            ->countAllResults();

        $completed = $newBuilder()
            ->where('customer_id', $customerId)
            ->where('status', AppointmentStatus::COMPLETED)
            ->countAllResults();

        $cancelled = $newBuilder()
            ->where('customer_id', $customerId)
            ->where('status', AppointmentStatus::CANCELLED)
            ->countAllResults();

        $noShow = $newBuilder()
            ->where('customer_id', $customerId)
            ->where('status', AppointmentStatus::NO_SHOW)
            ->countAllResults();

        $favoriteProvider = $newBuilder()
            ->select('provider_id, COUNT(*) as count')
            ->where('customer_id', $customerId)
            ->groupBy('provider_id')
            ->orderBy('count', 'DESC')
            ->limit(1)
            ->get()
            ->getRowArray();

        $favoriteService = $newBuilder()
            ->select('service_id, COUNT(*) as count')
            ->where('customer_id', $customerId)
            ->groupBy('service_id')
            ->orderBy('count', 'DESC')
            ->limit(1)
            ->get()
            ->getRowArray();

        $firstAppointment = $newBuilder()
            ->select('MIN(start_at) as first_date')
            ->where('customer_id', $customerId)
            ->get()
            ->getRowArray();

        $lastAppointment = $newBuilder()
            ->select('MAX(start_at) as last_date')
            ->where('customer_id', $customerId)
            ->where('start_at <', $now)
            ->get()
            ->getRowArray();

        return [
            'total'               => (int) $total,
            'upcoming'            => (int) $upcoming,
            'completed'           => (int) $completed,
            'cancelled'           => (int) $cancelled,
            'no_show'             => (int) $noShow,
            'favorite_provider_id' => isset($favoriteProvider['provider_id']) ? (int) $favoriteProvider['provider_id'] : null,
            'favorite_service_id'  => isset($favoriteService['service_id'])  ? (int) $favoriteService['service_id']  : null,
            'first_appointment'   => $firstAppointment['first_date'] ?? null,
            'last_appointment'    => $lastAppointment['last_date']   ?? null,
        ];
    }

    /**
     * Apply admin/provider/staff scope to a query builder.
     *
     * null = admin/global, int = provider, int[] = staff-assigned providers.
     */
    private function applyProviderScopeToBuilder(\CodeIgniter\Database\BaseBuilder $builder, int|array|null $providerScope): void
    {
        if ($providerScope === null) {
            return;
        }

        if (is_int($providerScope)) {
            $builder->where('provider_id', $providerScope);
            return;
        }

        $ids = $this->normalizeProviderScopeIds($providerScope);
        if ($ids === []) {
            // Empty staff assignment should return no metrics rows.
            $builder->where('1 = 0', null, false);
            return;
        }

        $builder->whereIn('provider_id', $ids);
    }

    /**
     * Build SQL clause and bindings for provider scope in raw SQL queries.
     *
     * @param array<string, mixed> $bindings
     */
    private function providerScopeSqlClause(int|array|null $providerScope, array &$bindings, string $qualifiedColumn): string
    {
        if ($providerScope === null) {
            return '';
        }

        if (is_int($providerScope)) {
            $bindings['provider_scope_id'] = $providerScope;
            return " AND {$qualifiedColumn} = :provider_scope_id:";
        }

        $ids = $this->normalizeProviderScopeIds($providerScope);
        if ($ids === []) {
            return ' AND 1 = 0';
        }

        $escaped = array_map(fn (int $id): string => $this->db->escape((string) $id), $ids);
        return ' AND ' . $qualifiedColumn . ' IN (' . implode(', ', $escaped) . ')';
    }

    /**
     * @param int[] $providerIds
     * @return int[]
     */
    private function normalizeProviderScopeIds(array $providerIds): array
    {
        $ids = [];
        foreach ($providerIds as $id) {
            if (is_numeric($id) && (int) $id > 0) {
                $ids[] = (int) $id;
            }
        }

        return array_values(array_unique($ids));
    }
}
