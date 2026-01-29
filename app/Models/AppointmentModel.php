<?php

namespace App\Models;

use App\Models\BaseModel;

use CodeIgniter\Database\BaseBuilder;

class AppointmentModel extends BaseModel
{
    protected $table            = 'xs_appointments';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $useTimestamps    = true;
    protected $dateFormat       = 'datetime';
    protected $createdField     = 'created_at';
    protected $updatedField     = 'updated_at';

    // Only valid fields
    protected $allowedFields    = [
        'user_id',
        'customer_id',
        'service_id',
        'provider_id',
        'location_id',
        'location_name',
        'location_address',
        'location_contact',
        'appointment_date',
        'appointment_time',
        'start_time',
        'end_time',
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
        'start_time'  => 'permit_empty|valid_date',
        'end_time'    => 'permit_empty|valid_date',
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
     * Upcoming appointments for provider
     */
    public function upcomingForProvider(int $providerId, int $days = 30): array
    {
        return $this->builder()
            ->select('appointments.*, c.first_name, c.last_name, s.name as service_name')
            ->join('customers c', 'c.id = appointments.customer_id', 'left')
            ->join('services s', 's.id = appointments.service_id', 'left')
            ->where('provider_id', $providerId)
            ->where('start_time >=', date('Y-m-d H:i:s'))
            ->where('start_time <', date('Y-m-d H:i:s', strtotime("+{$days} days")))
            ->orderBy('start_time','ASC')
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
        if (empty($payload['user_id']) && !empty($payload['provider_id'])) {
            $payload['user_id'] = $payload['provider_id'];
        }
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
        $now        = date('Y-m-d H:i:s');
        $todayStart = date('Y-m-d 00:00:00');
        $todayEnd   = date('Y-m-d 23:59:59');
        $weekStart  = date('Y-m-d 00:00:00', strtotime('monday this week'));
        $weekEnd    = date('Y-m-d 23:59:59', strtotime('sunday this week'));
        $monthStart = date('Y-m-01 00:00:00');
        $monthEnd   = date('Y-m-t 23:59:59');

        return [
            'total'     => (int) $this->applyAllScopes($this->builder(), $context, $statusFilter)->countAllResults(),
            'today'     => (int) $this->applyAllScopes($this->builder()
                ->where('start_time >=', $todayStart)
                ->where('start_time <=', $todayEnd), $context, $statusFilter)->countAllResults(),
            'upcoming'  => $this->countByStatus('upcoming', $context, $statusFilter),
            'pending'   => $this->countByStatus('pending', $context, $statusFilter),
            'completed' => $this->countByStatus('completed', $context, $statusFilter),
            'cancelled' => $this->countByStatus('cancelled', $context, $statusFilter),
            'this_week' => (int) $this->applyAllScopes($this->builder()
                ->where('start_time >=', $weekStart)
                ->where('start_time <=', $weekEnd), $context, $statusFilter)->countAllResults(),
            'this_month'=> (int) $this->applyAllScopes($this->builder()
                ->where('start_time >=', $monthStart)
                ->where('start_time <=', $monthEnd), $context, $statusFilter)->countAllResults(),
        ];
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
            $builder->where('xs_appointments.start_time >=', $filters['start']);
        }
        if (!empty($filters['end'])) {
            $builder->where('xs_appointments.start_time <=', $filters['end']);
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

        $builder->orderBy('xs_appointments.start_time', 'ASC');

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
            ->orderBy('xs_appointments.start_time', 'ASC')
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
            $builder->where('xs_appointments.start_time >=', date('Y-m-d H:i:s'))
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
        // Current month appointments
        $currentMonthStart = date('Y-m-01 00:00:00');
        $currentMonthEnd = date('Y-m-t 23:59:59');
        $currentCount = $this->where('start_time >=', $currentMonthStart)
                             ->where('start_time <=', $currentMonthEnd)
                             ->countAllResults(false);

        // Previous month appointments
        $prevMonthStart = date('Y-m-01 00:00:00', strtotime('first day of last month'));
        $prevMonthEnd = date('Y-m-t 23:59:59', strtotime('last day of last month'));
        $prevCount = $this->where('start_time >=', $prevMonthStart)
                          ->where('start_time <=', $prevMonthEnd)
                          ->countAllResults(false);

        return $this->calculateTrendPercentage($currentCount, $prevCount);
    }

    /**
     * Calculate trend for pending/today's appointments
     */
    public function getPendingTrend(): array
    {
        // Today's pending
        $todayStart = date('Y-m-d 00:00:00');
        $todayEnd = date('Y-m-d 23:59:59');
        $todayCount = $this->where('start_time >=', $todayStart)
                           ->where('start_time <=', $todayEnd)
                           ->countAllResults(false);

        // Yesterday's count (same time comparison)
        $yesterdayStart = date('Y-m-d 00:00:00', strtotime('-1 day'));
        $yesterdayEnd = date('Y-m-d 23:59:59', strtotime('-1 day'));
        $yesterdayCount = $this->where('start_time >=', $yesterdayStart)
                               ->where('start_time <=', $yesterdayEnd)
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
        if ($period === 'month') {
            $completed->where('start_time >=', date('Y-m-01 00:00:00'))
                      ->where('start_time <=', date('Y-m-t 23:59:59'));
        } elseif ($period === 'week') {
            $completed->where('start_time >=', date('Y-m-d 00:00:00', strtotime('monday this week')))
                      ->where('start_time <=', date('Y-m-d 23:59:59', strtotime('sunday this week')));
        } elseif ($period === 'today') {
            $completed->where('start_time >=', date('Y-m-d 00:00:00'))
                      ->where('start_time <=', date('Y-m-d 23:59:59'));
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
        
        $periodCondition = '';
        if ($period === 'month') {
            $periodCondition = "AND a.start_time >= '" . date('Y-m-01 00:00:00') . "' AND a.start_time <= '" . date('Y-m-t 23:59:59') . "'";
        } elseif ($period === 'week') {
            $periodCondition = "AND a.start_time >= '" . date('Y-m-d 00:00:00', strtotime('monday this week')) . "' AND a.start_time <= '" . date('Y-m-d 23:59:59', strtotime('sunday this week')) . "'";
        } elseif ($period === 'today') {
            $periodCondition = "AND a.start_time >= '" . date('Y-m-d 00:00:00') . "' AND a.start_time <= '" . date('Y-m-d 23:59:59') . "'";
        } elseif ($period === 'last_month') {
            $periodCondition = "AND a.start_time >= '" . date('Y-m-01 00:00:00', strtotime('first day of last month')) . "' AND a.start_time <= '" . date('Y-m-t 23:59:59', strtotime('last day of last month')) . "'";
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
        
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $dayStart = $date . ' 00:00:00';
            $dayEnd = $date . ' 23:59:59';
            
            $query = $db->query("
                SELECT COALESCE(SUM(s.price), 0) as revenue
                FROM {$tableName} a
                LEFT JOIN xs_services s ON a.service_id = s.id
                WHERE a.status = 'completed'
                AND a.start_time >= '{$dayStart}'
                AND a.start_time <= '{$dayEnd}'
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
        
        for ($i = $months - 1; $i >= 0; $i--) {
            $monthStart = date('Y-m-01 00:00:00', strtotime("-{$i} months"));
            $monthEnd = date('Y-m-t 23:59:59', strtotime("-{$i} months"));
            $monthLabel = date('M', strtotime("-{$i} months"));
            
            $query = $db->query("
                SELECT COALESCE(SUM(s.price), 0) as revenue
                FROM {$tableName} a
                LEFT JOIN xs_services s ON a.service_id = s.id
                WHERE a.status = 'completed'
                AND a.start_time >= '{$monthStart}'
                AND a.start_time <= '{$monthEnd}'
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
        
        $query = $db->query("
            SELECT 
                DATE_FORMAT(start_time, '%l:00 %p') as time_slot,
                HOUR(start_time) as hour,
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
                for ($hour = 6; $hour <= 21; $hour++) {
                    $hourStart = date('Y-m-d') . ' ' . sprintf('%02d:00:00', $hour);
                    $hourEnd = date('Y-m-d') . ' ' . sprintf('%02d:59:59', $hour);
                    
                    $count = $this->where('start_time >=', $hourStart)
                                  ->where('start_time <=', $hourEnd)
                                  ->countAllResults(false);
                    
                    $labels[] = date('ga', strtotime($hourStart)); // e.g., "9am"
                    $data[] = $count;
                }
                break;
                
            case 'week':
                // Current week: Monday to Sunday
                $monday = date('Y-m-d', strtotime('monday this week'));
                for ($i = 0; $i < 7; $i++) {
                    $dayStart = date('Y-m-d 00:00:00', strtotime("$monday +{$i} days"));
                    $dayEnd = date('Y-m-d 23:59:59', strtotime("$monday +{$i} days"));
                    
                    $count = $this->where('start_time >=', $dayStart)
                                  ->where('start_time <=', $dayEnd)
                                  ->countAllResults(false);
                    
                    $dayLabel = date('D', strtotime("$monday +{$i} days"));
                    // Mark today
                    if (date('Y-m-d', strtotime("$monday +{$i} days")) === date('Y-m-d')) {
                        $dayLabel .= '*';
                    }
                    $labels[] = $dayLabel;
                    $data[] = $count;
                }
                break;
                
            case 'year':
                // 12 months: 6 past + current + 5 future
                for ($i = -6; $i <= 5; $i++) {
                    if ($i < 0) {
                        $monthStart = date('Y-m-01 00:00:00', strtotime("{$i} months"));
                        $monthEnd = date('Y-m-t 23:59:59', strtotime("{$i} months"));
                    } elseif ($i == 0) {
                        $monthStart = date('Y-m-01 00:00:00');
                        $monthEnd = date('Y-m-t 23:59:59');
                    } else {
                        $monthStart = date('Y-m-01 00:00:00', strtotime("+{$i} months"));
                        $monthEnd = date('Y-m-t 23:59:59', strtotime("+{$i} months"));
                    }
                    
                    $count = $this->where('start_time >=', $monthStart)
                                  ->where('start_time <=', $monthEnd)
                                  ->countAllResults(false);
                    
                    $monthLabel = date('M', strtotime($monthStart));
                    // Mark current month
                    if (date('Y-m', strtotime($monthStart)) === date('Y-m')) {
                        $monthLabel .= '*';
                    }
                    $labels[] = $monthLabel;
                    $data[] = $count;
                }
                break;
                
            case 'month':
            default:
                // 4 weeks: 2 past + current + 1 future
                $today = strtotime('today');
                $startOfCurrentWeek = strtotime('monday this week');
                
                for ($i = -2; $i <= 1; $i++) {
                    $weekStart = date('Y-m-d 00:00:00', strtotime("monday " . ($i < 0 ? "{$i} weeks" : ($i == 0 ? "this week" : "+{$i} weeks"))));
                    $weekEnd = date('Y-m-d 23:59:59', strtotime("sunday " . ($i < 0 ? "{$i} weeks" : ($i == 0 ? "this week" : "+{$i} weeks"))));
                    
                    $count = $this->where('start_time >=', $weekStart)
                                  ->where('start_time <=', $weekEnd)
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
        switch ($period) {
            case 'day':
                $startDate = date('Y-m-d 00:00:00');
                $endDate = date('Y-m-d 23:59:59');
                break;
            case 'week':
                $startDate = date('Y-m-d 00:00:00', strtotime('monday this week'));
                $endDate = date('Y-m-d 23:59:59', strtotime('sunday this week'));
                break;
            case 'year':
                $startDate = date('Y-m-d 00:00:00', strtotime('-6 months'));
                $endDate = date('Y-m-d 23:59:59', strtotime('+5 months'));
                break;
            case 'month':
            default:
                $startDate = date('Y-m-d 00:00:00', strtotime('-2 weeks'));
                $endDate = date('Y-m-d 23:59:59', strtotime('+1 week'));
                break;
        }
        
        $query = $db->query("
            SELECT 
                u.name as provider_name,
                COUNT(a.id) as total_appointments
            FROM {$tableName} a
            LEFT JOIN xs_users u ON a.provider_id = u.id
            WHERE u.name IS NOT NULL 
              AND a.start_time >= '{$startDate}'
              AND a.start_time <= '{$endDate}'
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
        
        for ($i = $months - 1; $i >= 0; $i--) {
            $monthStart = date('Y-m-01 00:00:00', strtotime("-{$i} months"));
            $monthEnd = date('Y-m-t 23:59:59', strtotime("-{$i} months"));
            $monthLabel = date('M', strtotime("-{$i} months"));
            
            $count = $this->where('start_time >=', $monthStart)
                          ->where('start_time <=', $monthEnd)
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

