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
        'appointment_date',
        'appointment_time',
        'start_time',
        'end_time',
        'status',
        'notes',
        'hash',
        'public_token',
        'public_token_expires_at',
    ];

    protected $beforeInsert = ['generateHash'];

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
     */
    public function getRevenueTrend(): array
    {
        $currentRevenue = $this->getRevenue('month');
        
        // Previous month revenue
        $prevMonthStart = date('Y-m-01 00:00:00', strtotime('first day of last month'));
        $prevMonthEnd = date('Y-m-t 23:59:59', strtotime('last day of last month'));
        $prevCount = $this->where('status', 'completed')
                          ->where('start_time >=', $prevMonthStart)
                          ->where('start_time <=', $prevMonthEnd)
                          ->countAllResults();
        $prevRevenue = $prevCount * 50; // Same calculation as getRevenue

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
                COALESCE(c.name, 'Unknown Customer') as customer_name,
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
    public function getChartData(string $period = 'week'): array
    {
        $data = [];
        $labels = [];
        if ($period === 'week') {
            for ($i = 6; $i >= 0; $i--) {
                $dayStart = date('Y-m-d 00:00:00', strtotime("-{$i} days"));
                $dayEnd   = date('Y-m-d 23:59:59', strtotime("-{$i} days"));
                $count = $this->where('start_time >=', $dayStart)
                              ->where('start_time <=', $dayEnd)
                              ->countAllResults(false);
                $labels[] = date('M j', strtotime($dayStart));
                $data[] = $count;
            }
        } elseif ($period === 'month') {
            for ($i = 3; $i >= 0; $i--) {
                $startDate = date('Y-m-d 00:00:00', strtotime("-{$i} weeks monday"));
                $endDate   = date('Y-m-d 23:59:59', strtotime("-{$i} weeks sunday"));
                $count = $this->where('start_time >=', $startDate)
                              ->where('start_time <=', $endDate)
                              ->countAllResults(false);
                $labels[] = 'Week ' . (4 - $i);
                $data[] = $count;
            }
        }
        return ['labels' => $labels, 'data' => $data];
    }

    /**
     * Status distribution for pie chart
     */
    public function getStatusDistribution(): array
    {
        $statuses = ['pending', 'confirmed', 'completed', 'cancelled', 'no-show'];
        $data = [];
        $labels = [];
        foreach ($statuses as $status) {
            $count = $this->where('status', $status)->countAllResults(false);
            if ($count > 0) {
                $labels[] = ucfirst($status);
                $data[] = $count;
            }
        }
        return ['labels' => $labels, 'data' => $data];
    }

    /**
     * Calculate revenue from completed appointments (placeholder)
     */
    public function getRevenue(string $period = 'month'): int
    {
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
        return $count * 50; // placeholder average revenue
    }
}

