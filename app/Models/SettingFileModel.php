<?php

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
