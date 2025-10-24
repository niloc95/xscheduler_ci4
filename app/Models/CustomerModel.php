<?php
namespace App\Models;

use App\Models\BaseModel;

class CustomerModel extends BaseModel
{
	protected $table = 'xs_customers';
	protected $primaryKey = 'id';
	protected $allowedFields = [
		'first_name', 'last_name', 'name', 'email', 'phone', 'address', 'notes', 'custom_fields', 'created_at', 'updated_at'
	];

	// Validation rules
	protected $validationRules = [
		'first_name' => 'permit_empty|max_length[100]',
		'last_name'  => 'permit_empty|max_length[100]',
		'name'       => 'permit_empty|max_length[200]',
		'email'      => 'required|valid_email|is_unique[customers.email,id,{id}]',
		'phone'      => 'permit_empty|max_length[20]',
		'address'    => 'permit_empty|max_length[255]',
		'notes'      => 'permit_empty|max_length[1000]',
		'custom_fields' => 'permit_empty',
	];
	protected $validationMessages = [];
	protected $skipValidation = false;
	protected $cleanValidationRules = true;

	/**
	 * Search customers by name or email
	 */
	public function search(array $params = []): array
	{
		$q = trim((string)($params['q'] ?? ''));
		$limit = (int)($params['limit'] ?? 50);
		$builder = $this->builder();
		if ($q !== '') {
			$builder->groupStart()
				->like('first_name', $q)
				->orLike('last_name', $q)
				->orLike('name', $q)
				->orLike('email', $q)
			->groupEnd();
		}
		$builder->orderBy('created_at', 'DESC');
		if ($limit > 0) {
			$builder->limit($limit);
		}
		return $builder->get()->getResultArray();
	}

	/**
	 * Count all customers
	 */
	public function countAllCustomers(): int
	{
		return $this->countAll();
	}

	/**
	 * Get recent customers
	 */
	public function getRecentCustomers($limit = 10): array
	{
		return $this->orderBy('created_at', 'DESC')->limit($limit)->find();
	}

	/**
	 * Get customer statistics for dashboard
	 */
	public function getStats(): array
	{
		$total = $this->countAll();
		$today = $this->where('created_at >=', date('Y-m-d 00:00:00'))
					  ->where('created_at <=', date('Y-m-d 23:59:59'))
					  ->countAllResults(false);
		$recent = $this->where('created_at >=', date('Y-m-d', strtotime('-30 days')))
					   ->countAllResults(false);
		return [
			'total' => $total,
			'today' => $today,
			'recent' => $recent
		];
	}
}
