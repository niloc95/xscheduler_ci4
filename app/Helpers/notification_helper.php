<?php

/**
 * =============================================================================
 * NOTIFICATION HELPER
 * =============================================================================
 * 
 * @file        app/Helpers/notification_helper.php
 * @description Helper functions for notification system encryption,
 *              configuration handling, and reminder scheduling.
 * 
 * LOADING:
 * -----------------------------------------------------------------------------
 * Load manually where needed:
 *     helper('notification');
 * 
 * AVAILABLE FUNCTIONS:
 * -----------------------------------------------------------------------------
 * notification_encrypt_config($config, $label)
 *   Encrypt notification configuration for secure database storage
 *   Used for SMTP passwords, API keys, etc.
 * 
 * notification_decrypt_config($encrypted, $returnError, $logContext)
 *   Decrypt notification configuration from database
 *   Handles errors gracefully with logging
 * 
 * notification_get_reminder_offset_minutes($businessId, $channel)
 *   Get reminder timing configuration for a business/channel
 *   Returns minutes before appointment (0-43200)
 * 
 * ENCRYPTION FLOW:
 * -----------------------------------------------------------------------------
 * 1. Config array -> JSON encode
 * 2. JSON string -> Encrypt with CI4 encrypter
 * 3. Encrypted binary -> Base64 encode for DB TEXT column
 * 
 * On decrypt: Reverse the process with error handling.
 * 
 * ERROR HANDLING:
 * -----------------------------------------------------------------------------
 * The decrypt function can return error codes:
 * - 'encryption_key_mismatch' : Key changed, can't decrypt
 * - null : Success or empty input
 * 
 * REMINDER CHANNELS:
 * -----------------------------------------------------------------------------
 * - email     : Email reminders
 * - sms       : SMS text reminders
 * - whatsapp  : WhatsApp message reminders
 * 
 * @see         app/Services/NotificationEmailService.php
 * @see         app/Services/NotificationSmsService.php
 * @see         app/Models/BusinessNotificationRuleModel.php
 * @package     App\Helpers
 * @author      Nilesh Nagin Cara
 * @copyright   2024-2026 Nilesh Nagin Cara
 * =============================================================================
 */

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
     * Fetch reminder offsets for a channel as an ordered list of minutes.
     *
     * Backward compatibility:
     * - Uses reminder_offsets_json when present and valid.
     * - Falls back to reminder_offset_minutes when JSON is empty.
     *
     * @return array<int, int>
     */
    function notification_get_reminder_offsets_minutes(int $businessId, string $channel): array
    {
        $model = new \App\Models\BusinessNotificationRuleModel();
        $row = $model
            ->where('business_id', $businessId)
            ->where('event_type', 'appointment_reminder')
            ->where('channel', $channel)
            ->first();

        if (!is_array($row)) {
            return [];
        }

        $offsets = [];

        $jsonRaw = $row['reminder_offsets_json'] ?? null;
        if (is_string($jsonRaw) && trim($jsonRaw) !== '') {
            $decoded = json_decode($jsonRaw, true);
            if (is_array($decoded)) {
                foreach ($decoded as $value) {
                    if (!is_numeric($value)) {
                        continue;
                    }
                    $offsets[] = max(0, min(43200, (int) $value));
                }
            }
        }

        if ($offsets === []) {
            $legacy = $row['reminder_offset_minutes'] ?? null;
            if ($legacy !== null && $legacy !== '' && is_numeric($legacy)) {
                $offsets[] = max(0, min(43200, (int) $legacy));
            }
        }

        if ($offsets === []) {
            return [];
        }

        // De-duplicate while preserving configured order.
        $seen = [];
        $normalized = [];
        foreach ($offsets as $offset) {
            if (isset($seen[$offset])) {
                continue;
            }
            $seen[$offset] = true;
            $normalized[] = $offset;
        }

        return $normalized;
    }

    /**
     * Fetch reminder offset minutes for a channel, clamped to 0..43200.
     */
    function notification_get_reminder_offset_minutes(int $businessId, string $channel): ?int
    {
        $offsets = notification_get_reminder_offsets_minutes($businessId, $channel);
        if ($offsets === []) {
            return null;
        }

        // Compatibility choice: return nearest reminder offset.
        return min($offsets);
    }
}
