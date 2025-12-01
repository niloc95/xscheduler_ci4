<?php

namespace App\Services;

use App\Models\CustomerModel;
use App\Models\ProviderStaffModel;

class AppointmentDashboardContextService
{
    protected CustomerModel $customerModel;
    protected ProviderStaffModel $providerStaffModel;

    public function __construct()
    {
        $this->customerModel = new CustomerModel();
        $this->providerStaffModel = new ProviderStaffModel();
    }

    /**
     * Build a role-aware query scope for appointment dashboards.
     */
    public function build(?string $role, ?int $userId, ?array $currentUser = null): array
    {
        $context = [];

        if ($role === 'provider' && $userId) {
            $context['provider_id'] = (int) $userId;
            return $context;
        }

        if ($role === 'staff' && $userId) {
            $assignments = $this->providerStaffModel->getProvidersForStaff((int) $userId);
            if (!empty($assignments)) {
                $context['provider_id'] = array_map(static fn ($provider) => (int) $provider['id'], $assignments);
            }
        }

        if ($role === 'customer') {
            $customerId = $this->resolveCustomerId($currentUser);
            if ($customerId) {
                $context['customer_id'] = $customerId;
            }
        }

        return $context;
    }

    private function resolveCustomerId(?array $currentUser): ?int
    {
        $sessionCustomerId = session()->get('customer_id');
        if ($sessionCustomerId) {
            return (int) $sessionCustomerId;
        }

        if (!$currentUser) {
            return null;
        }

        $email = $currentUser['email'] ?? null;
        if (!$email) {
            return null;
        }

        $customer = $this->customerModel
            ->select('id')
            ->where('email', $email)
            ->first();

        return isset($customer['id']) ? (int) $customer['id'] : null;
    }
}
