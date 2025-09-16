<?php

namespace App\Models;

use App\Models\BaseModel;

class SettingModel extends BaseModel
{
    protected $table = 'settings';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'setting_key',
        'setting_value',
        'setting_type',
    'updated_by',
        'created_at',
        'updated_at',
    ];

    // ...existing code...

    /**
     * Get settings by keys (array) preserving associative mapping.
     */
    public function getByKeys(array $keys): array
    {
        if (empty($keys)) return [];
        $rows = $this->select(['setting_key', 'setting_value', 'setting_type'])
            ->whereIn('setting_key', $keys)
            ->findAll();
        $out = [];
        foreach ($rows as $r) {
            $out[$r['setting_key']] = $this->castValue($r['setting_value'], $r['setting_type'] ?? 'string');
        }
        return $out;
    }

    /**
     * Get settings by prefix (e.g., 'general.') returning key=>value map.
     */
    public function getByPrefix(string $prefix): array
    {
        $rows = $this->select(['setting_key', 'setting_value', 'setting_type'])
            ->like('setting_key', $prefix, 'after')
            ->findAll();
        $out = [];
        foreach ($rows as $r) {
            $out[$r['setting_key']] = $this->castValue($r['setting_value'], $r['setting_type'] ?? 'string');
        }
        return $out;
    }

    /**
     * Upsert a single setting.
     */
    public function upsert(string $key, $value, string $type = 'string', ?int $updatedBy = null): bool
    {
        $payload = [
            'setting_key' => $key,
            'setting_value' => $this->serializeValue($value, $type),
            'setting_type' => $type,
        ];
        if ($updatedBy !== null) {
            $payload['updated_by'] = $updatedBy;
        }
        $existing = $this->where('setting_key', $key)->first();
        if ($existing) {
            return (bool) $this->update($existing['id'], $payload);
        }
        return (bool) $this->insert($payload, true);
    }

    private function castValue(?string $val, string $type)
    {
        if ($val === null) return null;
        return match ($type) {
            'int','integer' => (int) $val,
            'float','double' => (float) $val,
            'bool','boolean' => in_array(strtolower($val), ['1','true','yes','on'], true),
            'json' => json_decode($val, true),
            default => $val,
        };
    }

    private function serializeValue($val, string $type): string
    {
        return match ($type) {
            'json' => json_encode($val, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'bool','boolean' => $val ? 'true' : 'false',
            default => (string) $val,
        };
    }
}
