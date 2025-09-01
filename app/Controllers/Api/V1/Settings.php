<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Models\SettingModel;
use CodeIgniter\HTTP\ResponseInterface;

class Settings extends BaseController
{
    protected $model;

    public function __construct()
    {
        $this->model = new SettingModel();
    }

    /**
     * GET /api/v1/settings?prefix=general.
     * Returns a flat key=>value map.
     */
    public function index()
    {
        $prefix = $this->request->getGet('prefix');
        if ($prefix) {
            $data = $this->model->getByPrefix($prefix);
        } else {
            // Return all settings as a map
            $rows = $this->model->select(['setting_key', 'setting_value', 'setting_type'])->findAll();
            $data = [];
            foreach ($rows as $r) {
                $data[$r['setting_key']] = $this->model->getByKeys([$r['setting_key']])[$r['setting_key']] ?? null;
            }
        }
        return $this->response->setJSON(['ok' => true, 'data' => $data]);
    }

    /**
     * PUT /api/v1/settings (bulk upsert)
     * Body: { "general.company_name":"Acme", ... }
     */
    public function update()
    {
        $payload = $this->request->getJSON(true) ?? [];
        if (!is_array($payload)) {
            return $this->failValidationErrors('Invalid JSON payload');
        }
        $userId = session()->get('user_id');
        $count = 0;
        foreach ($payload as $key => $value) {
            // Infer type: JSON if array/object; boolean if true/false; else string
            $type = is_array($value) ? 'json' : (is_bool($value) ? 'bool' : 'string');
            if ($this->model->upsert($key, $value, $type, $userId)) {
                $count++;
            }
        }
        return $this->response->setJSON(['ok' => true, 'updated' => $count]);
    }
}
