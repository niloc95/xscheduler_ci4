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

	/**
	 * Backward compatibility helper for legacy calls expecting getAllCustomers().
	 * Optional $limit to keep large lists manageable.
	 */
	public function getAllCustomers(int $limit = 0): array
	{
		if ($limit > 0) {
			return $this->orderBy('created_at','DESC')->findAll($limit);
		}
		return $this->orderBy('created_at','DESC')->findAll();
	}

	/**
	 * Backward compatibility for legacy controllers expecting countAllCustomers().
	 */
	public function countAllCustomers(): int
	{
		// Use Query Builder directly to avoid potential soft delete filters if added later.
		return (int)$this->builder()->countAllResults();
	}

	/** Return a single customer by email (legacy helper). */
	public function findByEmail(string $email): ?array
	{
		return $this->where('email', $email)->first();
	}
}

