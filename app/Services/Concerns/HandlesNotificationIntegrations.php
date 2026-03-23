<?php

namespace App\Services\Concerns;

use App\Models\BusinessIntegrationModel;

trait HandlesNotificationIntegrations
{
    abstract protected function integrationEncryptionLabel(): string;

    abstract protected function integrationDecryptContext(): string;

    private function getIntegrationRow(int $businessId): ?array
    {
        try {
            $model = new BusinessIntegrationModel();
            $row = $model
                ->where('business_id', $businessId)
                ->where('channel', static::CHANNEL)
                ->first();

            return is_array($row) ? $row : null;
        } catch (\Throwable $e) {
            log_message('debug', static::class . '::getIntegrationRow - table unavailable: ' . $e->getMessage());
            return null;
        }
    }

    private function encryptConfig(array $config): string
    {
        helper('notification');
        return notification_encrypt_config($config, $this->integrationEncryptionLabel());
    }

    private function decryptConfig($encrypted, bool $returnError = false): array
    {
        helper('notification');
        return notification_decrypt_config($encrypted, $returnError, $this->integrationDecryptContext());
    }

    private function updateHealth(BusinessIntegrationModel $model, array $integration, string $healthStatus, string $testedAt): void
    {
        $payload = [
            'health_status' => $healthStatus,
            'last_tested_at' => $testedAt,
        ];

        if (!empty($integration['id'])) {
            $model->update((int) $integration['id'], $payload);
        }
    }

    private function isValidE164(string $phone): bool
    {
        return (bool) preg_match('/^\+[1-9]\d{7,14}$/', $phone);
    }
}