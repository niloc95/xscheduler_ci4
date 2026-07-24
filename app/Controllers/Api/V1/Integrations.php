<?php

namespace App\Controllers\Api\V1;

use App\Controllers\Api\BaseApiController;
use App\Services\Settings\IntegrationSettingsService;

/**
 * Integration hub API — all actions delegate to IntegrationSettingsService.
 *
 * Routes (api_auth filter, under /api/v1/):
 *   GET  integrations            — returns all integration statuses
 *   POST integrations/save       — save config for one channel
 *   POST integrations/test       — test connection for one channel
 *   POST integrations/disconnect — remove integration row for one channel
 */
class Integrations extends BaseApiController
{
    private ?IntegrationSettingsService $service = null;

    public function index()
    {
        try {
            $data = $this->getService()->getIndexData();
            return $this->ok($data);
        } catch (\Throwable $e) {
            log_message('error', 'Api\\V1\\Integrations::index - ' . $e->getMessage());
            return $this->error(500, 'Failed to load integration data.');
        }
    }

    public function save()
    {
        return $this->handleIntent('save');
    }

    public function test()
    {
        return $this->handleIntent('test');
    }

    public function disconnect()
    {
        return $this->handleIntent('disconnect');
    }

    private function handleIntent(string $intent)
    {
        $body    = $this->request->getJSON(true) ?? $this->request->getPost() ?? [];
        $channel = trim((string) ($body['channel'] ?? ''));

        if ($channel === '') {
            return $this->error(422, 'channel is required.');
        }

        $body['intent'] = $intent;

        try {
            $result = $this->getService()->save($body, current_user_id());

            if ($result['type'] === 'error') {
                return $this->error(422, $result['message']);
            }

            return $this->ok(['message' => $result['message']]);
        } catch (\Throwable $e) {
            log_message('error', 'Api\\V1\\Integrations::' . $intent . ' - ' . $e->getMessage());
            return $this->error(500, 'Integration operation failed.');
        }
    }

    private function getService(): IntegrationSettingsService
    {
        if ($this->service === null) {
            $this->service = new IntegrationSettingsService();
        }
        return $this->service;
    }
}
