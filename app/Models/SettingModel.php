<?php

/**
 * =============================================================================
 * SETTING MODEL
 * =============================================================================
 * 
 * @file        app/Models/SettingModel.php
 * @description Data model for application settings stored as key-value pairs.
 *              Supports typed values and prefix-based organization.
 * 
 * DATABASE TABLE: xs_settings
 * -----------------------------------------------------------------------------
 * Columns:
 * - id              : Primary key
 * - setting_key     : Unique setting identifier (e.g., 'general.business_name')
 * - setting_value   : Value stored as text
 * - setting_type    : Value type (string, integer, boolean, json)
 * - updated_by      : User ID who last updated (optional)
 * - created_at      : Creation timestamp
 * - updated_at      : Last update timestamp
 * 
 * SETTING KEY PREFIXES:
 * -----------------------------------------------------------------------------
 * - general.*       : Business info, contact details
 * - localization.*  : Timezone, date format, currency
 * - booking.*       : Booking rules, slot duration, buffer time
 * - calendar.*      : Calendar display settings
 * - notifications.* : Email, SMS, WhatsApp config
 * - branding.*      : Logo, colors, theme
 * - security.*      : Security-related settings
 * - database.*      : Database/backup settings
 * 
 * KEY METHODS:
 * -----------------------------------------------------------------------------
 * - getByKeys(array)    : Get multiple settings at once
 * - getByPrefix(string) : Get all settings with prefix
 * - getValue(key)       : Get single setting value
 * - setValue(key, val)  : Set single setting
 * - castValue()         : Cast value to correct type
 * 
 * VALUE TYPES:
 * -----------------------------------------------------------------------------
 * - string  : Plain text
 * - integer : Numeric value
 * - boolean : true/false (stored as 1/0)
 * - json    : JSON object/array
 * 
 * @see         app/Controllers/Settings.php for admin UI
 * @see         app/Services/LocalizationSettingsService.php
 * @package     App\Models
 * @extends     BaseModel
 * @author      Nilesh Nagin Cara
 * @copyright   2024-2026 Nilesh Nagin Cara
 * =============================================================================
 */

namespace App\Models;

use App\Models\BaseModel;

class SettingModel extends BaseModel
{
    protected $table = 'xs_settings';
    protected $primaryKey = 'id';

    private static ?bool $hasUpdatedByColumn = null;

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
     * Request-scoped in-memory cache for individual setting values.
     * Keyed by setting_key. Populated lazily on first fetch.
     */
    private static array $requestCache = [];

    /**
     * Get settings by keys (array) preserving associative mapping.
     * Results are cached for the duration of the request to avoid
     * redundant queries when the same keys are fetched repeatedly.
     */
    public function getByKeys(array $keys): array
    {
        if (empty($keys)) return [];

        $missing = array_diff($keys, array_keys(self::$requestCache));

        if (!empty($missing)) {
            $rows = $this->select(['setting_key', 'setting_value', 'setting_type'])
                ->whereIn('setting_key', $missing)
                ->findAll();
            foreach ($rows as $r) {
                self::$requestCache[$r['setting_key']] = $this->castValue($r['setting_value'], $r['setting_type'] ?? 'string');
            }
            // Ensure missing keys that had no row are stored as null so we don't re-query
            foreach ($missing as $k) {
                if (!array_key_exists($k, self::$requestCache)) {
                    self::$requestCache[$k] = null;
                }
            }
        }

        $out = [];
        foreach ($keys as $k) {
            $out[$k] = self::$requestCache[$k] ?? null;
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
     * Get all settings as a typed key=>value map.
     */
    public function getAllAsMap(): array
    {
        $rows = $this->select(['setting_key', 'setting_value', 'setting_type'])->findAll();
        $out = [];

        foreach ($rows as $row) {
            $out[$row['setting_key']] = $this->castValue($row['setting_value'], $row['setting_type'] ?? 'string');
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

        // Production safety: some environments may not have the `updated_by` column yet.
        if ($updatedBy !== null && $this->hasUpdatedByColumn()) {
            $payload['updated_by'] = $updatedBy;
        }
        $existing = $this->where('setting_key', $key)->first();
        if ($existing) {
            $result = (bool) $this->update($existing['id'], $payload);
        } else {
            $result = (bool) $this->insert($payload, true);
        }
        // Invalidate request cache so the updated value is visible immediately
        unset(self::$requestCache[$key]);
        return $result;
    }

    /**
     * Drop the in-memory settings cache.
     *
     * The cache is process-static, which is correct for a single HTTP request but
     * outlives it in long-running contexts (spark workers, test runs). Call this
     * after writing settings rows by any route that bypasses upsert() — raw
     * query-builder writes, migrations, seeders, fixtures — otherwise readers keep
     * serving the pre-write value.
     *
     * @param string|null $key Clear one key, or the whole cache when null.
     */
    public static function clearRequestCache(?string $key = null): void
    {
        if ($key === null) {
            self::$requestCache = [];

            return;
        }

        unset(self::$requestCache[$key]);
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
            'json' => $this->encodeJsonValue($val),
            'bool','boolean' => $val ? 'true' : 'false',
            default => (string) $val,
        };
    }

    private function hasUpdatedByColumn(): bool
    {
        try {
            $database = $this->db->getDatabase();
            $tableName = $this->table;
            $query = $this->db->query(
                'SELECT 1 FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ? LIMIT 1',
                [$database, $tableName, 'updated_by']
            );

            return $query->getNumRows() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function encodeJsonValue($val): string
    {
        $encoded = json_encode($val, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            log_message('warning', 'SettingModel: json_encode failed for setting value: {msg}', [
                'msg' => json_last_error_msg(),
            ]);
            // Avoid fatal TypeError in production; store a safe placeholder.
            return 'null';
        }
        return $encoded;
    }
}
