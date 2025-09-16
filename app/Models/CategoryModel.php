<?php

namespace App\Models;

use App\Models\BaseModel;

class CategoryModel extends BaseModel
{
    protected $table            = 'categories';
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
        $builder = $this->db->table($this->table . ' c')
            ->select('c.*, COUNT(s.id) as services_count')
            ->join('services s', 's.category_id = c.id', 'left')
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
