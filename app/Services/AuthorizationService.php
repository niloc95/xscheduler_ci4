<?php

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
     * Check if user can view dashboard metrics
     * 
     * @param string $userRole User role
     * @return bool
     */
    public function canViewDashboardMetrics(string $userRole): bool
    {
        // All authenticated users can view dashboard metrics
        return in_array($userRole, [self::ROLE_ADMIN, self::ROLE_PROVIDER, self::ROLE_STAFF]);
    }

    /**
     * Check if user can view provider schedule
     * 
     * @param string $userRole User role
     * @param int $userId Current user ID
     * @param int $targetProviderId Target provider ID to view
     * @return bool
     */
    public function canViewProviderSchedule(string $userRole, int $userId, int $targetProviderId): bool
    {
        // Admin can view any provider
        if ($userRole === self::ROLE_ADMIN) {
            return true;
        }

        // Provider can only view own schedule
        if ($userRole === self::ROLE_PROVIDER) {
            return $userId === $targetProviderId;
        }

        // Staff cannot view provider schedules (configurable)
        return false;
    }

    /**
     * Get provider scope for data filtering
     * 
     * Returns:
     * - null for admin (no scope restriction)
     * - provider ID for providers (restrict to own data)
     * - null for staff (handled by specific permissions)
     * 
     * @param string $userRole User role
     * @param int|null $providerId Provider ID if user is provider
     * @return int|null Provider ID for scope or null for admin
     */
    public function getProviderScope(string $userRole, ?int $providerId = null): ?int
    {
        if ($userRole === self::ROLE_ADMIN) {
            return null; // No scope restriction
        }

        if ($userRole === self::ROLE_PROVIDER && $providerId !== null) {
            return $providerId; // Restrict to own data
        }

        return null; // Staff sees no data by default (requires explicit permission)
    }

    /**
     * Check if user can manage appointments
     * 
     * @param string $userRole User role
     * @param int|null $appointmentProviderId Provider ID of appointment (null for create)
     * @param int|null $currentProviderId Current user's provider ID
     * @return bool
     */
    public function canManageAppointment(string $userRole, ?int $appointmentProviderId = null, ?int $currentProviderId = null): bool
    {
        // Admin can manage all appointments
        if ($userRole === self::ROLE_ADMIN) {
            return true;
        }

        // Provider can manage own appointments only
        if ($userRole === self::ROLE_PROVIDER && $currentProviderId !== null) {
            if ($appointmentProviderId === null) {
                // Creating new appointment for self
                return true;
            }
            // Editing existing appointment
            return $appointmentProviderId === $currentProviderId;
        }

        return false;
    }

    /**
     * Check if user can view system settings
     * 
     * @param string $userRole User role
     * @return bool
     */
    public function canViewSettings(string $userRole): bool
    {
        // Only admin can view/edit settings
        return $userRole === self::ROLE_ADMIN;
    }

    /**
     * Check if user can manage users
     * 
     * @param string $userRole User role
     * @return bool
     */
    public function canManageUsers(string $userRole): bool
    {
        // Only admin can manage users
        return $userRole === self::ROLE_ADMIN;
    }

    /**
     * Check if user can view booking status
     * 
     * @param string $userRole User role
     * @return bool
     */
    public function canViewBookingStatus(string $userRole): bool
    {
        // Only admin can view booking system status
        return $userRole === self::ROLE_ADMIN;
    }

    /**
     * Check if user can view alerts
     * 
     * @param string $userRole User role
     * @return bool
     */
    public function canViewAlerts(string $userRole): bool
    {
        // All authenticated users can view alerts
        return in_array($userRole, [self::ROLE_ADMIN, self::ROLE_PROVIDER, self::ROLE_STAFF]);
    }

    /**
     * Get filtered alert types for user role
     * 
     * Different roles see different alert types.
     * 
     * @param string $userRole User role
     * @return array Array of allowed alert types
     */
    public function getAllowedAlertTypes(string $userRole): array
    {
        if ($userRole === self::ROLE_ADMIN) {
            return [
                'confirmation_pending',
                'missing_hours',
                'booking_disabled',
                'blocked_periods',
                'overbooking',
            ];
        }

        if ($userRole === self::ROLE_PROVIDER) {
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
     * @param string $userRole User role
     * @return bool
     */
    public function canViewReports(string $userRole): bool
    {
        // Admin and providers can view reports
        return in_array($userRole, [self::ROLE_ADMIN, self::ROLE_PROVIDER]);
    }

    /**
     * Get user role from session or user data
     * 
     * @param array|null $user User data from session
     * @return string Role constant
     */
    public function getUserRole(?array $user): string
    {
        if (!$user) {
            return self::ROLE_STAFF; // Default to least privileged
        }

        $role = $user['role'] ?? 'staff';
        
        // Normalize role names
        if ($role === 'owner') {
            return self::ROLE_ADMIN;
        }

        return $role;
    }

    /**
     * Get provider ID from session or user data
     * 
     * @param array|null $user User data from session
     * @return int|null Provider ID or null
     */
    public function getProviderId(?array $user): ?int
    {
        if (!$user) {
            return null;
        }

        // If user is provider, return their user ID
        if (($user['role'] ?? '') === 'provider') {
            return $user['id'] ?? null;
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
