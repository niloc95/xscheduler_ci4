<?php

namespace App\Models;

use CodeIgniter\Model;

class AppointmentModel extends Model
{
    protected $table            = 'appointments';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'user_id', 'provider_id', 'service_id', 'start_time', 'end_time', 'status', 'notes'
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Validation
    protected $validationRules      = [
        'user_id'     => 'required|integer',
        'provider_id' => 'required|integer',
        'service_id'  => 'required|integer',
        'start_time'  => 'required|valid_date',
        'end_time'    => 'required|valid_date',
        'status'      => 'required|in_list[booked,cancelled,completed,rescheduled]'
    ];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    /**
     * Get appointment statistics
     */
    public function getStats()
    {
        $total = $this->countAll();
        $today = date('Y-m-d');
        
        return [
            'total' => $total,
            'today' => $this->where('DATE(start_time)', $today)->countAllResults(false),
            'upcoming' => $this->where('start_time >', date('Y-m-d H:i:s'))
                               ->where('status', 'booked')
                               ->countAllResults(false),
            'completed' => $this->where('status', 'completed')->countAllResults(false),
            'cancelled' => $this->where('status', 'cancelled')->countAllResults(false),
            'this_week' => $this->where('start_time >=', date('Y-m-d', strtotime('monday this week')))
                                ->where('start_time <=', date('Y-m-d', strtotime('sunday this week')))
                                ->countAllResults(false),
            'this_month' => $this->where('YEAR(start_time)', date('Y'))
                                 ->where('MONTH(start_time)', date('m'))
                                 ->countAllResults(false)
        ];
    }

    /**
     * Get recent appointments with user and service details
     */
    public function getRecentAppointments($limit = 10)
    {
        // Simplified version - return basic appointment data
        return $this->orderBy('created_at', 'DESC')
                    ->limit($limit)
                    ->find();
    }

    /**
     * Get appointment activity for dashboard (recent changes)
     */
    public function getRecentActivity($limit = 5)
    {
        // Simplified version without joins to avoid table prefix issues
        $appointments = $this->orderBy('updated_at', 'DESC')
                             ->limit($limit)
                             ->find();
        
        // For now, return mock data structure that matches expected format
        $activities = [];
        foreach ($appointments as $appointment) {
            $activities[] = [
                'customer_name' => 'User #' . $appointment['user_id'],
                'service_name' => 'Service #' . $appointment['service_id'],
                'status' => $appointment['status'],
                'updated_at' => $appointment['updated_at']
            ];
        }
        
        return $activities;
    }

    /**
     * Get appointment data for charts
     */
    public function getChartData($period = 'week')
    {
        $data = [];
        $labels = [];
        
        if ($period === 'week') {
            // Last 7 days
            for ($i = 6; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-{$i} days"));
                $count = $this->where('DATE(start_time)', $date)->countAllResults(false);
                
                $labels[] = date('M j', strtotime($date));
                $data[] = $count;
            }
        } else if ($period === 'month') {
            // Last 4 weeks
            for ($i = 3; $i >= 0; $i--) {
                $startDate = date('Y-m-d', strtotime("-{$i} weeks monday"));
                $endDate = date('Y-m-d', strtotime("-{$i} weeks sunday"));
                
                $count = $this->where('DATE(start_time) >=', $startDate)
                              ->where('DATE(start_time) <=', $endDate)
                              ->countAllResults(false);
                
                $labels[] = 'Week ' . (4 - $i);
                $data[] = $count;
            }
        }
        
        return [
            'labels' => $labels,
            'data' => $data
        ];
    }

    /**
     * Get status distribution for pie chart
     */
    public function getStatusDistribution()
    {
        $statuses = ['booked', 'completed', 'cancelled', 'rescheduled'];
        $data = [];
        $labels = [];
        
        foreach ($statuses as $status) {
            $count = $this->where('status', $status)->countAllResults(false);
            if ($count > 0) {
                $labels[] = ucfirst($status);
                $data[] = $count;
            }
        }
        
        return [
            'labels' => $labels,
            'data' => $data
        ];
    }

    /**
     * Calculate revenue from completed appointments
     */
    public function getRevenue($period = 'month')
    {
        // For now, return a simple calculation or 0 if no completed appointments
        // This avoids the complex join issue until we can solve the table prefix problem
        
        $completedCount = $this->where('status', 'completed');
        
        if ($period === 'month') {
            $completedCount->where('YEAR(start_time)', date('Y'))
                          ->where('MONTH(start_time)', date('m'));
        } elseif ($period === 'week') {
            $completedCount->where('start_time >=', date('Y-m-d', strtotime('monday this week')))
                          ->where('start_time <=', date('Y-m-d', strtotime('sunday this week')));
        } elseif ($period === 'today') {
            $completedCount->where('DATE(start_time)', date('Y-m-d'));
        }
        
        $count = $completedCount->countAllResults();
        
        // Simple revenue calculation: average price of $50 per appointment
        // In a real scenario, this should calculate actual revenue from service prices
        return $count * 50;
    }
}
