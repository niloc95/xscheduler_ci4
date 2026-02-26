<?php

/**
 * =============================================================================
 * APPOINTMENT MODEL
 * =============================================================================
 * 
 * @file        app/Models/AppointmentModel.php
 * @description Data model for appointments - the core entity of the scheduling
 *              system. Handles CRUD, statistics, and complex queries.
 * 
 * DATABASE TABLE: xs_appointments
 * -----------------------------------------------------------------------------
 * Columns:
 * - id              : Primary key
 * - user_id         : Booking user (deprecated, use customer_id)
 * - customer_id     : Customer who booked (FK to xs_customers)
 * - service_id      : Service booked (FK to xs_services)
 * - provider_id     : Service provider (FK to xs_users)
 * - location_id     : Location if applicable (FK to xs_locations)
 * - start_at       : Appointment start (datetime)
 * - end_at         : Appointment end (datetime)
 * - status          : pending, confirmed, completed, cancelled, no-show
 * - notes           : Customer/staff notes
 * - hash            : Unique identifier for public URLs
 * - public_token    : Token for customer self-service
 * - reminder_sent   : Whether reminder was sent (0/1)
 * - created_at      : Creation timestamp
 * - updated_at      : Last update timestamp
 * 
 * KEY METHODS:
 * -----------------------------------------------------------------------------
 * - getStats()           : Dashboard statistics
 * - getUpcoming()        : Future appointments list
 * - getByDateRange()     : Appointments within date range
 * - getByProvider()      : Provider's appointments
 * - getByCustomer()      : Customer's appointment history
 * - checkConflicts()     : Check for scheduling conflicts
 * - generateHash()       : Create unique public identifier
 * 
 * MODEL CALLBACKS:
 * -----------------------------------------------------------------------------
 * - beforeInsert: generateHash (creates unique appointment hash)
 * - afterInsert:  invalidateDashboardCache
 * - afterUpdate:  invalidateDashboardCache
 * - afterDelete:  invalidateDashboardCache
 * 
 * @see         app/Controllers/Api/Appointments.php for API layer
 * @see         app/Services/SchedulingService.php for business logic
 * @package     App\Models
 * @extends     BaseModel
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

namespace App\Models;

use App\Models\BaseModel;

use CodeIgniter\Database\BaseBuilder;

class AppointmentModel extends BaseModel
{
    protected $table      = 'xs_appointments';
    protected $primaryKey = 'id';

    // Only valid fields
    protected $allowedFields    = [
        'customer_id',
        'service_id',
        'provider_id',
        'location_id',
        'location_name',
        'location_address',
        'location_contact',
        'appointment_date',
        'appointment_time',
        'start_at',
        'end_at',
        'status',
        'reminder_sent',
        'notes',
        'hash',
        'public_token',
        'public_token_expires_at',
    ];

    protected $beforeInsert = ['generateHash'];
    protected $afterInsert = ['invalidateDashboardCache'];
    protected $afterUpdate = ['invalidateDashboardCache'];
    protected $afterDelete = ['invalidateDashboardCache'];

    protected $validationRules = [
        'customer_id' => 'required|is_natural_no_zero',
        'provider_id' => 'required|is_natural_no_zero',
        'service_id'  => 'required|is_natural_no_zero',
        // Accept either granular date/time or combined start/end timestamps depending on the callers
        'start_at'  => 'permit_empty|valid_date',
        'end_at'    => 'permit_empty|valid_date',
        'appointment_date' => 'permit_empty|valid_date',
        'appointment_time' => 'permit_empty',
        'status'      => 'required|in_list[pending,confirmed,completed,cancelled,no-show]'
    ];

    /**
     * Generate unique hash for new appointment before insert
     */
    protected function generateHash(array $data): array
    {
        if (!isset($data['data']['hash']) || empty($data['data']['hash'])) {
            $encryptionKey = config('Encryption')->key ?? 'default-secret-key';
            $data['data']['hash'] = hash('sha256', 'appointment_' . uniqid('', true) . $encryptionKey . time());
        }
        return $data;
    }

    /**
     * Invalidate dashboard cache after appointment changes
     * 
     * Called automatically after insert, update, or delete operations.
     * Invalidates both admin cache and provider-specific cache.
     * 
     * @param array $data Data containing appointment info
     * @return array Unmodified data
     */
    protected function invalidateDashboardCache(array $data): array
    {
        try {
            // Get provider ID from the data
            $providerId = null;
            
            // For insert/update operations
            if (isset($data['data']['provider_id'])) {
                $providerId = $data['data']['provider_id'];
            }
            // For delete operations, try to get from ID
            elseif (isset($data['id'])) {
                $appointment = $this->find($data['id']);
                if ($appointment) {
                    $providerId = $appointment['provider_id'] ?? null;
                }
            }
            
            // Invalidate admin cache (global)
            cache()->delete('dashboard_metrics_admin');
            
            // Invalidate provider-specific cache
            if ($providerId !== null) {
                cache()->delete("dashboard_metrics_{$providerId}");
            }
            
            // Log cache invalidation in debug mode
            if (ENVIRONMENT === 'development') {
                log_message('debug', "Dashboard cache invalidated for provider: " . ($providerId ?? 'admin'));
            }
        } catch (\Exception $e) {
            // Don't fail the operation if cache invalidation fails
            log_message('error', 'Failed to invalidate dashboard cache: ' . $e->getMessage());
        }
        
        return $data;
    }

    /**
     * Find appointment by hash
     */
    public function findByHash(string $hash): ?array
    {
        $result = $this->where('hash', $hash)->first();
        return $result ?: null;
    }

    /**
     * Get a DateTime in the business-local timezone.
     * Since PHP default TZ is UTC, we must explicitly use business TZ for
     * calendar-aware concepts like "today", "this week", "this month".
     */
    private function localNow(): \DateTime
    {
        return new \DateTime('now', new \DateTimeZone(\App\Services\TimezoneService::businessTimezone()));
    }

    /**
     * Convert a business-local datetime string to UTC for querying start_at/end_at.
     * @param string $localDatetime  e.g. '2025-01-15 00:00:00' in business TZ
     * @return string  UTC datetime string  e.g. '2025-01-14 22:00:00'
     */
    private function toUtc(string $localDatetime): string
    {
        $localTz = new \DateTimeZone(\App\Services\TimezoneService::businessTimezone());
        return (new \DateTime($localDatetime, $localTz))
            ->setTimezone(new \DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s');
    }

    /**
     * Upcoming appointments for provider
     */
    public function upcomingForProvider(int $providerId, int $days = 30): array
    {
        return $this->builder()
            ->select('appointments.*, c.first_name, c.last_name, s.name as service_name')
            ->join('customers c', 'c.id = appointments.customer_id', 'left')
            ->join('services s', 's.id = appointments.service_id', 'left')
            ->where('provider_id', $providerId)
            ->where('start_at >=', date('Y-m-d H:i:s'))
            ->where('start_at <', date('Y-m-d H:i:s', strtotime("+{$days} days")))
            ->orderBy('start_at','ASC')
            ->get()->getResultArray();
    }

    /**
     * Book appointment helper
     */
    public function book(array $payload): int|false
    {
        if (empty($payload['status'])) {
            $payload['status'] = 'pending';
        }
        // user_id column was removed in the customers-split migration;
        // ensure it never gets inserted accidentally.
        unset($payload['user_id']);

        if (!$this->insert($payload, false)) {
            return false;
        }
        return (int)$this->getInsertID();
    }

    /**
     * Dashboard / analytics helpers
     */
    public const SIMPLE_STATUSES = ['pending', 'confirmed', 'completed', 'cancelled', 'no-show'];

    /**
     * Count appointments by semantic status (supports "upcoming" virtual status)
     */
    public function countByStatus(string $status, array $context = [], ?string $baseStatusFilter = null): int
    {
        $builder = $this->builder();
        $this->applyContextScope($builder, $context);
        if ($baseStatusFilter) {
            $this->applyStatusFilter($builder, $baseStatusFilter);
        }
        $this->applyStatusFilter($builder, $status);
        return (int) $builder->countAllResults();
    }

    /**
     * Return summarized stats for dashboards.
     */
    public function getStats(array $context = [], ?string $statusFilter = null): array
    {
        $statusFilter = $this->normalizeStatusFilter($statusFilter);
        $now        = date('Y-m-d H:i:s'); // UTC now — correct for absolute comparisons
        $local      = $this->localNow();
        $todayLocal = $local->format('Y-m-d');
        $todayStart = $this->toUtc($todayLocal . ' 00:00:00');
        $todayEnd   = $this->toUtc($todayLocal . ' 23:59:59');
        $weekStart  = $this->toUtc((clone $local)->modify('monday this week')->format('Y-m-d') . ' 00:00:00');
        $weekEnd    = $this->toUtc((clone $local)->modify('sunday this week')->format('Y-m-d') . ' 23:59:59');
        $monthStart = $this->toUtc($local->format('Y-m-01') . ' 00:00:00');
        $monthEnd   = $this->toUtc($local->format('Y-m-t') . ' 23:59:59');

        return [
            'total'     => (int) $this->applyAllScopes($this->builder(), $context, $statusFilter)->countAllResults(),
            'today'     => (int) $this->applyAllScopes($this->builder()
                ->where('start_at >=', $todayStart)
                ->where('start_at <=', $todayEnd), $context, $statusFilter)->countAllResults(),
            'upcoming'  => $this->countByStatus('upcoming', $context, $statusFilter),
            'pending'   => $this->countByStatus('pending', $context, $statusFilter),
            'completed' => $this->countByStatus('completed', $context, $statusFilter),
            'cancelled' => $this->countByStatus('cancelled', $context, $statusFilter),
            'this_week' => (int) $this->applyAllScopes($this->builder()
                ->where('start_at >=', $weekStart)
                ->where('start_at <=', $weekEnd), $context, $statusFilter)->countAllResults(),
            'this_month'=> (int) $this->applyAllScopes($this->builder()
                ->where('start_at >=', $monthStart)
                ->where('start_at <=', $monthEnd), $context, $statusFilter)->countAllResults(),
        ];
    }

    /**
     * Get stats for a specific date range.
     * Used for dynamic dashboard stats based on Day/Week/Month view.
     * 
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @param int|null $providerId Optional provider filter
     * @return array Stats within the date range
     */
    public function getStatsForDateRange(string $startDate, string $endDate, ?int $providerId = null): array
    {
        $startDateTime = $this->toUtc($startDate . ' 00:00:00');
        $endDateTime = $this->toUtc($endDate . ' 23:59:59');
        $table = $this->table;
        
        // Build base query with date range
        $baseQuery = function() use ($startDateTime, $endDateTime, $providerId, $table) {
            $builder = $this->db->table($table)
                ->where("{$table}.start_at >=", $startDateTime)
                ->where("{$table}.start_at <=", $endDateTime);
            
            if ($providerId) {
                $builder->where("{$table}.provider_id", $providerId);
            }
            
            return $builder;
        };
        
        // Count by status within date range
        $pending = (int) $baseQuery()->where("{$table}.status", 'pending')->countAllResults(false);
        $confirmed = (int) $baseQuery()->where("{$table}.status", 'confirmed')->countAllResults(false);
        $completed = (int) $baseQuery()->where("{$table}.status", 'completed')->countAllResults(false);
        $cancelled = (int) $baseQuery()->where("{$table}.status", 'cancelled')->countAllResults(false);
        $noshow = (int) $baseQuery()->where("{$table}.status", 'no-show')->countAllResults(false);
        
        // Calculate totals
        $upcoming = $pending + $confirmed;
        $total = $pending + $confirmed + $completed + $cancelled + $noshow;
        
        return [
            'pending' => $pending,
            'confirmed' => $confirmed,
            'completed' => $completed,
            'cancelled' => $cancelled,
            'noshow' => $noshow,
            'upcoming' => $upcoming,
            'total' => $total,
        ];
    }

    /**
     * Get list of providers who have appointments in a date range.
     * Used to filter provider dropdown to only show relevant providers.
     * 
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @return array List of providers with id, name, color
     */
    public function getProvidersWithAppointments(string $startDate, string $endDate): array
    {
        $startDateTime = $this->toUtc($startDate . ' 00:00:00');
        $endDateTime = $this->toUtc($endDate . ' 23:59:59');
        
        $table = $this->table;
        $result = $this->builder()
            ->distinct()
            ->select("{$table}.provider_id as id, u.name, u.color")
            ->join('xs_users as u', "u.id = {$table}.provider_id", 'left')
            ->where("{$table}.start_at >=", $startDateTime)
            ->where("{$table}.start_at <=", $endDateTime)
            ->orderBy('u.name', 'ASC')
            ->get()
            ->getResultArray();
        
        return $result ?: [];
    }

    /**
     * Get appointment with all related data (customer, service, provider)
     * Centralizes JOIN query to eliminate duplication across controllers
     * 
     * @param int $id Appointment ID
     * @return array|null Appointment with relations or null if not found
     */
    public function getWithRelations(int $id): ?array
    {
        $result = $this->builder()
            ->select('xs_appointments.*, 
                     CONCAT(c.first_name, " ", COALESCE(c.last_name, "")) as customer_name,
                     c.email as customer_email,
                     c.phone as customer_phone,
                     s.name as service_name,
                     s.duration_min as service_duration,
                     s.price as service_price,
                     u.name as provider_name,
                     u.color as provider_color')
            ->join('xs_customers as c', 'c.id = xs_appointments.customer_id', 'left')
            ->join('xs_services as s', 's.id = xs_appointments.service_id', 'left')
            ->join('xs_users as u', 'u.id = xs_appointments.provider_id', 'left')
            ->where('xs_appointments.id', $id)
            ->get()
            ->getRowArray();
        
        return $result ?: null;
    }

    /**
     * Get multiple appointments with relations, with optional filtering
     * Supports pagination, date ranges, and status filtering
     * 
     * @param array $filters Optional filters (start, end, provider_id, service_id, status)
     * @param int|null $limit Maximum results to return
     * @param int $offset Offset for pagination
     * @return array Appointments with relations
     */
    public function getManyWithRelations(array $filters = [], ?int $limit = null, int $offset = 0): array
    {
        $builder = $this->builder()
            ->select('xs_appointments.*, 
                     CONCAT(c.first_name, " ", COALESCE(c.last_name, "")) as customer_name,
                     c.email as customer_email,
                     c.phone as customer_phone,
                     s.name as service_name,
                     s.duration_min as service_duration,
                     s.price as service_price,
                     u.name as provider_name,
                     u.color as provider_color')
            ->join('xs_customers as c', 'c.id = xs_appointments.customer_id', 'left')
            ->join('xs_services as s', 's.id = xs_appointments.service_id', 'left')
            ->join('xs_users as u', 'u.id = xs_appointments.provider_id', 'left');

        // Apply filters
        if (!empty($filters['start'])) {
            $builder->where('xs_appointments.start_at >=', $filters['start']);
        }
        if (!empty($filters['end'])) {
            $builder->where('xs_appointments.start_at <=', $filters['end']);
        }
        if (!empty($filters['provider_id'])) {
            $builder->where('xs_appointments.provider_id', (int)$filters['provider_id']);
        }
        if (!empty($filters['service_id'])) {
            $builder->where('xs_appointments.service_id', (int)$filters['service_id']);
        }
        if (!empty($filters['status'])) {
            $builder->where('xs_appointments.status', $filters['status']);
        }

        // Apply pagination
        if ($limit !== null) {
            $builder->limit($limit, $offset);
        }

        $builder->orderBy('xs_appointments.start_at', 'ASC');

        return $builder->get()->getResultArray();
    }

    /**
     * Fetch appointments for dashboard widgets with optional status filter.
     */
    public function getDashboardAppointments(?string $status = null, array $context = [], int $limit = 50): array
    {
        $builder = $this->builder()
            ->select('xs_appointments.*, 
                     CONCAT(c.first_name, " ", COALESCE(c.last_name, "")) as customer_name,
                     c.email as customer_email,
                     c.phone as customer_phone,
                     s.name as service_name,
                     u.name as provider_name,
                     u.color as provider_color')
            ->join('xs_customers as c', 'c.id = xs_appointments.customer_id', 'left')
            ->join('xs_services as s', 's.id = xs_appointments.service_id', 'left')
            ->join('xs_users as u', 'u.id = xs_appointments.provider_id', 'left')
            ->orderBy('xs_appointments.start_at', 'ASC')
            ->limit($limit);

        $this->applyStatusFilter($builder, $status);
        $this->applyContextScope($builder, $context);

        return $builder->get()->getResultArray();
    }

    /**
     * Normalize and apply status filter to a builder instance.
     */
    public function applyStatusFilter(BaseBuilder $builder, ?string $status): BaseBuilder
    {
        $normalized = $this->normalizeStatusFilter($status);

        if ($normalized === null) {
            return $builder;
        }

        if ($normalized === 'upcoming') {
            $builder->where('xs_appointments.start_at >=', date('Y-m-d H:i:s'))
                    ->whereIn('xs_appointments.status', ['pending', 'confirmed']);
            return $builder;
        }

        return $builder->where('xs_appointments.status', $normalized);
    }

    /**
     * Apply context scope (provider/customer) to builder or counts.
     */
    private function applyContextScope(BaseBuilder $builder, array $context): BaseBuilder
    {
        if (array_key_exists('provider_id', $context)) {
            $providerConstraint = $context['provider_id'];
            if (is_array($providerConstraint)) {
                $providerIds = array_values(array_filter(array_map('intval', $providerConstraint)));
                if (!empty($providerIds)) {
                    $builder->whereIn('xs_appointments.provider_id', $providerIds);
                }
            } elseif (!empty($providerConstraint)) {
                $builder->where('xs_appointments.provider_id', (int) $providerConstraint);
            }
        }

        if (array_key_exists('customer_id', $context)) {
            $customerConstraint = $context['customer_id'];
            if (is_array($customerConstraint)) {
                $customerIds = array_values(array_filter(array_map('intval', $customerConstraint)));
                if (!empty($customerIds)) {
                    $builder->whereIn('xs_appointments.customer_id', $customerIds);
                }
            } elseif (!empty($customerConstraint)) {
                $builder->where('xs_appointments.customer_id', (int) $customerConstraint);
            }
        }

        return $builder;
    }

    private function applyAllScopes(BaseBuilder $builder, array $context, ?string $statusFilter = null): BaseBuilder
    {
        $this->applyContextScope($builder, $context);
        if ($statusFilter) {
            $this->applyStatusFilter($builder, $statusFilter);
        }

        return $builder;
    }

    public function normalizeStatusFilter(?string $status): ?string
    {
        if (!$status) {
            return null;
        }

        $status = strtolower($status);

        if ($status === 'upcoming') {
            return 'upcoming';
        }

        return in_array($status, self::SIMPLE_STATUSES, true) ? $status : null;
    }

    /**
     * Calculate month-over-month trend for appointments
     */
    public function getTrend(): array
    {
        // Current month appointments (local TZ boundaries → UTC)
        $local = $this->localNow();
        $currentMonthStart = $this->toUtc($local->format('Y-m-01') . ' 00:00:00');
        $currentMonthEnd = $this->toUtc($local->format('Y-m-t') . ' 23:59:59');
        $currentCount = $this->where('start_at >=', $currentMonthStart)
                             ->where('start_at <=', $currentMonthEnd)
                             ->countAllResults(false);

        // Previous month appointments (local TZ boundaries → UTC)
        $prevLocal = (clone $local)->modify('first day of last month');
        $prevMonthStart = $this->toUtc($prevLocal->format('Y-m-01') . ' 00:00:00');
        $prevMonthEnd = $this->toUtc($prevLocal->format('Y-m-t') . ' 23:59:59');
        $prevCount = $this->where('start_at >=', $prevMonthStart)
                          ->where('start_at <=', $prevMonthEnd)
                          ->countAllResults(false);

        return $this->calculateTrendPercentage($currentCount, $prevCount);
    }

    /**
     * Calculate trend for pending/today's appointments
     */
    public function getPendingTrend(): array
    {
        // Today's pending (local TZ boundaries → UTC)
        $local = $this->localNow();
        $todayLocal = $local->format('Y-m-d');
        $todayStart = $this->toUtc($todayLocal . ' 00:00:00');
        $todayEnd = $this->toUtc($todayLocal . ' 23:59:59');
        $todayCount = $this->where('start_at >=', $todayStart)
                           ->where('start_at <=', $todayEnd)
                           ->countAllResults(false);

        // Yesterday's count (local TZ boundaries → UTC)
        $yesterday = (clone $local)->modify('-1 day')->format('Y-m-d');
        $yesterdayStart = $this->toUtc($yesterday . ' 00:00:00');
        $yesterdayEnd = $this->toUtc($yesterday . ' 23:59:59');
        $yesterdayCount = $this->where('start_at >=', $yesterdayStart)
                               ->where('start_at <=', $yesterdayEnd)
                               ->countAllResults(false);

        return $this->calculateTrendPercentage($todayCount, $yesterdayCount);
    }

    /**
     * Calculate revenue trend month-over-month
     * Uses getRealRevenue() for actual service prices
     */
    public function getRevenueTrend(): array
    {
        $currentRevenue = (int)$this->getRealRevenue('month');
        $prevRevenue = (int)$this->getRealRevenue('last_month');

        return $this->calculateTrendPercentage($currentRevenue, $prevRevenue);
    }

    /**
     * Helper to calculate trend percentage
     */
    protected function calculateTrendPercentage(int $current, int $previous): array
    {
        if ($previous === 0) {
            return [
                'percentage' => $current > 0 ? 100 : 0,
                'direction' => $current > 0 ? 'up' : 'neutral',
                'current' => $current,
                'previous' => $previous
            ];
        }

        $change = (($current - $previous) / $previous) * 100;
        return [
            'percentage' => round(abs($change), 1),
            'direction' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'neutral'),
            'current' => $current,
            'previous' => $previous
        ];
    }

    /**
     * Recent appointments
     */
    public function getRecentAppointments(int $limit = 10): array
    {
        return $this->orderBy('created_at', 'DESC')
                    ->limit($limit)
                    ->find();
    }

    /**
     * Recent activity for dashboard - includes service and customer names
     */
    public function getRecentActivity(int $limit = 5): array
    {
        $db = \Config\Database::connect();
        $tableName = $this->table;
        
        $query = $db->query("
            SELECT 
                a.id,
                a.customer_id,
                a.service_id,
                a.status,
                a.updated_at,
                COALESCE(CONCAT(c.first_name, ' ', c.last_name), 'Unknown Customer') as customer_name,
                COALESCE(s.name, 'Unknown Service') as service_name
            FROM {$tableName} a
            LEFT JOIN xs_customers c ON a.customer_id = c.id
            LEFT JOIN xs_services s ON a.service_id = s.id
            ORDER BY a.updated_at DESC
            LIMIT {$limit}
        ");
        
        return $query->getResultArray();
    }

    /**
     * Chart data helpers
     */
    /**
     * Get chart data for appointment counts by period
     * @deprecated Use getAppointmentGrowth($period) instead - it's more comprehensive
     */
    public function getChartData(string $period = 'week'): array
    {
        // DEPRECATED: Use getAppointmentGrowth() for new code
        // Note: 'month' in old method showed 4 weeks, getAppointmentGrowth('month') does same
        return $this->getAppointmentGrowth($period);
    }

    /**
     * CONSOLIDATED: Get appointment status distribution
     * 
     * This is the single source of truth for status distribution data.
     * Returns counts, labels, and colors for all statuses.
     * 
     * @param string $format Output format: 'full' (default), 'chart', 'simple'
     *   - 'full': Returns ['statuses' => [...], 'labels' => [...], 'data' => [...], 'colors' => [...]]
     *   - 'chart': Returns ['labels' => [...], 'data' => [...], 'colors' => [...]] (for pie charts)
     *   - 'simple': Returns ['status' => count, ...] (simple key-value pairs)
     * @return array Status distribution in requested format
     */
    public function getStatusCounts(string $format = 'full'): array
    {
        $db = \Config\Database::connect();
        $tableName = $this->table;
        
        $query = $db->query("
            SELECT 
                CASE 
                    WHEN status = '' OR status IS NULL THEN 'unknown'
                    ELSE status
                END as status,
                COUNT(*) as count
            FROM {$tableName}
            GROUP BY status
            ORDER BY count DESC
        ");
        
        $results = $query->getResultArray();
        
        // Standard status colors
        $statusColors = [
            'confirmed' => '#34a853',  // Green
            'pending'   => '#fbbc04',  // Yellow
            'completed' => '#1a73e8',  // Blue
            'cancelled' => '#ea4335',  // Red
            'no-show'   => '#9aa0a6',  // Gray
            'unknown'   => '#5f6368'   // Dark gray
        ];
        
        // Build unified data structure
        $statuses = [];
        $labels = [];
        $data = [];
        $colors = [];
        
        foreach ($results as $row) {
            $status = $row['status'];
            $count = (int)$row['count'];
            $color = $statusColors[$status] ?? '#5f6368';
            
            $statuses[$status] = [
                'count' => $count,
                'color' => $color,
                'label' => ucfirst($status)
            ];
            $labels[] = ucfirst($status);
            $data[] = $count;
            $colors[] = $color;
        }
        
        // Handle empty results
        if (empty($labels)) {
            $labels = ['No Data'];
            $data = [0];
            $colors = ['#9aa0a6'];
        }
        
        // Return in requested format
        switch ($format) {
            case 'simple':
                $simple = [];
                foreach ($statuses as $status => $info) {
                    $simple[$status] = $info['count'];
                }
                return $simple;
                
            case 'chart':
                return ['labels' => $labels, 'data' => $data, 'colors' => $colors];
                
            case 'full':
            default:
                return [
                    'statuses' => $statuses,
                    'labels' => $labels,
                    'data' => $data,
                    'colors' => $colors
                ];
        }
    }

    /**
     * Status distribution for pie chart
     * @deprecated Use getStatusCounts('chart') instead
     */
    public function getStatusDistribution(): array
    {
        // DEPRECATED: Use getStatusCounts('chart') for new code
        $result = $this->getStatusCounts('chart');
        // Return without colors for backward compatibility
        return ['labels' => $result['labels'], 'data' => $result['data']];
    }

    /**
     * Calculate revenue from completed appointments
     * 
     * @deprecated Use getRealRevenue() instead - this method uses placeholder prices
     * @param string $period 'month', 'week', or 'today'
     * @return int Estimated revenue (placeholder calculation)
     */
    public function getRevenue(string $period = 'month'): int
    {
        // DEPRECATED: This uses placeholder pricing ($50 per appointment)
        // Use getRealRevenue() for actual service prices
        $completed = $this->where('status', 'completed');
        $local = $this->localNow();
        if ($period === 'month') {
            $completed->where('start_at >=', $this->toUtc($local->format('Y-m-01') . ' 00:00:00'))
                      ->where('start_at <=', $this->toUtc($local->format('Y-m-t') . ' 23:59:59'));
        } elseif ($period === 'week') {
            $completed->where('start_at >=', $this->toUtc((clone $local)->modify('monday this week')->format('Y-m-d') . ' 00:00:00'))
                      ->where('start_at <=', $this->toUtc((clone $local)->modify('sunday this week')->format('Y-m-d') . ' 23:59:59'));
        } elseif ($period === 'today') {
            $completed->where('start_at >=', $this->toUtc($local->format('Y-m-d') . ' 00:00:00'))
                      ->where('start_at <=', $this->toUtc($local->format('Y-m-d') . ' 23:59:59'));
        }
        $count = $completed->countAllResults();
        return $count * 50; // DEPRECATED: placeholder average revenue
    }

    /**
     * Get real revenue by joining with services table for actual prices
     * This is the preferred method for revenue calculations.
     * 
     * @param string $period 'month', 'week', 'today', or 'last_month'
     * @return float Actual revenue from service prices
     */
    public function getRealRevenue(string $period = 'month'): float
    {
        $db = \Config\Database::connect();
        $tableName = $this->table;
        
        $local = $this->localNow();
        $periodCondition = '';
        if ($period === 'month') {
            $periodCondition = "AND a.start_at >= '" . $this->toUtc($local->format('Y-m-01') . ' 00:00:00') . "' AND a.start_at <= '" . $this->toUtc($local->format('Y-m-t') . ' 23:59:59') . "'";
        } elseif ($period === 'week') {
            $periodCondition = "AND a.start_at >= '" . $this->toUtc((clone $local)->modify('monday this week')->format('Y-m-d') . ' 00:00:00') . "' AND a.start_at <= '" . $this->toUtc((clone $local)->modify('sunday this week')->format('Y-m-d') . ' 23:59:59') . "'";
        } elseif ($period === 'today') {
            $periodCondition = "AND a.start_at >= '" . $this->toUtc($local->format('Y-m-d') . ' 00:00:00') . "' AND a.start_at <= '" . $this->toUtc($local->format('Y-m-d') . ' 23:59:59') . "'";
        } elseif ($period === 'last_month') {
            $prevLocal = (clone $local)->modify('first day of last month');
            $periodCondition = "AND a.start_at >= '" . $this->toUtc($prevLocal->format('Y-m-01') . ' 00:00:00') . "' AND a.start_at <= '" . $this->toUtc($prevLocal->format('Y-m-t') . ' 23:59:59') . "'";
        }
        
        $query = $db->query("
            SELECT COALESCE(SUM(s.price), 0) as total_revenue
            FROM {$tableName} a
            LEFT JOIN xs_services s ON a.service_id = s.id
            WHERE a.status = 'completed' {$periodCondition}
        ");
        
        $result = $query->getRow();
        return (float)($result->total_revenue ?? 0);
    }

    /**
     * Get daily revenue data for charts
     */
    public function getDailyRevenue(int $days = 30): array
    {
        $db = \Config\Database::connect();
        $tableName = $this->table;
        $data = [];
        
        $local = $this->localNow();
        for ($i = $days - 1; $i >= 0; $i--) {
            $dayLocal = (clone $local)->modify("-{$i} days");
            $date = $dayLocal->format('Y-m-d');
            $dayStart = $this->toUtc($date . ' 00:00:00');
            $dayEnd = $this->toUtc($date . ' 23:59:59');
            
            $query = $db->query("
                SELECT COALESCE(SUM(s.price), 0) as revenue
                FROM {$tableName} a
                LEFT JOIN xs_services s ON a.service_id = s.id
                WHERE a.status = 'completed'
                AND a.start_at >= '{$dayStart}'
                AND a.start_at <= '{$dayEnd}'
            ");
            
            $result = $query->getRow();
            $data[] = [
                'date' => $date,
                'revenue' => (float)($result->revenue ?? 0)
            ];
        }
        
        return $data;
    }

    /**
     * Get monthly revenue data for charts
     */
    public function getMonthlyRevenue(int $months = 12): array
    {
        $db = \Config\Database::connect();
        $tableName = $this->table;
        $data = [];
        
        $local = $this->localNow();
        for ($i = $months - 1; $i >= 0; $i--) {
            $monthLocal = (clone $local)->modify("-{$i} months");
            $monthStart = $this->toUtc($monthLocal->format('Y-m-01') . ' 00:00:00');
            $monthEnd = $this->toUtc($monthLocal->format('Y-m-t') . ' 23:59:59');
            $monthLabel = $monthLocal->format('M');
            
            $query = $db->query("
                SELECT COALESCE(SUM(s.price), 0) as revenue
                FROM {$tableName} a
                LEFT JOIN xs_services s ON a.service_id = s.id
                WHERE a.status = 'completed'
                AND a.start_at >= '{$monthStart}'
                AND a.start_at <= '{$monthEnd}'
            ");
            
            $result = $query->getRow();
            $data[] = [
                'month' => $monthLabel,
                'revenue' => (float)($result->revenue ?? 0)
            ];
        }
        
        return $data;
    }

    /**
     * Get appointments grouped by status
     * @deprecated Use getStatusCounts('simple') instead
     */
    public function getByStatus(): array
    {
        // DEPRECATED: Use getStatusCounts('simple') for new code
        return $this->getStatusCounts('simple');
    }

    /**
     * Get appointments by service with revenue
     */
    public function getByService(int $limit = 10): array
    {
        $db = \Config\Database::connect();
        $tableName = $this->table;
        
        $query = $db->query("
            SELECT 
                s.name as service,
                COUNT(a.id) as count,
                COALESCE(SUM(CASE WHEN a.status = 'completed' THEN s.price ELSE 0 END), 0) as revenue
            FROM {$tableName} a
            LEFT JOIN xs_services s ON a.service_id = s.id
            WHERE s.name IS NOT NULL
            GROUP BY s.id, s.name
            ORDER BY count DESC
            LIMIT {$limit}
        ");
        
        return $query->getResultArray();
    }

    /**
     * Get appointments by time slot
     */
    public function getByTimeSlot(): array
    {
        $db = \Config\Database::connect();
        $tableName = $this->table;
        
        $localTz = \App\Services\TimezoneService::businessTimezone();
        $offset = (new \DateTime('now', new \DateTimeZone($localTz)))->format('P'); // e.g. '+02:00'
        $query = $db->query("
            SELECT 
                DATE_FORMAT(CONVERT_TZ(start_at, '+00:00', '{$offset}'), '%l:00 %p') as time_slot,
                HOUR(CONVERT_TZ(start_at, '+00:00', '{$offset}')) as hour,
                COUNT(*) as count
            FROM {$tableName}
            GROUP BY hour, time_slot
            ORDER BY hour
        ");
        
        $results = $query->getResultArray();
        $formatted = [];
        
        foreach ($results as $row) {
            $formatted[$row['time_slot']] = (int)$row['count'];
        }
        
        return $formatted;
    }

    /**
     * Get average booking value
     */
    public function getAverageBookingValue(): float
    {
        $db = \Config\Database::connect();
        $tableName = $this->table;
        
        $query = $db->query("
            SELECT AVG(s.price) as avg_value
            FROM {$tableName} a
            LEFT JOIN xs_services s ON a.service_id = s.id
            WHERE a.status = 'completed' AND s.price > 0
        ");
        
        $result = $query->getRow();
        return (float)($result->avg_value ?? 0);
    }

    /**
     * Get appointment counts by status for analytics
     * 
     * @param array $options Options: 'format' => 'simple' returns just status=>count
     * @return array Status counts
     */
    public function getStatusStats(array $options = []): array
    {
        $builder = $this->builder();
        $results = $builder->select('status, COUNT(*) as count')
            ->groupBy('status')
            ->get()
            ->getResultArray();
        
        // Convert to associative array
        $statusCounts = [];
        foreach ($results as $row) {
            $statusCounts[$row['status']] = (int)$row['count'];
        }
        
        // Ensure all statuses are present even if count is 0
        $allStatuses = ['pending', 'confirmed', 'completed', 'cancelled', 'no-show'];
        foreach ($allStatuses as $status) {
            if (!isset($statusCounts[$status])) {
                $statusCounts[$status] = 0;
            }
        }
        
        if (isset($options['format']) && $options['format'] === 'simple') {
            return $statusCounts;
        }
        
        // Return with additional metadata
        $total = array_sum($statusCounts);
        return [
            'counts' => $statusCounts,
            'total' => $total,
            'percentages' => array_map(function($count) use ($total) {
                return $total > 0 ? round(($count / $total) * 100, 1) : 0;
            }, $statusCounts)
        ];
    }

    /**
     * Get completion rate
     */
    public function getCompletionRate(): float
    {
        $total = $this->countAllResults(false);
        if ($total === 0) return 0;
        
        $completed = $this->where('status', 'completed')->countAllResults(false);
        return round(($completed / $total) * 100, 1);
    }

    /**
     * Get appointment growth data based on period (rolling window centered on today)
     * Shows both recent past and upcoming appointments for scheduling context
     * @param string $period - 'day', 'week', 'month', 'year'
     */
    public function getAppointmentGrowth(string $period = 'month'): array
    {
        $labels = [];
        $data = [];
        
        switch ($period) {
            case 'day':
                // Today: Show hours from 6am to 9pm (business hours focus)
                $local = $this->localNow();
                $todayLocal = $local->format('Y-m-d');
                for ($hour = 6; $hour <= 21; $hour++) {
                    $hourStart = $this->toUtc($todayLocal . ' ' . sprintf('%02d:00:00', $hour));
                    $hourEnd = $this->toUtc($todayLocal . ' ' . sprintf('%02d:59:59', $hour));
                    
                    $count = $this->where('start_at >=', $hourStart)
                                  ->where('start_at <=', $hourEnd)
                                  ->countAllResults(false);
                    
                    $labels[] = sprintf('%d%s', $hour > 12 ? $hour - 12 : ($hour ?: 12), $hour >= 12 ? 'pm' : 'am');
                    $data[] = $count;
                }
                break;
                
            case 'week':
                // Current week: Monday to Sunday
                $local = $this->localNow();
                $mondayLocal = (clone $local)->modify('monday this week');
                $todayLocal = $local->format('Y-m-d');
                for ($i = 0; $i < 7; $i++) {
                    $dayLocal = (clone $mondayLocal)->modify("+{$i} days");
                    $dayDate = $dayLocal->format('Y-m-d');
                    $dayStart = $this->toUtc($dayDate . ' 00:00:00');
                    $dayEnd = $this->toUtc($dayDate . ' 23:59:59');
                    
                    $count = $this->where('start_at >=', $dayStart)
                                  ->where('start_at <=', $dayEnd)
                                  ->countAllResults(false);
                    
                    $dayLabel = $dayLocal->format('D');
                    // Mark today
                    if ($dayDate === $todayLocal) {
                        $dayLabel .= '*';
                    }
                    $labels[] = $dayLabel;
                    $data[] = $count;
                }
                break;
                
            case 'year':
                // 12 months: 6 past + current + 5 future
                $local = $this->localNow();
                $currentYm = $local->format('Y-m');
                for ($i = -6; $i <= 5; $i++) {
                    $monthLocal = (clone $local)->modify("{$i} months");
                    $monthStart = $this->toUtc($monthLocal->format('Y-m-01') . ' 00:00:00');
                    $monthEnd = $this->toUtc($monthLocal->format('Y-m-t') . ' 23:59:59');
                    
                    $count = $this->where('start_at >=', $monthStart)
                                  ->where('start_at <=', $monthEnd)
                                  ->countAllResults(false);
                    
                    $monthLabel = $monthLocal->format('M');
                    // Mark current month
                    if ($monthLocal->format('Y-m') === $currentYm) {
                        $monthLabel .= '*';
                    }
                    $labels[] = $monthLabel;
                    $data[] = $count;
                }
                break;
                
            case 'month':
            default:
                // 4 weeks: 2 past + current + 1 future
                $local = $this->localNow();
                
                for ($i = -2; $i <= 1; $i++) {
                    $mondayExpr = "monday " . ($i < 0 ? "{$i} weeks" : ($i == 0 ? "this week" : "+{$i} weeks"));
                    $sundayExpr = "sunday " . ($i < 0 ? "{$i} weeks" : ($i == 0 ? "this week" : "+{$i} weeks"));
                    $weekStart = $this->toUtc((clone $local)->modify($mondayExpr)->format('Y-m-d') . ' 00:00:00');
                    $weekEnd = $this->toUtc((clone $local)->modify($sundayExpr)->format('Y-m-d') . ' 23:59:59');
                    
                    $count = $this->where('start_at >=', $weekStart)
                                  ->where('start_at <=', $weekEnd)
                                  ->countAllResults(false);
                    
                    $weekNum = $i + 3; // Week 1, 2, 3, 4
                    $weekLabel = 'Week ' . $weekNum;
                    if ($i == 0) {
                        $weekLabel .= '*'; // Mark current week
                    }
                    $labels[] = $weekLabel;
                    $data[] = $count;
                }
                break;
        }
        
        return ['labels' => $labels, 'data' => $data];
    }

    /**
     * Get services by provider for a specific time period (rolling window)
     * @param string $period - 'day', 'week', 'month', 'year'
     */
    public function getProviderServicesByPeriod(string $period = 'month'): array
    {
        $db = \Config\Database::connect();
        $tableName = $this->table;
        
        // Calculate date range based on period (rolling window centered on today)
        $local = $this->localNow();
        switch ($period) {
            case 'day':
                $startDate = $this->toUtc($local->format('Y-m-d') . ' 00:00:00');
                $endDate = $this->toUtc($local->format('Y-m-d') . ' 23:59:59');
                break;
            case 'week':
                $startDate = $this->toUtc((clone $local)->modify('monday this week')->format('Y-m-d') . ' 00:00:00');
                $endDate = $this->toUtc((clone $local)->modify('sunday this week')->format('Y-m-d') . ' 23:59:59');
                break;
            case 'year':
                $startDate = $this->toUtc((clone $local)->modify('-6 months')->format('Y-m-d') . ' 00:00:00');
                $endDate = $this->toUtc((clone $local)->modify('+5 months')->format('Y-m-d') . ' 23:59:59');
                break;
            case 'month':
            default:
                $startDate = $this->toUtc((clone $local)->modify('-2 weeks')->format('Y-m-d') . ' 00:00:00');
                $endDate = $this->toUtc((clone $local)->modify('+1 week')->format('Y-m-d') . ' 23:59:59');
                break;
        }
        
        $query = $db->query("
            SELECT 
                u.name as provider_name,
                COUNT(a.id) as total_appointments
            FROM {$tableName} a
            LEFT JOIN xs_users u ON a.provider_id = u.id
            WHERE u.name IS NOT NULL 
              AND a.start_at >= '{$startDate}'
              AND a.start_at <= '{$endDate}'
            GROUP BY a.provider_id, u.name
            ORDER BY total_appointments DESC
            LIMIT 10
        ");
        
        $results = $query->getResultArray();
        
        $labels = [];
        $data = [];
        
        foreach ($results as $row) {
            $labels[] = $row['provider_name'];
            $data[] = (int)$row['total_appointments'];
        }
        
        if (empty($labels)) {
            return ['labels' => ['No Data'], 'data' => [0]];
        }
        
        return ['labels' => $labels, 'data' => $data];
    }

    /**
     * Get appointment status distribution with colors
     * @deprecated Use getStatusStats(['format' => 'chart', 'includeColors' => true]) instead
     */
    public function getStatusDistributionWithColors(): array
    {
        // DEPRECATED: Use getStatusStats() for new code
        return $this->getStatusStats([
            'format' => 'chart',
            'includeColors' => true
        ]);
    }

    /**
     * Get monthly appointment counts for chart (appointment growth)
     * @deprecated Use getAppointmentGrowth('year') instead - it shows 12 months centered on current
     * Note: This method is not currently used anywhere in the codebase
     */
    public function getMonthlyAppointments(int $months = 6): array
    {
        // DEPRECATED: Use getAppointmentGrowth('year') for new code
        // This legacy method only looks at past months
        $labels = [];
        $data = [];
        
        $local = $this->localNow();
        for ($i = $months - 1; $i >= 0; $i--) {
            $monthLocal = (clone $local)->modify("-{$i} months");
            $monthStart = $this->toUtc($monthLocal->format('Y-m-01') . ' 00:00:00');
            $monthEnd = $this->toUtc($monthLocal->format('Y-m-t') . ' 23:59:59');
            $monthLabel = $monthLocal->format('M');
            
            $count = $this->where('start_at >=', $monthStart)
                          ->where('start_at <=', $monthEnd)
                          ->countAllResults(false);
            
            $labels[] = $monthLabel;
            $data[] = $count;
        }
        
        return ['labels' => $labels, 'data' => $data];
    }

    /**
     * Get services by provider (appointment counts per provider per service)
     */
    public function getServicesByProvider(int $limit = 10): array
    {
        $db = \Config\Database::connect();
        $tableName = $this->table;
        
        $query = $db->query("
            SELECT 
                u.name as provider_name,
                s.name as service_name,
                COUNT(a.id) as appointment_count
            FROM {$tableName} a
            LEFT JOIN xs_users u ON a.provider_id = u.id
            LEFT JOIN xs_services s ON a.service_id = s.id
            WHERE u.name IS NOT NULL AND s.name IS NOT NULL
            GROUP BY a.provider_id, a.service_id, u.name, s.name
            ORDER BY appointment_count DESC
            LIMIT {$limit}
        ");
        
        return $query->getResultArray();
    }

    /**
     * Get provider appointment summary for chart
     */
    public function getProviderServiceSummary(): array
    {
        $db = \Config\Database::connect();
        $tableName = $this->table;
        
        $query = $db->query("
            SELECT 
                u.name as provider_name,
                COUNT(a.id) as total_appointments
            FROM {$tableName} a
            LEFT JOIN xs_users u ON a.provider_id = u.id
            WHERE u.name IS NOT NULL
            GROUP BY a.provider_id, u.name
            ORDER BY total_appointments DESC
            LIMIT 10
        ");
        
        $results = $query->getResultArray();
        
        $labels = [];
        $data = [];
        
        foreach ($results as $row) {
            $labels[] = $row['provider_name'];
            $data[] = (int)$row['total_appointments'];
        }
        
        // If no data, return placeholder
        if (empty($labels)) {
            return ['labels' => ['No Data'], 'data' => [0]];
        }
        
        return ['labels' => $labels, 'data' => $data];
    }
}

