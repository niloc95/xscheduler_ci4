<?php

namespace App\Models;

use App\Models\BaseModel;

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
        'hash'
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
    public function getStats(): array
    {
        $total = $this->countAll();
        $now        = date('Y-m-d H:i:s');
        $todayStart = date('Y-m-d 00:00:00');
        $todayEnd   = date('Y-m-d 23:59:59');
        $weekStart  = date('Y-m-d 00:00:00', strtotime('monday this week'));
        $weekEnd    = date('Y-m-d 23:59:59', strtotime('sunday this week'));
        $monthStart = date('Y-m-01 00:00:00');
        $monthEnd   = date('Y-m-t 23:59:59');
        return [
            'total' => $total,
            'today' => $this->where('start_time >=', $todayStart)
                            ->where('start_time <=', $todayEnd)
                            ->countAllResults(false),
            'upcoming' => $this->where('start_time >', $now)
                               ->whereIn('status', ['pending', 'confirmed'])
                               ->countAllResults(false),
            'completed' => $this->where('status', 'completed')->countAllResults(false),
            'cancelled' => $this->where('status', 'cancelled')->countAllResults(false),
            'this_week' => $this->where('start_time >=', $weekStart)
                                ->where('start_time <=', $weekEnd)
                                ->countAllResults(false),
            'this_month' => $this->where('start_time >=', $monthStart)
                                 ->where('start_time <=', $monthEnd)
                                 ->countAllResults(false)
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
     * Recent activity for dashboard
     */
    public function getRecentActivity(int $limit = 5): array
    {
        $appointments = $this->orderBy('updated_at', 'DESC')
                             ->limit($limit)
                             ->find();
        $activities = [];
        foreach ($appointments as $appointment) {
            $activities[] = [
                'customer_id' => $appointment['customer_id'] ?? null,
                'service_id'  => $appointment['service_id'] ?? null,
                'status'      => $appointment['status'] ?? null,
                'updated_at'  => $appointment['updated_at'] ?? null,
            ];
        }
        return $activities;
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

