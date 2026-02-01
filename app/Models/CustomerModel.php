<?php

/**
 * =============================================================================
 * CUSTOMER MODEL
 * =============================================================================
 * 
 * @file        app/Models/CustomerModel.php
 * @description Data model for customers (people who book appointments).
 *              Separate from users - customers don't log in to the system.
 * 
 * DATABASE TABLE: xs_customers
 * -----------------------------------------------------------------------------
 * Columns:
 * - id              : Primary key
 * - first_name      : Customer first name
 * - last_name       : Customer last name
 * - email           : Contact email (for notifications)
 * - phone           : Contact phone (for SMS/WhatsApp)
 * - address         : Optional address
 * - notes           : Staff notes about customer
 * - custom_fields   : JSON for additional data
 * - hash            : Unique identifier for URLs
 * - created_at      : Creation timestamp
 * - updated_at      : Last update timestamp
 * 
 * CUSTOMERS VS USERS:
 * -----------------------------------------------------------------------------
 * - Customers: People who book appointments, no system login
 * - Users: System users (admin, provider, staff) who log in
 * 
 * KEY METHODS:
 * -----------------------------------------------------------------------------
 * - findByHash()         : Find customer by public hash
 * - findByEmail()        : Find customer by email
 * - findByPhone()        : Find customer by phone
 * - search()             : Search by name/email/phone
 * - getOrCreate()        : Find existing or create new
 * - getAppointments()    : Customer's appointment history
 * 
 * MODEL CALLBACKS:
 * -----------------------------------------------------------------------------
 * - beforeInsert: generateHash (creates unique customer identifier)
 * 
 * VALIDATION:
 * -----------------------------------------------------------------------------
 * Validation is handled by BookingSettingsService which provides
 * dynamic rules based on admin settings. Model validation is disabled
 * to prevent conflicts.
 * 
 * @see         app/Controllers/CustomerManagement.php for admin CRUD
 * @see         app/Services/BookingSettingsService.php for validation
 * @package     App\Models
 * @extends     BaseModel
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

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

	/**
	 * Get new customers in a time period
	 */
	public function getNewCustomers(string $period = 'month'): int
	{
		$startDate = match($period) {
			'today' => date('Y-m-d 00:00:00'),
			'week' => date('Y-m-d 00:00:00', strtotime('monday this week')),
			'month' => date('Y-m-01 00:00:00'),
			'last_month' => date('Y-m-01 00:00:00', strtotime('first day of last month')),
			default => date('Y-m-01 00:00:00')
		};
		
		$endDate = match($period) {
			'today' => date('Y-m-d 23:59:59'),
			'week' => date('Y-m-d 23:59:59', strtotime('sunday this week')),
			'month' => date('Y-m-t 23:59:59'),
			'last_month' => date('Y-m-t 23:59:59', strtotime('last day of last month')),
			default => date('Y-m-t 23:59:59')
		};

		return $this->where('created_at >=', $startDate)
					->where('created_at <=', $endDate)
					->countAllResults();
	}

	/**
	 * Get customer growth trend (month over month)
	 */
	public function getGrowthTrend(): array
	{
		$currentMonth = $this->getNewCustomers('month');
		$lastMonth = $this->getNewCustomers('last_month');
		
		if ($lastMonth === 0) {
			return [
				'percentage' => $currentMonth > 0 ? 100 : 0,
				'direction' => $currentMonth > 0 ? 'up' : 'neutral',
				'current' => $currentMonth,
				'previous' => $lastMonth
			];
		}
		
		$change = (($currentMonth - $lastMonth) / $lastMonth) * 100;
		return [
			'percentage' => round(abs($change), 1),
			'direction' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'neutral'),
			'current' => $currentMonth,
			'previous' => $lastMonth
		];
	}

	/**
	 * Get new vs returning customers (based on appointment count)
	 */
	public function getNewVsReturning(): array
	{
		$db = \Config\Database::connect();
		
		// Count customers with only 1 appointment (new) vs more than 1 (returning)
		$query = $db->query("
			SELECT 
				SUM(CASE WHEN appt_count = 1 THEN 1 ELSE 0 END) as new_customers,
				SUM(CASE WHEN appt_count > 1 THEN 1 ELSE 0 END) as returning_customers
			FROM (
				SELECT c.id, COUNT(a.id) as appt_count
				FROM xs_customers c
				LEFT JOIN xs_appointments a ON c.id = a.customer_id
				GROUP BY c.id
			) as customer_counts
		");
		
		$result = $query->getRow();
		return [
			'new' => (int)($result->new_customers ?? 0),
			'returning' => (int)($result->returning_customers ?? 0)
		];
	}

	/**
	 * Get customer retention rate
	 */
	public function getRetentionRate(): float
	{
		$newVsReturning = $this->getNewVsReturning();
		$total = $newVsReturning['new'] + $newVsReturning['returning'];
		
		if ($total === 0) return 0;
		
		return round(($newVsReturning['returning'] / $total) * 100, 1);
	}

	/**
	 * Get customer loyalty segments
	 */
	public function getLoyaltySegments(): array
	{
		$db = \Config\Database::connect();
		
		$query = $db->query("
			SELECT 
				SUM(CASE WHEN appt_count = 1 THEN 1 ELSE 0 END) as first_time,
				SUM(CASE WHEN appt_count BETWEEN 2 AND 4 THEN 1 ELSE 0 END) as occasional,
				SUM(CASE WHEN appt_count BETWEEN 5 AND 10 THEN 1 ELSE 0 END) as regular,
				SUM(CASE WHEN appt_count > 10 THEN 1 ELSE 0 END) as vip
			FROM (
				SELECT c.id, COUNT(a.id) as appt_count
				FROM xs_customers c
				LEFT JOIN xs_appointments a ON c.id = a.customer_id
				GROUP BY c.id
			) as customer_counts
		");
		
		$result = $query->getRow();
		return [
			'first_time' => (int)($result->first_time ?? 0),
			'occasional' => (int)($result->occasional ?? 0),
			'regular' => (int)($result->regular ?? 0),
			'vip' => (int)($result->vip ?? 0)
		];
	}
}
