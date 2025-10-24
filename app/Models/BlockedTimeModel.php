<?php

namespace App\Models;

use CodeIgniter\Model;

class BlockedTimeModel extends Model
{
    protected $table            = 'xs_blocked_times';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'provider_id', 'start_time', 'end_time', 'reason'
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
