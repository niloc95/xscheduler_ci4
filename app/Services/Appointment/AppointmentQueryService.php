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
use App\Models\ProviderStaffModel;
use App\Services\LocalizationSettingsService;

class AppointmentQueryService
{
    private const DEFAULT_PROVIDER_COLOR = '#3B82F6';

    private AppointmentModel $model;
    private ProviderStaffModel $providerStaffModel;
    private LocalizationSettingsService $localizationService;

    public function __construct(
        ?AppointmentModel $model = null,
        ?ProviderStaffModel $providerStaffModel = null,
        ?LocalizationSettingsService $localizationService = null
    )
    {
        $this->model = $model ?? new AppointmentModel();
        $this->providerStaffModel = $providerStaffModel ?? new ProviderStaffModel();
        $this->localizationService = $localizationService ?? new LocalizationSettingsService();
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
            'page'  => $page,
            'length'=> $length,
        ];
    }

    /**
     * Fetch appointments in a date range, returned raw (with JOIN data).
     * Used by CalendarRangeService / view services to inject into cells.
     *
     * Dates are local (user/business perspective). They are converted to UTC
     * boundaries before querying the DB (which stores times in UTC).
     *
     * @param string $startDate Y-m-d (local)
     * @param string $endDate   Y-m-d (local)
     * @param array  $filters   provider_id, service_id, location_id, status
     * @return array Flat array of appointment rows
     */
    public function getForRange(string $startDate, string $endDate, array $filters = []): array
    {
        // Convert local day boundaries to UTC for DB query using canonical resolver.
        $localTz = $this->localizationService->getTimezone();
        $boundaries = $this->resolveUtcBoundaries($startDate, $endDate, $localTz);

        $builder = $this->buildBaseQuery();
        if (!empty($boundaries['start'])) {
            $builder->where('xs_appointments.start_at >=', $boundaries['start']);
        }
        if (!empty($boundaries['end'])) {
            $builder->where('xs_appointments.start_at <=', $boundaries['end']);
        }

        $builder = $this->applyProviderScope($builder, $filters);
        $builder = $this->applyFilters($builder, $filters);
        $builder->orderBy('xs_appointments.start_at', 'ASC');

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
        $localTz = $this->localizationService->getTimezone();

        foreach ($rows as $row) {
            // Convert UTC start_at → local date for grouping
            $localDate = \App\Services\TimezoneService::toDisplay($row['start_at'], $localTz);
            $date = substr($localDate, 0, 10); // Extract Y-m-d from local
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
        $localTz = (new \App\Services\LocalizationSettingsService())->getTimezone();

        foreach ($rows as $row) {
            $localDate  = \App\Services\TimezoneService::toDisplay($row['start_at'], $localTz);
            $date       = substr($localDate, 0, 10);
            $providerId = (int) $row['provider_id'];
            $grouped[$date][$providerId][] = $row;
        }

        return $grouped;
    }

    /**
     * Fetch a single appointment with joined relation data.
     */
    public function getDetailById(int $appointmentId): ?array
    {
        $appointment = $this->model->getWithRelations($appointmentId);

        return $appointment ?: null;
    }

    /**
     * Count appointments across today/week/month for dashboard-style summaries.
     */
    public function getPeriodCounts(array $filters = []): array
    {
        $providerId = (int) ($filters['provider_id'] ?? 0);
        $serviceId = (int) ($filters['service_id'] ?? 0);

        $now = new \DateTimeImmutable('now');
        $todayStart = $now->setTime(0, 0, 0);
        $todayEnd = $now->setTime(23, 59, 59);

        $dayOfWeek = (int) $now->format('w');
        $weekStart = $todayStart->modify('-' . $dayOfWeek . ' days');
        $weekEnd = $weekStart->modify('+6 days')->setTime(23, 59, 59);

        $monthStart = $now->modify('first day of this month')->setTime(0, 0, 0);
        $monthEnd = $now->modify('last day of this month')->setTime(23, 59, 59);

        return [
            'today' => $this->countInRange($providerId, $serviceId, $todayStart, $todayEnd),
            'week' => $this->countInRange($providerId, $serviceId, $weekStart, $weekEnd),
            'month' => $this->countInRange($providerId, $serviceId, $monthStart, $monthEnd),
        ];
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
        $timezone = $filters['timezone'] ?? \App\Services\TimezoneService::businessTimezone();

        if ($start || $end) {
            $boundaries = $this->resolveUtcBoundaries($start, $end, $timezone);

            if (!empty($boundaries['start'])) {
                $builder->where('xs_appointments.start_at >=', $boundaries['start']);
            }
            if (!empty($boundaries['end'])) {
                $builder->where('xs_appointments.start_at <=', $boundaries['end']);
            }
        }

        return $builder;
    }

    /**
     * Resolve incoming range params into UTC DB boundaries.
     *
     * Accepted input examples:
     * - Y-m-d (interpreted in provided timezone)
     * - ISO datetime with offset/Z (respected as-is)
     */
    private function resolveUtcBoundaries(?string $start, ?string $end, string $timezone): array
    {
        $utc = new \DateTimeZone('UTC');
        $sourceTz = new \DateTimeZone($timezone);

        $toUtc = static function (?string $value, bool $isEnd) use ($utc, $sourceTz): ?string {
            if (!$value) {
                return null;
            }

            $trimmed = trim($value);

            try {
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed)) {
                    $local = new \DateTime($trimmed . ($isEnd ? ' 23:59:59' : ' 00:00:00'), $sourceTz);
                    $local->setTimezone($utc);
                    return $local->format('Y-m-d H:i:s');
                }

                $dt = new \DateTime($trimmed);
                $dt->setTimezone($utc);
                return $dt->format('Y-m-d H:i:s');
            } catch (\Throwable $e) {
                log_message('warning', '[AppointmentQueryService] Invalid date range value: ' . $trimmed);
                return null;
            }
        };

        return [
            'start' => $toUtc($start, false),
            'end'   => $toUtc($end, true),
        ];
    }

    /**
     * Enforce role scoping:
     * - provider: restrict to scope_to_user_id (their own appointments)
     * - staff: restrict to assigned providers (optionally narrowed by provider_id)
     * - admin: honours explicit provider_id filter
     *
     * Fixes RISK-06: provider could previously see all appointments by omitting provider_id.
     */
    private function applyProviderScope($builder, array $filters): \CodeIgniter\Database\BaseBuilder
    {
        $userRole    = $filters['user_role']        ?? null;
        $scopeUserId = $filters['scope_to_user_id'] ?? null;
        $filterPid   = $filters['provider_id']      ?? null;
        $filterPids  = array_values(array_filter(array_map(
            static fn ($id) => (int) $id,
            (array) ($filters['provider_ids'] ?? [])
        )));

        if ($userRole === 'provider' && $scopeUserId) {
            // Provider can only see their own appointments.
            $builder->where('xs_appointments.provider_id', (int) $scopeUserId);
            return $builder;
        }

        if ($userRole === 'staff' && $scopeUserId) {
            $providers = $this->providerStaffModel->getProvidersForStaff((int) $scopeUserId, 'active');
            $providerIds = array_map('intval', array_column($providers, 'id'));

            if ($filterPid || !empty($filterPids)) {
                $requestedProviderIds = $filterPid
                    ? [(int) $filterPid]
                    : $filterPids;
                $allowedProviderIds = array_values(array_intersect($requestedProviderIds, $providerIds));

                if (!empty($allowedProviderIds)) {
                    $builder->whereIn('xs_appointments.provider_id', $allowedProviderIds);
                } else {
                    $builder->where('xs_appointments.provider_id', 0);
                }
                return $builder;
            }

            if (!empty($providerIds)) {
                $builder->whereIn('xs_appointments.provider_id', $providerIds);
            } else {
                $builder->where('xs_appointments.provider_id', 0);
            }

            return $builder;
        }

        if ($filterPid) {
            $builder->where('xs_appointments.provider_id', (int) $filterPid);
        } elseif (!empty($filterPids)) {
            $builder->whereIn('xs_appointments.provider_id', $filterPids);
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
     * Apply sort (defaults to start_at ASC).
     */
    private function applySort($builder, array $filters): \CodeIgniter\Database\BaseBuilder
    {
        $validFields = ['id', 'start_at', 'end_at', 'provider_id', 'service_id', 'status'];
        $sortParam   = $filters['sort'] ?? 'start_at:asc';

        [$field, $dir] = array_pad(explode(':', $sortParam), 2, 'asc');
        if (!in_array($field, $validFields, true)) {
            $field = 'start_at';
        }
        $dir = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';

        $builder->orderBy('xs_appointments.' . $field, $dir);

        return $builder;
    }

    private function countInRange(int $providerId, int $serviceId, \DateTimeImmutable $start, \DateTimeImmutable $end): int
    {
        $builder = $this->model->builder();
        $builder->select('COUNT(*) AS c')
            ->where('start_at >=', $start->format('Y-m-d H:i:s'))
            ->where('start_at <=', $end->format('Y-m-d H:i:s'));

        if ($providerId > 0) {
            $builder->where('provider_id', $providerId);
        }

        if ($serviceId > 0) {
            $builder->where('service_id', $serviceId);
        }

        $row = $builder->get()->getRowArray();

        return (int) ($row['c'] ?? 0);
    }
}
