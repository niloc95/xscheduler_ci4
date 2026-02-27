<?php

/**
 * =============================================================================
 * CONFLICT SERVICE
 * =============================================================================
 *
 * @file        app/Services/ConflictService.php
 * @description Checks for scheduling conflicts between appointments and
 *              blocked time periods. Extracted from AvailabilityService to
 *              provide a single-responsibility conflict-detection API.
 *
 * KEY METHODS:
 * -----------------------------------------------------------------------------
 * - getConflictingAppointments()  Find appointments that overlap a time range
 * - hasConflict()                 Quick boolean check for conflicts
 * - getBlockedTimesForPeriod()    Find blocked times that overlap a time range
 *
 * IMPORTANT: All datetime parameters for appointment queries must be in UTC
 *            (matches DB storage). Blocked times remain in local TZ.
 *
 * @see         app/Services/AvailabilityService.php — primary consumer
 * @package     App\Services
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

namespace App\Services;

use App\Models\AppointmentModel;
use App\Models\BlockedTimeModel;

class ConflictService
{
    private AppointmentModel $appointmentModel;
    private BlockedTimeModel $blockedTimeModel;

    public function __construct(
        ?AppointmentModel $appointmentModel = null,
        ?BlockedTimeModel $blockedTimeModel = null
    ) {
        $this->appointmentModel = $appointmentModel ?? new AppointmentModel();
        $this->blockedTimeModel = $blockedTimeModel ?? new BlockedTimeModel();
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * Quick boolean: does the provider have a conflicting appointment?
     *
     * @param int      $providerId
     * @param string   $startUtc   UTC datetime 'Y-m-d H:i:s'
     * @param string   $endUtc     UTC datetime 'Y-m-d H:i:s'
     * @param int|null $excludeAppointmentId  Appointment to exclude (reschedule)
     * @param int|null $locationId            Scope to location
     */
    public function hasConflict(
        int $providerId,
        string $startUtc,
        string $endUtc,
        ?int $excludeAppointmentId = null,
        ?int $locationId = null
    ): bool {
        return !empty($this->getConflictingAppointments(
            $providerId,
            $startUtc,
            $endUtc,
            $excludeAppointmentId,
            $locationId
        ));
    }

    /**
     * Return all appointments that overlap the given UTC time range.
     *
     * Uses three-clause overlap logic:
     *   1. Existing starts before new ends AND existing ends after new starts
     *   2. Existing starts during new range
     *   3. New range contains existing entirely
     *
     * @param int      $providerId
     * @param string   $startUtc   UTC datetime 'Y-m-d H:i:s'
     * @param string   $endUtc     UTC datetime 'Y-m-d H:i:s'
     * @param int|null $excludeAppointmentId
     * @param int|null $locationId
     * @return array   Conflicting appointment rows
     */
    public function getConflictingAppointments(
        int $providerId,
        string $startUtc,
        string $endUtc,
        ?int $excludeAppointmentId = null,
        ?int $locationId = null
    ): array {
        $builder = $this->appointmentModel->builder();

        $builder->where('provider_id', $providerId)
                ->where('status !=', 'cancelled')
                ->groupStart()
                    // New starts during existing
                    ->groupStart()
                        ->where('start_at <=', $startUtc)
                        ->where('end_at >', $startUtc)
                    ->groupEnd()
                    // New ends during existing
                    ->orGroupStart()
                        ->where('start_at <', $endUtc)
                        ->where('end_at >=', $endUtc)
                    ->groupEnd()
                    // New contains existing
                    ->orGroupStart()
                        ->where('start_at >=', $startUtc)
                        ->where('end_at <=', $endUtc)
                    ->groupEnd()
                ->groupEnd();

        if ($excludeAppointmentId) {
            $builder->where('id !=', $excludeAppointmentId);
        }

        if ($locationId !== null) {
            $builder->where('location_id', $locationId);
        }

        return $builder->get()->getResultArray();
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * Return blocked time entries that overlap the given UTC time range.
     *
     * Returns both provider-specific blocks (matching $providerId) and
     * global blocks (provider_id IS NULL), e.g. public holidays.
     *
     * @param int    $providerId
     * @param string $startUtc   UTC datetime 'Y-m-d H:i:s'
     * @param string $endUtc     UTC datetime 'Y-m-d H:i:s'
     * @return array Blocked time rows
     */
    public function getBlockedTimesForPeriod(
        int $providerId,
        string $startUtc,
        string $endUtc
    ): array {
        return $this->blockedTimeModel
            ->groupStart()
                ->where('provider_id', $providerId)
                ->orWhere('provider_id', null)
            ->groupEnd()
            ->groupStart()
                ->groupStart()
                    ->where('start_at <=', $startUtc)
                    ->where('end_at >',   $startUtc)
                ->groupEnd()
                ->orGroupStart()
                    ->where('start_at <',  $endUtc)
                    ->where('end_at >=',   $endUtc)
                ->groupEnd()
                ->orGroupStart()
                    ->where('start_at >=', $startUtc)
                    ->where('end_at <=',   $endUtc)
                ->groupEnd()
            ->groupEnd()
            ->findAll();
    }
}
