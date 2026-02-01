<?php

/**
 * =============================================================================
 * BASE MODEL
 * =============================================================================
 * 
 * @file        app/Models/BaseModel.php
 * @description Abstract base model providing shared configuration and utilities
 *              for all application models. Extends CodeIgniter's Model class.
 * 
 * PURPOSE:
 * -----------------------------------------------------------------------------
 * Provides consistent model configuration:
 * - Auto-increment primary keys
 * - Array return type for all queries
 * - Automatic timestamps (created_at, updated_at)
 * - Datetime format for date fields
 * - Field protection enabled by default
 * 
 * DEFAULT CONFIGURATION:
 * -----------------------------------------------------------------------------
 * - useAutoIncrement: true   - Primary keys auto-increment
 * - returnType: 'array'      - Results as arrays (not objects)
 * - useSoftDeletes: false    - Hard deletes by default
 * - protectFields: true      - Only allowedFields can be set
 * - useTimestamps: true      - Auto manage created_at/updated_at
 * - dateFormat: 'datetime'   - MySQL datetime format
 * 
 * USAGE:
 * -----------------------------------------------------------------------------
 * All application models extend this class:
 * 
 *     class MyModel extends BaseModel
 *     {
 *         protected $table = 'xs_my_table';
 *         protected $allowedFields = ['field1', 'field2'];
 *     }
 * 
 * @see         CodeIgniter\Model for parent class documentation
 * @package     App\Models
 * @extends     CodeIgniter\Model
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

namespace App\Models;

use CodeIgniter\Model;

/**
 * BaseModel for shared CRUD config and utilities
 */
class BaseModel extends Model
{
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $useTimestamps    = true;
    protected $dateFormat       = 'datetime';
    protected $createdField     = 'created_at';
    protected $updatedField     = 'updated_at';

    // Optionally add shared utility methods here
}
