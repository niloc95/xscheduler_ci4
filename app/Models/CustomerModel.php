<?php
namespace App\Models;

use App\Models\BaseModel;

class CustomerModel extends BaseModel
{
	protected $table = 'xs_customers';
	protected $primaryKey = 'id';
	protected $allowedFields = [
		'first_name', 'last_name', 'email', 'phone', 'address', 'notes', 'custom_fields', 'hash', 'created_at', 'updated_at'
	];

	protected $beforeInsert = ['generateHash'];
	protected $useTimestamps = false;

	// Validation rules
	// NOTE: Validation is handled by BookingSettingsService which provides dynamic rules
	// based on settings. Model validation is disabled to prevent conflicts.
	protected $validationRules = [];
	protected $validationMessages = [];
	protected $skipValidation = true;
	protected $cleanValidationRules = true;

	/**
	 * Generate unique hash for new customer before insert
	 */
	protected function generateHash(array $data): array
	{
		if (!isset($data['data']['hash']) || empty($data['data']['hash'])) {
			$encryptionKey = config('Encryption')->key ?? 'default-secret-key';
			$data['data']['hash'] = hash('sha256', uniqid('customer_', true) . $encryptionKey . time());
		}
		return $data;
	}

	/**
	 * Find customer by hash
	 */
	public function findByHash(string $hash): ?array
	{
		$result = $this->where('hash', $hash)->first();
		return $result ?: null;
	}

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
