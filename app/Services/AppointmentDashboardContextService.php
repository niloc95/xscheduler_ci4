<?php

/**
 * =============================================================================
 * APPOINTMENT DASHBOARD CONTEXT SERVICE
 * =============================================================================
 * 
 * @file        app/Services/AppointmentDashboardContextService.php
 * @description Builds role-based context for appointment dashboard queries.
 *              Determines filtering based on user role and permissions.
 * 
 * PURPOSE:
 * -----------------------------------------------------------------------------
 * Centralizes the logic for determining what appointments a user can see
 * based on their role. This ensures consistent access control across all
 * dashboard and appointment list views.
 * 
 * ROLE-BASED ACCESS:
 * -----------------------------------------------------------------------------
 * - admin    : Sees ALL appointments (no filtering)
 * - provider : Sees only THEIR appointments (filter by provider_id)
 * - staff    : Sees appointments for assigned providers (filter by staff_id)
 * - customer : Sees only their own appointments (filter by customer_id)
 * - guest    : No access (returns empty results)
 * 
 * KEY METHODS:
 * -----------------------------------------------------------------------------
 * build($role, $userId, $user)
 *   Build context array for dashboard queries
 *   Returns array with role, IDs, and filter flags
 * 
 * CONTEXT STRUCTURE:
 * -----------------------------------------------------------------------------
 *     [
 *         'role'               => 'provider',
 *         'user_id'            => 123,
 *         'provider_id'        => 123,
 *         'staff_id'           => null,
 *         'customer_id'        => null,
 *         'filter_by_provider' => true,
 *         'filter_by_staff'    => false,
 *         'filter_by_customer' => false,
 *     ]
 * 
 * USAGE:
 * -----------------------------------------------------------------------------
 *     $contextService = new AppointmentDashboardContextService();
 *     $context = $contextService->build($role, $userId, $userData);
 *     
 *     // Use context in appointment queries
 *     if ($context['filter_by_provider']) {
 *         $query->where('provider_id', $context['provider_id']);
 *     }
 * 
 * @see         app/Services/DashboardService.php for usage
 * @see         app/Controllers/Dashboard.php
 * @package     App\Services
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

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
