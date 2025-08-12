<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table            = 'users';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'name', 'email', 'phone', 'password_hash', 'role', 'reset_token', 'reset_expires'
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Validation
    protected $validationRules      = [
        'name'  => 'required|min_length[2]|max_length[255]',
        'email' => 'required|valid_email|is_unique[users.email,id,{id}]',
        'role'  => 'required|in_list[customer,provider,admin]'
    ];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    /**
     * Get user statistics for dashboard
     */
    public function getStats()
    {
        return [
            'total' => $this->countAll(),
            'customers' => $this->where('role', 'customer')->countAllResults(false),
            'providers' => $this->where('role', 'provider')->countAllResults(false),
            'admins' => $this->where('role', 'admin')->countAllResults(false),
            'recent' => $this->where('created_at >=', date('Y-m-d', strtotime('-30 days')))->countAllResults()
        ];
    }

    /**
     * Get recent user registrations
     */
    public function getRecentUsers($limit = 5)
    {
        return $this->select('id, name, email, role, created_at')
                    ->orderBy('created_at', 'DESC')
                    ->limit($limit)
                    ->find();
    }

    /**
     * Get user growth data for charts
     */
    public function getUserGrowthData($months = 6)
    {
        $data = [];
        $labels = [];
        
        for ($i = $months - 1; $i >= 0; $i--) {
            $date = date('Y-m-01', strtotime("-{$i} months"));
            $nextDate = date('Y-m-01', strtotime("-" . ($i - 1) . " months"));
            
            $count = $this->where('created_at >=', $date)
                          ->where('created_at <', $nextDate)
                          ->countAllResults(false);
            
            $labels[] = date('M', strtotime($date));
            $data[] = $count;
        }
        
        return [
            'labels' => $labels,
            'data' => $data
        ];
    }

    /**
     * Get the first admin user (for dashboard display when no session)
     */
    public function getFirstAdmin()
    {
        return $this->where('role', 'admin')->first();
    }
}
