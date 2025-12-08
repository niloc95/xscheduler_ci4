<?php

namespace App\Services;

/**
 * Service to build dashboard context for appointment queries.
 * Determines filtering based on user role and permissions.
 */
class AppointmentDashboardContextService
{
    /**
     * Build context array for dashboard appointment queries.
     *
     * @param string|null $role Current user's role
     * @param int|null $userId Current user's ID
     * @param array|null $user Current user data
     * @return array Context array with role-based filtering info
     */
    public function build(?string $role, ?int $userId, ?array $user): array
    {
        $context = [
            'role' => $role ?? 'guest',
            'user_id' => $userId,
            'provider_id' => null,
            'staff_id' => null,
            'customer_id' => null,
            'filter_by_provider' => false,
            'filter_by_staff' => false,
            'filter_by_customer' => false,
        ];

        // Determine context based on role
        switch ($role) {
            case 'admin':
                // Admin sees all appointments - no filtering
                break;
                
            case 'provider':
                // Provider sees only their appointments
                $context['provider_id'] = $userId;
                $context['filter_by_provider'] = true;
                break;
                
            case 'staff':
                // Staff sees appointments for their assigned providers
                $context['staff_id'] = $userId;
                $context['filter_by_staff'] = true;
                break;
                
            case 'customer':
                // Customer sees only their own appointments
                $context['customer_id'] = $user['customer_id'] ?? $userId;
                $context['filter_by_customer'] = true;
                break;
                
            default:
                // Guest or unknown role - no access
                $context['filter_by_customer'] = true;
                $context['customer_id'] = 0; // Will return no results
                break;
        }

        return $context;
    }
}
