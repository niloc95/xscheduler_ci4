<?php

/**
 * =============================================================================
 * BLOCKED TIME MODEL
 * =============================================================================
 * 
 * @file        app/Models/BlockedTimeModel.php
 * @description Data model for provider time blocks. Allows providers to block
 *              specific time periods when they're unavailable.
 * 
 * DATABASE TABLE: xs_blocked_times
 * -----------------------------------------------------------------------------
 * Columns:
 * - id              : Primary key
 * - provider_id     : FK to xs_users (provider); NULL = global block
 * - start_at        : Block start datetime (UTC)
 * - end_at          : Block end datetime (UTC)
 * - reason          : Why time is blocked (vacation, meeting, etc.)
 * - created_at      : Creation timestamp
 * - updated_at      : Last update timestamp
 * 
 * PURPOSE:
 * -----------------------------------------------------------------------------
 * Providers can block time for:
 * - Vacations/holidays
 * - Personal appointments
 * - Meetings
 * - Lunch breaks (one-time, not recurring)
 * - Any unavailability
 * 
 * AVAILABILITY IMPACT:
 * -----------------------------------------------------------------------------
 * When calculating available slots, blocked times are excluded:
 * - Overlapping slots become unavailable
 * - Works with both full day and partial blocks
 * 
 * DIFFERENCE FROM SCHEDULE:
 * -----------------------------------------------------------------------------
 * - Schedule: Recurring weekly working hours
 * - Blocked Time: One-time specific unavailability
 * 
 * @see         app/Services/AvailabilityService.php for availability checks
 * @see         app/Models/ProviderScheduleModel.php for recurring schedule
 * @package     App\Models
 * @extends     CodeIgniter\Model
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

namespace App\Models;

use CodeIgniter\Model;

class BlockedTimeModel extends Model
{
    protected $table            = 'xs_blocked_times';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'provider_id', 'start_at', 'end_at', 'reason'
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
