<?php

/**
 * =============================================================================
 * CATEGORY MODEL
 * =============================================================================
 * 
 * @file        app/Models/CategoryModel.php
 * @description Data model for service categories. Allows grouping services
 *              into logical categories for easier navigation.
 * 
 * DATABASE TABLE: xs_categories
 * -----------------------------------------------------------------------------
 * Columns:
 * - id              : Primary key
 * - name            : Category name
 * - description     : Optional description
 * - color           : Display color (hex, e.g., #FF5733)
 * - active          : Is category active (0/1)
 * - created_at      : Creation timestamp
 * - updated_at      : Last update timestamp
 * 
 * USAGE:
 * -----------------------------------------------------------------------------
 * Categories organize services into groups:
 * - "Hair" -> Haircut, Coloring, Styling
 * - "Nails" -> Manicure, Pedicure, Gel
 * - "Spa" -> Massage, Facial, Body Wrap
 * 
 * KEY METHODS:
 * -----------------------------------------------------------------------------
 * - withServiceCounts()   : Get categories with service count
 * - activate(id)          : Enable category
 * - deactivate(id)        : Disable category
 * - getActive()           : List active categories
 * 
 * VALIDATION RULES:
 * -----------------------------------------------------------------------------
 * - name: Required, 2-255 characters
 * - color: Optional, valid hex color (6 characters)
 * 
 * @see         app/Controllers/Services.php for admin UI
 * @see         app/Models/ServiceModel.php for services
 * @package     App\Models
 * @extends     BaseModel
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

namespace App\Models;

use App\Models\BaseModel;

class CategoryModel extends BaseModel
{
    protected $table            = 'xs_categories';
    protected $primaryKey       = 'id';
    protected $allowedFields    = [
        'name', 'description', 'color', 'active'
    ];

    // Dates
    // ...existing code...

    protected $validationRules = [
        'name' => 'required|min_length[2]|max_length[255]',
        'color' => 'permit_empty|regex_match[/^#?[0-9A-Fa-f]{6}$/]'
    ];

    /**
     * Return categories with a computed services_count column
     */
    public function withServiceCounts(): array
    {
        $servicesTable = $this->db->prefixTable('services');

        $builder = $this->db->table($this->table . ' c')
            ->select('c.*, COUNT(s.id) as services_count')
            ->join($servicesTable . ' s', 's.category_id = c.id', 'left')
            ->groupBy('c.id')
            ->orderBy('c.name', 'ASC');

        return $builder->get()->getResultArray();
    }

    public function activate(int $id): bool
    {
        return $this->update($id, ['active' => 1]);
    }

    public function deactivate(int $id): bool
    {
        return $this->update($id, ['active' => 0]);
    }
}
