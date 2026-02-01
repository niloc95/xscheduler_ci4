<?php

/**
 * =============================================================================
 * BUSINESS HOUR MODEL
 * =============================================================================
 * 
 * @file        app/Models/BusinessHourModel.php
 * @description Data model for provider business hours. Alternative/legacy
 *              schedule storage using numeric weekday format.
 * 
 * DATABASE TABLE: xs_business_hours
 * -----------------------------------------------------------------------------
 * Columns:
 * - id              : Primary key
 * - provider_id     : FK to xs_users (provider)
 * - weekday         : Day of week (0=Sunday, 6=Saturday)
 * - start_time      : Working start time
 * - end_time        : Working end time
 * - breaks_json     : JSON array of break periods
 * - created_at      : Creation timestamp
 * - updated_at      : Last update timestamp
 * 
 * WEEKDAY VALUES:
 * -----------------------------------------------------------------------------
 * - 0 = Sunday
 * - 1 = Monday
 * - 2 = Tuesday
 * - 3 = Wednesday
 * - 4 = Thursday
 * - 5 = Friday
 * - 6 = Saturday
 * 
 * BREAKS_JSON FORMAT:
 * -----------------------------------------------------------------------------
 * [
 *   { "start": "12:00", "end": "13:00" },
 *   { "start": "15:00", "end": "15:30" }
 * ]
 * 
 * NOTE:
 * -----------------------------------------------------------------------------
 * This model uses numeric weekday (0-6) format.
 * See ProviderScheduleModel for string-based weekday format.
 * 
 * @see         app/Models/ProviderScheduleModel.php for primary schedule model
 * @see         app/Services/AvailabilityService.php for availability checks
 * @package     App\Models
 * @extends     CodeIgniter\Model
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

namespace App\Models;

use CodeIgniter\Model;

class BusinessHourModel extends Model
{
    protected $table            = 'xs_business_hours';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'provider_id', 'weekday', 'start_time', 'end_time', 'breaks_json'
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'provider_id' => 'required|integer',
        'weekday'     => 'required|integer|greater_than_equal_to[0]|less_than_equal_to[6]',
        'start_time'  => 'required',
        'end_time'    => 'required',
    ];
}
