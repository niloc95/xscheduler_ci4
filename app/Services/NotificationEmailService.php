<?php

/**
 * =============================================================================
 * NOTIFICATION EMAIL SERVICE
 * =============================================================================
 * 
 * @file        app/Services/NotificationEmailService.php
 * @description Handles sending email notifications using configured SMTP
 *              integrations. Manages email delivery for all notification types.
 * 
 * PURPOSE:
 * -----------------------------------------------------------------------------
 * Provides email delivery capability for appointment notifications:
 * - Confirmations
 * - Reminders
 * - Cancellations
 * - Reschedules
 * 
 * KEY METHODS:
 * -----------------------------------------------------------------------------
 * sendEmail($businessId, $toEmail, $subject, $message)
 *   Send an email using business SMTP configuration
 *   Returns: ['ok' => bool, 'error' => string|null]
 * 
 * getPublicIntegration($businessId)
 *   Get sanitized integration info (without sensitive data)
 *   For display in settings UI
 * 
 * configureIntegration($businessId, $config)
 *   Save SMTP configuration (encrypted)
 * 
 * testConnection($businessId, $testEmail)
 *   Send test email to verify SMTP configuration
 * 
 * SMTP CONFIGURATION:
 * -----------------------------------------------------------------------------
 * Config is stored encrypted in xs_business_integrations:
 * - host       : SMTP hostname (smtp.gmail.com)
 * - port       : SMTP port (587, 465, 25)
 * - crypto     : Encryption type (tls, ssl, none)
 * - username   : SMTP authentication username
 * - password   : SMTP authentication password
 * - from_email : Sender email address
 * - from_name  : Sender display name
 * 
 * ENCRYPTION:
 * -----------------------------------------------------------------------------
 * SMTP credentials are encrypted using notification_encrypt_config()
 * before storage. Decrypted on-demand for sending.
 * 
 * COMMON PROVIDERS:
 * -----------------------------------------------------------------------------
 * - Gmail SMTP
 * - Mailgun SMTP
 * - SendGrid SMTP
 * - Amazon SES SMTP
 * - Custom SMTP servers
 * 
 * @see         app/Helpers/notification_helper.php for encryption
 * @see         app/Models/BusinessIntegrationModel.php
 * @package     App\Services
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

namespace App\Services;

use App\Models\BusinessIntegrationModel;

class NotificationEmailService
{
    public const CHANNEL = 'email';

    /**
     * Send an email using the stored SMTP integration for the business.
     * Returns ['ok' => bool, 'error' => string?]
     */
    public function sendEmail(int $businessId, string $toEmail, string $subject, string $message): array
    {
        $toEmail = trim($toEmail);
        if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Invalid recipient email address.'];
        }

        $integration = $this->getIntegrationRow($businessId);
        if (!$integration || empty($integration['encrypted_config'])) {
            return ['ok' => false, 'error' => 'Email integration is not configured.'];
        }

        $config = $this->decryptConfig($integration['encrypted_config']);
        $smtpHost = (string) ($config['host'] ?? '');
        $smtpPort = (int) ($config['port'] ?? 587);
        $smtpCrypto = (string) ($config['crypto'] ?? 'tls');
        $smtpUser = (string) ($config['username'] ?? '');
        $smtpPass = (string) ($config['password'] ?? '');
        $fromEmail = (string) ($config['from_email'] ?? '');
        $fromName = (string) ($config['from_name'] ?? 'WebSchedulr');

        if ($smtpHost === '' || $fromEmail === '') {
            return ['ok' => false, 'error' => 'Email integration is missing required settings (host/from).'];
        }

        try {
            $email = \Config\Services::email();
            $email->initialize([
                'protocol' => 'smtp',
                'SMTPHost' => $smtpHost,
                'SMTPPort' => $smtpPort,
                'SMTPCrypto' => $smtpCrypto,
                'SMTPUser' => $smtpUser,
                'SMTPPass' => $smtpPass,
                'mailType' => 'text',
                'charset' => 'UTF-8',
            ]);

            $email->setFrom($fromEmail, $fromName);
            $email->setTo($toEmail);
            $email->setSubject($subject);
            $email->setMessage($message);

            $ok = (bool) $email->send(false);
            if ($ok) {
                return ['ok' => true];
            }

            $debug = trim((string) $email->printDebugger(['headers']));
            log_message('error', 'NotificationEmailService sendEmail failed: {debug}', ['debug' => $debug]);
            return ['ok' => false, 'error' => 'Email send failed.'];
        } catch (\Throwable $e) {
            log_message('error', 'NotificationEmailService sendEmail exception: {msg}', ['msg' => $e->getMessage()]);
            return ['ok' => false, 'error' => 'Email send failed due to a server error.'];
        }
    }

    /**
     * Returns a safe-to-render subset of the stored email integration.
     * Password is never returned.
     * If decryption fails (key mismatch), returns 'decrypt_error' => 'encryption_key_mismatch'.
     */
    public function getPublicIntegration(int $businessId = NotificationPhase1::BUSINESS_ID_DEFAULT): array
    {
        $integration = $this->getIntegrationRow($businessId);
        if (!$integration) {
            return [
                'provider_name' => '',
                'is_active' => false,
                'config' => [
                    'host' => '',
                    'port' => 587,
                    'crypto' => 'tls',
                    'username' => '',
                    'from_email' => '',
                    'from_name' => '',
                ],
                'decrypt_error' => null,
            ];
        }

        $decrypted = $this->decryptConfig($integration['encrypted_config'] ?? null, true);
        $config = $decrypted['data'] ?? [];
        $decryptError = $decrypted['error'] ?? null;

        return [
            'provider_name' => (string) ($integration['provider_name'] ?? ''),
            'is_active' => (bool) ($integration['is_active'] ?? false),
            'health_status' => (string) ($integration['health_status'] ?? 'unknown'),
            'last_tested_at' => (string) ($integration['last_tested_at'] ?? ''),
            'config' => [
                'host' => (string) ($config['host'] ?? ''),
                'port' => (int) ($config['port'] ?? 587),
                'crypto' => (string) ($config['crypto'] ?? 'tls'),
                'username' => (string) ($config['username'] ?? ''),
                'from_email' => (string) ($config['from_email'] ?? ''),
                'from_name' => (string) ($config['from_name'] ?? ''),
            ],
            'decrypt_error' => $decryptError,
        ];
    }

    /**
     * Save SMTP configuration into xs_business_integrations (encrypted_config).
     * If password is omitted, it is preserved from previous config if present.
     */
    public function saveIntegration(int $businessId, array $input): array
    {
        $providerName = trim((string) ($input['provider_name'] ?? ''));
        $isActive = (bool) ($input['is_active'] ?? false);

        $host = trim((string) ($input['host'] ?? ''));
        $port = $input['port'] ?? null;
        $crypto = trim((string) ($input['crypto'] ?? 'tls'));
        $username = trim((string) ($input['username'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $fromEmail = trim((string) ($input['from_email'] ?? ''));
        $fromName = trim((string) ($input['from_name'] ?? ''));

        if ($port === null || $port === '') {
            $port = 587;
        }
        if (!is_numeric($port)) {
            return ['ok' => false, 'error' => 'SMTP port must be a number.'];
        }
        $port = (int) $port;
        if ($port < 1 || $port > 65535) {
            return ['ok' => false, 'error' => 'SMTP port must be between 1 and 65535.'];
        }

        $cryptoAllowed = ['', 'none', 'tls', 'ssl'];
        if (!in_array($crypto, $cryptoAllowed, true)) {
            return ['ok' => false, 'error' => 'SMTP encryption must be one of: none, tls, ssl.'];
        }
        if ($crypto === 'none') {
            $crypto = '';
        }

        $hasAny = ($providerName !== '' || $host !== '' || $username !== '' || $password !== '' || $fromEmail !== '' || $fromName !== '' || $isActive);
        if (!$hasAny) {
            return ['ok' => true, 'cleared' => false];
        }

        // Basic validation when attempting to configure.
        if ($host === '') {
            return ['ok' => false, 'error' => 'SMTP host is required.'];
        }
        if ($fromEmail === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'A valid From email is required.'];
        }
        if ($username !== '' && $password === '') {
            // Password omitted: preserve existing, if any.
            $existing = $this->getIntegrationRow($businessId);
            $existingConfig = $this->decryptConfig($existing['encrypted_config'] ?? null);
            if (!empty($existingConfig['password'])) {
                $password = (string) $existingConfig['password'];
            } else {
                return ['ok' => false, 'error' => 'SMTP password is required when a username is provided.'];
            }
        }

        $configToStore = [
            'host' => $host,
            'port' => $port,
            'crypto' => $crypto,
            'username' => $username,
            'password' => $password,
            'from_email' => $fromEmail,
            'from_name' => $fromName,
        ];

        try {
            $encryptedConfig = $this->encryptConfig($configToStore);
            log_message('debug', 'NotificationEmailService: encrypted config length: {len}', ['len' => strlen($encryptedConfig)]);
        } catch (\Throwable $e) {
            log_message('error', 'NotificationEmailService: encrypt failed: {msg}', ['msg' => $e->getMessage()]);
            return ['ok' => false, 'error' => 'Encryption is not configured correctly. Please set an application encryption key.'];
        }

        $model = new BusinessIntegrationModel();
        $existing = $this->getIntegrationRow($businessId);

        $payload = [
            'business_id' => $businessId,
            'channel' => self::CHANNEL,
            'provider_name' => ($providerName !== '') ? $providerName : null,
            'encrypted_config' => $encryptedConfig,
            'is_active' => $isActive ? 1 : 0,
        ];

        log_message('debug', 'NotificationEmailService: saving payload with config length: {len}, existing: {existing}', [
            'len' => strlen($encryptedConfig),
            'existing' => $existing['id'] ?? 'new',
        ]);

        if (!empty($existing['id'])) {
            $result = $model->update((int) $existing['id'], $payload);
            log_message('debug', 'NotificationEmailService: update result: {result}, errors: {errors}', [
                'result' => $result ? 'true' : 'false',
                'errors' => json_encode($model->errors()),
            ]);
        } else {
            $result = $model->insert($payload);
            log_message('debug', 'NotificationEmailService: insert result: {result}, errors: {errors}', [
                'result' => $result ? 'true' : 'false',
                'errors' => json_encode($model->errors()),
            ]);
        }

        return ['ok' => true, 'cleared' => false];
    }

    /**
     * Sends a test email and updates integration health status.
     */
    public function sendTestEmail(int $businessId, string $toEmail): array
    {
        $toEmail = trim($toEmail);
        if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Please provide a valid test recipient email address.'];
        }

        $integration = $this->getIntegrationRow($businessId);
        if (!$integration || empty($integration['encrypted_config'])) {
            return ['ok' => false, 'error' => 'Email integration is not configured yet.'];
        }

        $config = $this->decryptConfig($integration['encrypted_config']);

        $smtpHost = (string) ($config['host'] ?? '');
        $smtpPort = (int) ($config['port'] ?? 587);
        $smtpCrypto = (string) ($config['crypto'] ?? 'tls');
        $smtpUser = (string) ($config['username'] ?? '');
        $smtpPass = (string) ($config['password'] ?? '');
        $fromEmail = (string) ($config['from_email'] ?? '');
        $fromName = (string) ($config['from_name'] ?? 'WebSchedulr');

        if ($smtpHost === '' || $fromEmail === '') {
            return ['ok' => false, 'error' => 'Email integration is missing required settings (host/from).'];
        }

        $email = \Config\Services::email();
        $email->initialize([
            'protocol' => 'smtp',
            'SMTPHost' => $smtpHost,
            'SMTPPort' => $smtpPort,
            'SMTPCrypto' => $smtpCrypto,
            'SMTPUser' => $smtpUser,
            'SMTPPass' => $smtpPass,
            'mailType' => 'text',
            'charset' => 'UTF-8',
        ]);

        $email->setFrom($fromEmail, $fromName);
        $email->setTo($toEmail);
        $email->setSubject('WebSchedulr SMTP Test');
        $email->setMessage("This is a test email from WebSchedulr.\n\nIf you received this, your SMTP integration is working.");

        $now = date('Y-m-d H:i:s');
        $model = new BusinessIntegrationModel();

        try {
            $ok = (bool) $email->send(false);
            if ($ok) {
                $this->updateHealth($model, $integration, 'healthy', $now);
                return ['ok' => true];
            }

            $debug = trim((string) $email->printDebugger(['headers']));
            log_message('error', 'SMTP test failed: {debug}', ['debug' => $debug]);
            $this->updateHealth($model, $integration, 'unhealthy', $now);
            return ['ok' => false, 'error' => 'SMTP test failed. Please verify your credentials and server settings.'];
        } catch (\Throwable $e) {
            log_message('error', 'SMTP test exception: {msg}', ['msg' => $e->getMessage()]);
            $this->updateHealth($model, $integration, 'unhealthy', $now);
            return ['ok' => false, 'error' => 'SMTP test failed due to a server error.'];
        }
    }

    private function getIntegrationRow(int $businessId): ?array
    {
        $model = new BusinessIntegrationModel();
        $row = $model
            ->where('business_id', $businessId)
            ->where('channel', self::CHANNEL)
            ->first();

        return is_array($row) ? $row : null;
    }

    private function encryptConfig(array $config): string
    {
        helper('notification');
        return notification_encrypt_config($config, 'SMTP');
    }

    /**
     * Decrypt config, returning ['data' => array, 'error' => string|null].
     * Use decryptConfigSafe() for backward-compatible array-only return.
     */
    private function decryptConfig($encrypted, bool $returnError = false): array
    {
        helper('notification');
        return notification_decrypt_config($encrypted, $returnError, 'NotificationEmailService');
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
}
