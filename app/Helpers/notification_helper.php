<?php

if (!function_exists('notification_encrypt_config')) {
    /**
     * Encrypt notification configuration payload for storage.
     */
    function notification_encrypt_config(array $config, string $label): string
    {
        $encrypter = service('encrypter');
        $json = json_encode($config);
        if ($json === false) {
            throw new \RuntimeException('Failed to encode ' . $label . ' config');
        }
        // Base64 encode the binary encrypted data for safe storage in TEXT column
        return base64_encode((string) $encrypter->encrypt($json));
    }
}

if (!function_exists('notification_decrypt_config')) {
    /**
     * Decrypt config, returning ['data' => array, 'error' => string|null] when $returnError is true.
     */
    function notification_decrypt_config($encrypted, bool $returnError = false, string $logContext = 'NotificationService'): array
    {
        if (!is_string($encrypted) || trim($encrypted) === '') {
            return $returnError ? ['data' => [], 'error' => null] : [];
        }

        try {
            $encrypter = service('encrypter');
            // Base64 decode before decrypting
            $decoded = base64_decode($encrypted, true);
            if ($decoded === false) {
                throw new \RuntimeException('Failed to base64 decode encrypted config');
            }
            $json = $encrypter->decrypt($decoded);
            $data = json_decode((string) $json, true);
            $result = is_array($data) ? $data : [];
            return $returnError ? ['data' => $result, 'error' => null] : $result;
        } catch (\Throwable $e) {
            log_message('error', $logContext . ': decrypt failed: {msg}', ['msg' => $e->getMessage()]);
            $errMsg = 'encryption_key_mismatch';
            if (stripos($e->getMessage(), 'authentication failed') !== false) {
                $errMsg = 'encryption_key_mismatch';
            }
            return $returnError ? ['data' => [], 'error' => $errMsg] : [];
        }
    }
}

if (!function_exists('notification_get_reminder_offset_minutes')) {
    /**
     * Fetch reminder offset minutes for a channel, clamped to 0..43200.
     */
    function notification_get_reminder_offset_minutes(int $businessId, string $channel): ?int
    {
        $model = new \App\Models\BusinessNotificationRuleModel();
        $row = $model
            ->where('business_id', $businessId)
            ->where('event_type', 'appointment_reminder')
            ->where('channel', $channel)
            ->first();

        $v = $row['reminder_offset_minutes'] ?? null;
        if ($v === null || $v === '') {
            return null;
        }

        $minutes = (int) $v;
        return max(0, min(43200, $minutes));
    }
}
