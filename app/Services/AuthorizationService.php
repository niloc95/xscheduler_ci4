<?php

/**
 * =============================================================================
 * AUTHORIZATION SERVICE
 * =============================================================================
 * 
 * @file        app/Services/AuthorizationService.php
 * @description Centralized RBAC (Role-Based Access Control) service for
 *              checking user permissions across the application.
 * 
 * PURPOSE:
 * -----------------------------------------------------------------------------
 * Server-side permission enforcement:
 * - Check if user can perform specific actions
 * - Scope data access based on user role
 * - Validate resource ownership
 * - Provide consistent authorization logic
 * 
 * ROLE HIERARCHY:
 * -----------------------------------------------------------------------------
 * admin    : Full system access, no restrictions
 * provider : Access own data, schedules, staff
 * staff    : Limited access, assigned by provider
 * 
 * KEY METHODS:
 * -----------------------------------------------------------------------------
 * - canViewDashboard(role)        : Check dashboard access
 * - canManageUsers(role)          : Check user management access
 * - canManageAppointment(role, apptProviderId, userId)
 * - canViewCalendar(role, calendarProviderId, userId)
 * - canAccessProvider(role, targetProviderId, userId)
 * - isOwnerOrAdmin(role, resourceUserId, currentUserId)
 * 
 * USAGE:
 * -----------------------------------------------------------------------------
 *     $authService = new AuthorizationService();
 *     if (!$authService->canManageAppointment($role, $appt['provider_id'], $userId)) {
 *         throw new \Exception('Access denied');
 *     }
 * 
 * @see         app/Filters/RoleFilter.php for route-level protection
 * @see         app/Helpers/permissions_helper.php for helper functions
 * @package     App\Services
 * @author      Nilesh Nagin Cara
 * @copyright   2024-2026 Nilesh Nagin Cara
 * =============================================================================
 */

namespace App\Services;

/**
 * Authorization Service
 * 
 * Centralized authorization logic for role-based access control.
 * Enforces server-side permission checks across the application.
 * 
 * Role Hierarchy:
 * - Owner (admin): Full access, no restrictions
 * - Provider: Access only to own data and schedules
 * - Staff: Subset of Owner permissions (configured by Owner)
 * 
 * @package App\Services
 */
class AuthorizationService
{
    /**
     * Role constants
     */
    const ROLE_ADMIN = 'admin';
    const ROLE_PROVIDER = 'provider';
    const ROLE_STAFF = 'staff';

    /**
     * Resolve a single canonical role string from a string or multi-role array.
     * Returns the highest-privilege role present in the hierarchy.
     *
     * @param string|array $userRole Single role string or array of role strings
     * @return string
     */
    private function resolveRole(string|array $userRole): string
    {
        if (!is_array($userRole)) {
            return $userRole;
        }

        foreach ([self::ROLE_ADMIN, self::ROLE_PROVIDER, self::ROLE_STAFF] as $r) {
            if (in_array($r, $userRole, true)) {
                return $r;
            }
        }

        return self::ROLE_STAFF;
    }

    /**
     * Check if user can view dashboard metrics
     *
     * @param string|array $userRole User role or roles array
     * @return bool
     */
    public function canViewDashboardMetrics(string|array $userRole): bool
    {
        // All authenticated users can view dashboard metrics
        $role = $this->resolveRole($userRole);
        return in_array($role, [self::ROLE_ADMIN, self::ROLE_PROVIDER, self::ROLE_STAFF]);
    }

    /**
     * Check if user can view provider schedule
     *
     * @param string|array $userRole User role or roles array
     * @param int $userId Current user ID
     * @param int $targetProviderId Target provider ID to view
     * @return bool
     */
    public function canViewProviderSchedule(string|array $userRole, int $userId, int $targetProviderId): bool
    {
        $role = $this->resolveRole($userRole);

        // Admin can view any provider
        if ($role === self::ROLE_ADMIN) {
            return true;
        }

        // Provider can only view own schedule
        if ($role === self::ROLE_PROVIDER) {
            return $userId === $targetProviderId;
        }

        // Staff cannot view provider schedules (configurable)
        return false;
    }

    /**
     * Get provider scope for data filtering.
     *
     * Returns:
     * - null     for admin (no scope restriction — sees all data)
     * - int      for providers (restrict to own provider ID)
     * - int[]    for staff (list of assigned provider IDs; empty = no assignments)
     *
     * @param string|array $userRole User role or roles array
     * @param int|null     $providerId Provider ID when user is a provider
     * @param array|null   $user Full user data from session (required for staff scope)
     * @return int|int[]|null
     */
    public function getProviderScope(string|array $userRole, ?int $providerId = null, ?array $user = null): int|array|null
    {
        $role = $this->resolveRole($userRole);

        if ($role === self::ROLE_ADMIN) {
            return null; // No scope restriction
        }

        if ($role === self::ROLE_PROVIDER && $providerId !== null) {
            return $providerId; // Restrict to own data
        }

        if ($role === self::ROLE_STAFF && $user !== null) {
            $staffId = (int) ($user['id'] ?? session()->get('user_id') ?? 0);
            if ($staffId > 0) {
                $psm = new \App\Models\ProviderStaffModel();
                $rows = $psm->getProvidersForStaff($staffId, 'active');
                return array_map('intval', array_column($rows, 'id'));
            }
            return []; // Staff with no user data: empty panes
        }

        return []; // Unresolved staff: empty panes (no global fallback)
    }

    /**
     * Check if user can manage appointments
     *
     * @param string|array $userRole User role or roles array
     * @param int|null $appointmentProviderId Provider ID of appointment (null for create)
     * @param int|null $currentProviderId Current user's provider ID
     * @return bool
     */
    public function canManageAppointment(string|array $userRole, ?int $appointmentProviderId = null, ?int $currentProviderId = null): bool
    {
        $role = $this->resolveRole($userRole);

        // Admin can manage all appointments
        if ($role === self::ROLE_ADMIN) {
            return true;
        }

        // Provider can manage own appointments only
        if ($role === self::ROLE_PROVIDER && $currentProviderId !== null) {
            if ($appointmentProviderId === null) {
                // Creating new appointment for self
                return true;
            }
            // Editing existing appointment
            return $appointmentProviderId === $currentProviderId;
        }

        // Staff can manage any appointment they can see.
        // Scope (what they can see) is enforced at the data-query layer.
        if ($role === self::ROLE_STAFF) {
            return true;
        }

        return false;
    }

    /**
     * Check if user can view system settings
     *
     * @param string|array $userRole User role or roles array
     * @return bool
     */
    public function canViewSettings(string|array $userRole): bool
    {
        // Only admin can view/edit settings
        return $this->resolveRole($userRole) === self::ROLE_ADMIN;
    }

    /**
     * Check if user can manage users
     *
     * @param string|array $userRole User role or roles array
     * @return bool
     */
    public function canManageUsers(string|array $userRole): bool
    {
        // Only admin can manage users
        return $this->resolveRole($userRole) === self::ROLE_ADMIN;
    }

    /**
     * Check if user can view booking status
     *
     * @param string|array $userRole User role or roles array
     * @return bool
     */
    public function canViewBookingStatus(string|array $userRole): bool
    {
        // Only admin can view booking system status
        return $this->resolveRole($userRole) === self::ROLE_ADMIN;
    }

    /**
     * Check if user can view alerts
     *
     * @param string|array $userRole User role or roles array
     * @return bool
     */
    public function canViewAlerts(string|array $userRole): bool
    {
        $role = $this->resolveRole($userRole);
        // All authenticated users can view alerts
        return in_array($role, [self::ROLE_ADMIN, self::ROLE_PROVIDER, self::ROLE_STAFF]);
    }

    /**
     * Get filtered alert types for user role
     *
     * Different roles see different alert types.
     *
     * @param string|array $userRole User role or roles array
     * @return array Array of allowed alert types
     */
    public function getAllowedAlertTypes(string|array $userRole): array
    {
        $role = $this->resolveRole($userRole);

        if ($role === self::ROLE_ADMIN) {
            return [
                'confirmation_pending',
                'missing_hours',
                'booking_disabled',
                'blocked_periods',
                'overbooking',
            ];
        }

        if ($role === self::ROLE_PROVIDER) {
            return [
                'confirmation_pending',
                'overbooking',
            ];
        }

        // Staff sees minimal alerts
        return [];
    }

    /**
     * Check if user can view reports
     *
     * @param string|array $userRole User role or roles array
     * @return bool
     */
    public function canViewReports(string|array $userRole): bool
    {
        $role = $this->resolveRole($userRole);
        // Admin and providers can view reports
        return in_array($role, [self::ROLE_ADMIN, self::ROLE_PROVIDER]);
    }

    /**
     * Get user role from session or user data.
     * active_role takes precedence for multi-role users (Agent Context §4.3).
     *
     * @param array|null $user User data from session
     * @return string Role constant
     */
    public function getUserRole(?array $user): string
    {
        if (!$user) {
            return self::ROLE_STAFF; // Default to least privileged
        }

        // active_role is authoritative for users with multiple roles
        $role = $user['active_role'] ?? $user['role'] ?? 'staff';
        
        // Normalize role names
        if ($role === 'owner') {
            return self::ROLE_ADMIN;
        }

        return $role;
    }

    /**
     * Get provider ID from session or user data.
     * Checks the full roles array so admin+provider users are recognised as providers.
     *
     * @param array|null $user User data from session
     * @return int|null Provider ID or null
     */
    public function getProviderId(?array $user): ?int
    {
        if (!$user) {
            return null;
        }

        $roles = $user['roles'] ?? [$user['role'] ?? ''];
        if (!is_array($roles)) {
            $roles = [$roles];
        }

        if (in_array('provider', $roles, true)) {
            $providerId = (int) ($user['id'] ?? session()->get('user_id') ?? 0);

            return $providerId ?: null;
        }

        return null;
    }

    /**
     * Enforce authorization or throw exception
     * 
     * @param bool $authorized Authorization check result
     * @param string $message Error message
     * @throws \RuntimeException
     */
    public function enforce(bool $authorized, string $message = 'Access denied'): void
    {
        if (!$authorized) {
            throw new \RuntimeException($message);
        }
    }
}
