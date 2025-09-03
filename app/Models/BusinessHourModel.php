<?php

namespace App\Models;

use CodeIgniter\Model;

class BusinessHourModel extends Model
{
    protected $table            = 'business_hours';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'provider_id', 'weekday', 'start_time', 'end_time', 'breaks_json'
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'provider_id' => 'required|integer',
        'weekday'     => 'required|integer|greater_than_equal_to[0]|less_than_equal_to[6]',
        'start_time'  => 'required',
        'end_time'    => 'required',
    ];
}
