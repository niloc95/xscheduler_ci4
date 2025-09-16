<?php
namespace App\Models;

/**
 * Unified AppointmentModel after user->customer split.
 * Keeps legacy dashboard/stat methods while adopting new customer_id field.
 */
class AppointmentModel extends BaseModel
{
    protected $table = 'appointments';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'customer_id','provider_id','service_id','start_time','end_time','status','notes','reminder_sent','created_at','updated_at'
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'customer_id' => 'required|is_natural_no_zero',
        'provider_id' => 'required|is_natural_no_zero',
        'service_id'  => 'required|is_natural_no_zero',
        'start_time'  => 'required|valid_date',
        'end_time'    => 'required|valid_date',
        'status'      => 'required|in_list[booked,cancelled,completed,rescheduled]',
        'reminder_sent' => 'permit_empty|in_list[0,1]'
    ];

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

    public function book(array $data): int|false
    {
        if (!$this->insert($data, false)) {
            return false;
        }
        return (int)$this->getInsertID();
    }

    // Dashboard / analytics helpers (adapted from legacy model)
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
                               ->where('status', 'booked')
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

    public function getRecentAppointments(int $limit = 10): array
    {
        return $this->orderBy('created_at', 'DESC')
                    ->limit($limit)
                    ->find();
    }

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

    public function getStatusDistribution(): array
    {
        $statuses = ['booked', 'completed', 'cancelled', 'rescheduled'];
        $data = [];
        $labels = [];
        foreach ($statuses as $status) {
            $count = $this->where('status', $status)->countAllResults(false);
            if ($count > 0) { $labels[] = ucfirst($status); $data[] = $count; }
        }
        return ['labels' => $labels, 'data' => $data];
    }

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
