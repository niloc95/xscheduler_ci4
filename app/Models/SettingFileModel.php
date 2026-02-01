<?php

/**
 * =============================================================================
 * SETTING FILE MODEL
 * =============================================================================
 * 
 * @file        app/Models/SettingFileModel.php
 * @description Data model for binary file settings (logos, favicons, etc.).
 *              Stores files directly in database as BLOB data.
 * 
 * DATABASE TABLE: xs_settings_files
 * -----------------------------------------------------------------------------
 * Columns:
 * - id              : Primary key
 * - setting_key     : Unique identifier (e.g., 'branding.logo')
 * - filename        : Original filename
 * - mime            : MIME type (image/png, image/jpeg, etc.)
 * - data            : Binary file content (MEDIUMBLOB)
 * - updated_by      : User who last updated
 * - created_at      : Creation timestamp
 * - updated_at      : Last update timestamp
 * 
 * COMMON SETTING KEYS:
 * -----------------------------------------------------------------------------
 * - branding.logo          : Business logo image
 * - branding.favicon       : Browser favicon
 * - branding.login_bg      : Login page background
 * - branding.email_header  : Email template header
 * 
 * WHY DATABASE STORAGE:
 * -----------------------------------------------------------------------------
 * - Simpler deployment (no file system dependencies)
 * - Works with load balancers/multiple servers
 * - Easier backup (included in DB backup)
 * - Atomic updates
 * 
 * KEY METHODS:
 * -----------------------------------------------------------------------------
 * - upsert(key, filename, mime, data) : Insert or update file
 * - getByKey(key)                     : Retrieve file by key
 * 
 * SERVING FILES:
 * -----------------------------------------------------------------------------
 * Files are served via Assets controller:
 * GET /assets/settings-db/branding.logo
 * 
 * @see         app/Controllers/Assets.php for serving files
 * @see         app/Controllers/Settings.php for upload handling
 * @package     App\Models
 * @extends     CodeIgniter\Model
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

namespace App\Models;

use CodeIgniter\Model;

class SettingFileModel extends Model
{
    protected $table = 'xs_settings_files';
    protected $primaryKey = 'id';
    protected $allowedFields = ['setting_key','filename','mime','data','updated_by','created_at','updated_at'];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public function upsert(string $key, string $filename, string $mime, string $data, ?int $updatedBy = null): bool
    {
        $existing = $this->where('setting_key', $key)->first();
        $payload = [
            'setting_key' => $key,
            'filename'    => $filename,
            'mime'        => $mime,
            'data'        => $data,
        ];
        if ($updatedBy !== null) $payload['updated_by'] = $updatedBy;
        if ($existing) {
            return (bool) $this->update($existing['id'], $payload);
        }
        return (bool) $this->insert($payload, true);
    }

    public function getByKey(string $key): ?array
    {
        $row = $this->where('setting_key', $key)->first();
        return $row ?: null;
    }
}
