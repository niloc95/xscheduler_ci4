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
    *         'role'        => 'provider',
    *         'user_id'     => 123,
    *         'provider_id' => 123,         // scalar for provider, int[] for staff, null for admin
    *         'customer_id' => null,
    *     ]
    *
    * USAGE:
    * -----------------------------------------------------------------------------
    *     $contextService = new AppointmentDashboardContextService();
    *     $context = $contextService->build($role, $userId, $userData);
    *     // Context is consumed by AppointmentModel::applyContextScope() which reads
    *     // 'provider_id' (accepts scalar or array) and 'customer_id'.
 * 
 * @see         app/Services/DashboardService.php for usage
 * @see         app/Controllers/Dashboard.php
 * @package     App\Services
 * @author      Nilesh Nagin Cara
 * @copyright   2024-2026 Nilesh Nagin Cara
 * =============================================================================
 */

namespace App\Services;

use App\Models\ProviderStaffModel;

/**
 * Service to build dashboard context for appointment queries.
 * Determines filtering based on user role and permissions.
 */
class AppointmentDashboardContextService
{
    private ProviderStaffModel $providerStaffModel;

    public function __construct(?ProviderStaffModel $providerStaffModel = null)
    {
        $this->providerStaffModel = $providerStaffModel ?? new ProviderStaffModel();
    }

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
            'role'        => $role ?? 'guest',
            'user_id'     => $userId,
            'provider_id' => null,
            'customer_id' => null,
        ];

        switch ($role) {
            case 'admin':
                // Admin sees all appointments — no filter.
                break;

            case 'provider':
                // Provider sees only their own appointments.
                $context['provider_id'] = $userId;
                break;

            case 'staff':
                // Staff sees appointments only for their actively-assigned providers.
                // applyContextScope() reads 'provider_id' and handles arrays via whereIn.
                // [0] when unassigned forces no results instead of showing everything.
                if ($userId) {
                    $assigned = $this->providerStaffModel->getProvidersForStaff($userId, 'active');
                    $context['provider_id'] = !empty($assigned)
                        ? array_map('intval', array_column($assigned, 'id'))
                        : [0];
                } else {
                    $context['provider_id'] = [0];
                }
                break;

            case 'customer':
                // Customer dashboard not yet implemented — scope to nothing.
                $context['customer_id'] = 0;
                break;

            default:
                // Unknown/guest — scope to nothing.
                $context['customer_id'] = 0;
                break;
        }

        return $context;
    }
}
