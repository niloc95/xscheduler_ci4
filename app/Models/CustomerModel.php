<?php
namespace App\Models;

use CodeIgniter\Model;

class CustomerModel extends Model
{
	protected $table            = 'customers';
	protected $primaryKey       = 'id';
	protected $returnType       = 'array';
	protected $useSoftDeletes   = false;
	protected $protectFields    = true;
	protected $allowedFields    = [
		'first_name','last_name','email','phone','address','notes','created_at','updated_at'
	];
	protected $useTimestamps = true;
	protected $createdField  = 'created_at';
	protected $updatedField  = 'updated_at';

	public function countAllSafe(): int
	{
		try { return $this->countAll(); } catch (\Throwable $e) { return 0; }
	}

	public function search(array $opts = []): array
	{
		$builder = $this->builder();
		if (!empty($opts['q'])) {
			$q = $opts['q'];
			$builder->groupStart()
				->like('first_name', $q)
				->orLike('last_name', $q)
				->orLike('email', $q)
				->groupEnd();
		}
		if (!empty($opts['limit'])) $builder->limit((int)$opts['limit']);
		return $builder->orderBy('created_at','DESC')->get()->getResultArray();
	}
}

