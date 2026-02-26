<?php

/**
 * =============================================================================
 * APPOINTMENT QUERY SERVICE
 * =============================================================================
 *
 * @file        app/Services/Appointment/AppointmentQueryService.php
 * @description Handles all appointment data retrieval with JOINs and filters.
 *              Extracted from Api/Appointments::index() to make the controller
 *              a thin orchestration layer.
 *
 * Audit fix: RISK-01 (fat controller) — inline SQL extracted here.
 * Audit fix: RISK-06 (provider scoping) — scope enforcement is centralized here.
 *
 * KEY METHODS:
 * ─────────────────────────────────────────────────────────────────
 * - getForCalendar(filters, pagination)
 *   Single query for the scheduler calendar (date range + provider + service).
 *   Automatically scopes to the requesting user's provider_id if role = provider.
 *
 * - getForRange(startDate, endDate, filters)
 *   Returns appointments between two dates with all related data.
 *
 * - getGroupedByDate(startDate, endDate, filters)
 *   Returns appointments indexed by date for fast calendar rendering.
 *
 * @package     App\Services\Appointment
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

namespace App\Services\Appointment;

use App\Models\AppointmentModel;

class AppointmentQueryService
{
    private const DEFAULT_PROVIDER_COLOR = '#3B82F6';

    private AppointmentModel $model;

    public function __construct(?AppointmentModel $model = null)
    {
        $this->model = $model ?? new AppointmentModel();
    }

    // ─────────────────────────────────────────────────────────────────
    // PUBLIC API
    // ─────────────────────────────────────────────────────────────────

    /**
     * Fetch appointments for calendar display with full JOIN data.
     * Applies role-scoping if a provider userId is provided.
     *
     * @param array $filters {
     *   start?: string Y-m-d,
     *   end?: string Y-m-d,
     *   provider_id?: int,
     *   service_id?: int,
     *   location_id?: int,
     *   status?: string,
     *   sort?: string field:dir,
     *   page?: int,
     *   length?: int,
     *   scope_to_user_id?: int   — if set AND user is provider, restrict to their rows
     *   user_role?: string       — 'admin','provider','staff','customer'
     * }
     * @return array { rows: array, total: int }
     */
    public function getForCalendar(array $filters = []): array
    {
        $builder = $this->buildBaseQuery();

        $builder = $this->applyDateRange($builder, $filters);
        $builder = $this->applyProviderScope($builder, $filters);
        $builder = $this->applyFilters($builder, $filters);
        $builder = $this->applySort($builder, $filters);

        // Count before pagination
        $countBuilder = clone $builder;
        $total = $countBuilder->countAllResults(false);

        // Paginate
        $page   = max(1, (int) ($filters['page'] ?? 1));
        $length = min(1000, max(1, (int) ($filters['length'] ?? 50)));
        $offset = ($page - 1) * $length;

        $rows = $builder->limit($length, $offset)->get()->getResultArray();

        return [
            'rows'  => $rows,
            'total' => (int) $total,
        ];
    }

    /**
     * Fetch appointments in a date range, returned raw (with JOIN data).
     * Used by CalendarRangeService / view services to inject into cells.
     *
     * @param string $startDate Y-m-d
     * @param string $endDate   Y-m-d
     * @param array  $filters   provider_id, service_id, location_id, status
     * @return array Flat array of appointment rows
     */
    public function getForRange(string $startDate, string $endDate, array $filters = []): array
    {
        $builder = $this->buildBaseQuery();
        $builder->where('xs_appointments.start_time >=', $startDate . ' 00:00:00')
                ->where('xs_appointments.start_time <=', $endDate   . ' 23:59:59');

        $builder = $this->applyProviderScope($builder, $filters);
        $builder = $this->applyFilters($builder, $filters);
        $builder->orderBy('xs_appointments.start_time', 'ASC');

        return $builder->get()->getResultArray();
    }

    /**
     * Same as getForRange but returns appointments grouped by date ('Y-m-d' key).
     * Efficient for month/week view injection.
     *
     * @return array<string, array> e.g. ['2026-02-23' => [appointment, ...]]
     */
    public function getGroupedByDate(string $startDate, string $endDate, array $filters = []): array
    {
        $rows    = $this->getForRange($startDate, $endDate, $filters);
        $grouped = [];

        foreach ($rows as $row) {
            $date = substr($row['start_time'], 0, 10); // Extract Y-m-d
            $grouped[$date][] = $row;
        }

        return $grouped;
    }

    /**
     * Same as getGroupedByDate but also groups by provider_id within each date.
     *
     * @return array<string, array<int, array>> e.g. ['2026-02-23' => [3 => [...]]]
     */
    public function getGroupedByDateAndProvider(string $startDate, string $endDate, array $filters = []): array
    {
        $rows    = $this->getForRange($startDate, $endDate, $filters);
        $grouped = [];

        foreach ($rows as $row) {
            $date       = substr($row['start_time'], 0, 10);
            $providerId = (int) $row['provider_id'];
            $grouped[$date][$providerId][] = $row;
        }

        return $grouped;
    }

    // ─────────────────────────────────────────────────────────────────
    // QUERY BUILDER HELPERS
    // ─────────────────────────────────────────────────────────────────

    /**
     * Build the base SELECT with all required JOINs.
     */
    private function buildBaseQuery(): \CodeIgniter\Database\BaseBuilder
    {
        $builder = $this->model->builder();

        $builder->select(
            'xs_appointments.*,
             CONCAT(c.first_name, " ", COALESCE(c.last_name, "")) AS customer_name,
             c.email   AS customer_email,
             c.phone   AS customer_phone,
             s.name          AS service_name,
             s.duration_min  AS service_duration,
             s.price         AS service_price,
             s.buffer_before AS service_buffer_before,
             s.buffer_after  AS service_buffer_after,
             u.name  AS provider_name,
             u.color AS provider_color'
        )
        ->join('xs_customers c', 'c.id = xs_appointments.customer_id', 'left')
        ->join('xs_services s',  's.id = xs_appointments.service_id',  'left')
        ->join('xs_users u',     'u.id = xs_appointments.provider_id', 'left');

        return $builder;
    }

    /**
     * Apply date range filter (ISO 8601 date strings accepted).
     */
    private function applyDateRange($builder, array $filters): \CodeIgniter\Database\BaseBuilder
    {
        $start = $filters['start'] ?? null;
        $end   = $filters['end']   ?? null;

        if ($start || $end) {
            $startDate = $start ? substr($start, 0, 10) : null;
            $endDate   = $end   ? substr($end,   0, 10) : null;

            if ($startDate) {
                $builder->where('xs_appointments.start_time >=', $startDate . ' 00:00:00');
            }
            if ($endDate) {
                $builder->where('xs_appointments.start_time <=', $endDate . ' 23:59:59');
            }
        }

        return $builder;
    }

    /**
     * Enforce provider scoping:
     * - If user_role = 'provider', restrict to scope_to_user_id (their own appointments)
     * - If provider_id filter is explicitly set, honour it (admin override)
     *
     * Fixes RISK-06: provider could previously see all appointments by omitting provider_id.
     */
    private function applyProviderScope($builder, array $filters): \CodeIgniter\Database\BaseBuilder
    {
        $userRole    = $filters['user_role']        ?? null;
        $scopeUserId = $filters['scope_to_user_id'] ?? null;
        $filterPid   = $filters['provider_id']      ?? null;

        if ($userRole === 'provider' && $scopeUserId) {
            // Provider can only see their own appointments
            // An explicit provider_id filter that matches their own ID is allowed;
            // any other provider_id is silently replaced with their own.
            $builder->where('xs_appointments.provider_id', (int) $scopeUserId);
        } elseif ($filterPid) {
            $builder->where('xs_appointments.provider_id', (int) $filterPid);
        }

        return $builder;
    }

    /**
     * Apply optional extra filters (service, location, status).
     */
    private function applyFilters($builder, array $filters): \CodeIgniter\Database\BaseBuilder
    {
        if (!empty($filters['service_id'])) {
            $builder->where('xs_appointments.service_id', (int) $filters['service_id']);
        }

        if (!empty($filters['location_id'])) {
            $builder->where('xs_appointments.location_id', (int) $filters['location_id']);
        }

        if (!empty($filters['status'])) {
            $builder->where('xs_appointments.status', $filters['status']);
        }

        return $builder;
    }

    /**
     * Apply sort (defaults to start_time ASC).
     */
    private function applySort($builder, array $filters): \CodeIgniter\Database\BaseBuilder
    {
        $validFields = ['id', 'start_time', 'end_time', 'provider_id', 'service_id', 'status'];
        $sortParam   = $filters['sort'] ?? 'start_time:asc';

        [$field, $dir] = array_pad(explode(':', $sortParam), 2, 'asc');
        if (!in_array($field, $validFields, true)) {
            $field = 'start_time';
        }
        $dir = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';

        $builder->orderBy('xs_appointments.' . $field, $dir);

        return $builder;
    }
}
