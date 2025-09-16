<?php
// app/Models/BaseModel.php
namespace App\Models;

use CodeIgniter\Model;

/**
 * BaseModel for shared CRUD config and utilities
 */
class BaseModel extends Model
{
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $useTimestamps    = true;
    protected $dateFormat       = 'datetime';
    protected $createdField     = 'created_at';
    protected $updatedField     = 'updated_at';

    // Optionally add shared utility methods here
}
