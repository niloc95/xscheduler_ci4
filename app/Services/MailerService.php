<?php

/**
 * =============================================================================
 * MAILER SERVICE
 * =============================================================================
 *
 * @file        app/Services/MailerService.php
 * @description Single source of truth for all outgoing email transport in
 *              WebScheduler. Resolves SMTP configuration, instantiates the
 *              CI4 email driver, and delivers messages for all email types:
 *              auth (password reset), booking notifications, and future
 *              system emails.
 *
 * PURPOSE
 * -------
 * Eliminate split email logic across Auth and the notification pipeline by
 * owning every aspect of transport:
 *   - Configuration resolution (DB integration → .env dev fallback)
 *   - From-address branding resolution
 *   - CI4 Email driver instantiation
 *   - Sending with consistent error handling and logging
 *   - Transport capability check used by queue gates
 *
 * ARCHITECTURE CONTRACT
 * ----------------------
 * MailerService is the ONLY permitted email transport layer.
 * See Agent_Context_v2.md §7.3 for the full owner contract.
 *
 * CALLERS
 * -------
 * - app/Controllers/Auth.php (password reset)
 * - app/Services/NotificationEmailService.php (appointment notifications)
 *
 * TRANSPORT RESOLUTION ORDER
 * --------------------------
 * 1. Active xs_business_integrations row (channel = email) — all environments
 * 2. Config\Email values (.env) — ONLY when ENVIRONMENT === 'development'
 *    and no active integration row exists
 * 3. null → cannot send; callers receive ['ok' => false] response
 *
 * RESPONSE CONTRACT
 * -----------------
 * send() always returns:
 *   ['ok' => bool, 'error' => ?string, 'transport' => string, 'messageId' => ?string]
 *
 * canSend() validates that the resolved config has a non-empty host AND
 * from_email — not merely a non-null config array.
 *
 * ISOLATION NOTES
 * ---------------
 * testConnection() in NotificationEmailService bypasses MailerService
 * intentionally — it tests a caller-supplied config object directly.
 * Do not route testConnection() through this class.
 *
 * QUEUE GATE DEPENDENCY
 * ----------------------
 * The notification queue gates (NotificationQueueDispatcher::isIntegrationActive,
 * NotificationQueueService::isIntegrationActive) call
 * NotificationEmailService::canUseDevelopmentFallbackSmtp(), which delegates
 * to MailerService::canSend(). Changes to canSend() are therefore queue-affecting.
 *
 * @see  app/Services/NotificationEmailService.php
 * @see  app/Controllers/Auth.php
 * @see  Agent_Context_v2.md §7.3
 * @package App\Services
 * @author  Nilesh Nagin Cara
 * @copyright 2024-2026 Nilesh Nagin Cara
 * =============================================================================
 */

namespace App\Services;

use App\Models\BusinessIntegrationModel;
use App\Services\NotificationCatalog;
use App\Services\Concerns\HandlesNotificationIntegrations;

class MailerService
{
    use HandlesNotificationIntegrations;

    public const CHANNEL = 'email';

    // Transport label constants returned in response arrays.
    public const TRANSPORT_SMTP        = 'smtp';
    public const TRANSPORT_DEV_MAILPIT = 'dev-fallback';
    public const TRANSPORT_UNKNOWN     = 'unknown';

    protected function integrationEncryptionLabel(): string
    {
        return 'SMTP';
    }

    protected function integrationDecryptContext(): string
    {
        return 'MailerService';
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Send an email using the resolved transport for the given business.
     *
     * @param  int    $businessId       Business ID (use NotificationCatalog::BUSINESS_ID_DEFAULT for system emails)
     * @param  string $toEmail          Recipient address
     * @param  string $subject          Email subject
     * @param  string $body             Rendered message body
     * @param  string $mailType         'html' or 'text'
     * @param  string $fromEmailOverride When non-empty, overrides the resolved from address
     * @param  string $fromNameOverride  When non-empty, overrides the resolved from name
     * @return array{ok: bool, error: ?string, transport: string, messageId: ?string}
     */
    public function send(
        int    $businessId,
        string $toEmail,
        string $subject,
        string $body,
        string $mailType         = 'html',
        string $fromEmailOverride = '',
        string $fromNameOverride  = ''
    ): array {
        $toEmail = trim($toEmail);
        if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return $this->errorResult('Invalid recipient email address.', self::TRANSPORT_UNKNOWN);
        }

        $resolved = $this->resolveTransportConfig($businessId);
        if ($resolved === null) {
            return $this->errorResult('Email is not configured. Set up an SMTP integration or configure .env email settings.', self::TRANSPORT_UNKNOWN);
        }

        $transport = $resolved['_transport_label'] ?? self::TRANSPORT_UNKNOWN;

        // From-address resolution: caller override > integration config > Config\Email fallback.
        $fromEmail = $fromEmailOverride !== '' ? $fromEmailOverride : (string) ($resolved['from_email'] ?? '');
        $fromName  = $fromNameOverride  !== '' ? $fromNameOverride  : (string) ($resolved['from_name'] ?? 'WebScheduler');

        // Final guard — resolved config must have a from address.
        if ($fromEmail === '') {
            return $this->errorResult('No sender email address is configured.', $transport);
        }

        try {
            $emailInstance = $this->createEmailInstance($resolved, $mailType);
            $emailInstance->setFrom($fromEmail, $fromName);
            $emailInstance->setTo($toEmail);
            $emailInstance->setSubject($subject);
            $emailInstance->setMessage($body);

            $ok = (bool) $emailInstance->send(false);

            if ($ok) {
                log_message('info', '[MailerService] Email sent — business_id:{bid} to:{to} subject:{subj} transport:{transport}', [
                    'bid'       => $businessId,
                    'to'        => $toEmail,
                    'subj'      => $subject,
                    'transport' => $transport,
                ]);
                return ['ok' => true, 'error' => null, 'transport' => $transport, 'messageId' => null];
            }

            $debug = trim((string) $emailInstance->printDebugger(['headers']));
            log_message('error', '[MailerService] Email failed — business_id:{bid} to:{to} subject:{subj} transport:{transport} debug:{debug}', [
                'bid'       => $businessId,
                'to'        => $toEmail,
                'subj'      => $subject,
                'transport' => $transport,
                'debug'     => $debug,
            ]);
            return $this->errorResult('Email send failed.', $transport);
        } catch (\Throwable $e) {
            log_message('error', '[MailerService] Email exception — business_id:{bid} to:{to} transport:{transport} error:{msg}', [
                'bid'       => $businessId,
                'to'        => $toEmail,
                'transport' => $transport,
                'msg'       => $e->getMessage(),
            ]);
            return $this->errorResult('Email send failed due to a server error.', $transport);
        }
    }

    /**
     * Returns true when a valid transport config (non-empty host AND from_email)
     * can be resolved for the given business.
     *
     * Used by NotificationEmailService::canUseDevelopmentFallbackSmtp() which
     * is the queue gate capability check. Changes here are queue-affecting.
     */
    public function canSend(int $businessId = NotificationCatalog::BUSINESS_ID_DEFAULT): bool
    {
        $config = $this->resolveTransportConfig($businessId);
        if ($config === null) {
            return false;
        }
        return trim((string) ($config['host'] ?? '')) !== ''
            && trim((string) ($config['from_email'] ?? '')) !== '';
    }

    /**
     * Resolve the SMTP transport config for a business.
     *
     * Resolution order (strict):
     *   1. Active xs_business_integrations row (channel = email) — all environments
     *   2. Config\Email (.env) — ONLY in development when no active integration row
     *   3. null — nothing configured
     *
     * @return array<string,mixed>|null
     */
    public function resolveTransportConfig(int $businessId): ?array
    {
        // Priority 1: DB integration (all environments).
        $integration = $this->getIntegrationRow($businessId);
        if ($integration && !empty($integration['encrypted_config']) && (bool) ($integration['is_active'] ?? false)) {
            $config = $this->decryptConfig($integration['encrypted_config']);
            if (!empty($config) && trim((string) ($config['host'] ?? '')) !== '') {
                $config['_transport_label'] = self::TRANSPORT_SMTP;
                return $config;
            }
        }

        // Priority 2: .env dev fallback (development only).
        return $this->resolveDevelopmentFallback();
    }

    // -------------------------------------------------------------------------
    // Protected helpers
    // -------------------------------------------------------------------------

    /**
     * Instantiate and configure the CI4 Email driver.
     *
     * Protected to allow future transport swaps (e.g. SMTP → SendGrid API)
     * without rewriting send(). Callers must never call this directly.
     *
     * @param  array<string,mixed> $config   Resolved transport config
     * @param  string              $mailType 'html' or 'text'
     * @return \CodeIgniter\Email\Email
     */
    protected function createEmailInstance(array $config, string $mailType = 'html'): \CodeIgniter\Email\Email
    {
        $email = \Config\Services::email();
        $email->initialize([
            'protocol'   => 'smtp',
            'SMTPHost'   => (string) ($config['host'] ?? ''),
            'SMTPPort'   => (int)    ($config['port'] ?? 587),
            'SMTPCrypto' => (string) ($config['crypto'] ?? ''),
            'SMTPUser'   => (string) ($config['username'] ?? ''),
            'SMTPPass'   => (string) ($config['password'] ?? ''),
            'mailType'   => $mailType,
            'charset'    => 'UTF-8',
        ]);
        return $email;
    }

    /**
     * Development-only fallback: returns SMTP config from Config\Email (.env)
     * when ENVIRONMENT === 'development' and the required fields are present.
     *
     * Returns null in all other cases (non-development or incomplete config).
     *
     * @return array<string,mixed>|null
     */
    protected function resolveDevelopmentFallback(): ?array
    {
        if (ENVIRONMENT !== 'development') {
            return null;
        }

        $emailConfig = config('Email');
        if (!$emailConfig instanceof \Config\Email) {
            return null;
        }

        if (strtolower((string) ($emailConfig->protocol ?? 'mail')) !== 'smtp') {
            // No SMTP configured in .env; use hardcoded Mailpit defaults for local dev.
            $host      = '127.0.0.1';
            $port      = 1025;
            $crypto    = '';
            $user      = '';
            $pass      = '';
            $fromEmail = trim((string) ($emailConfig->fromEmail ?? ''));
            $fromName  = trim((string) ($emailConfig->fromName  ?? 'WebScheduler'));

            // Require a from address even for Mailpit.
            if ($fromEmail === '') {
                return null;
            }

            return [
                'host'             => $host,
                'port'             => $port,
                'crypto'           => $crypto,
                'username'         => $user,
                'password'         => $pass,
                'from_email'       => $fromEmail,
                'from_name'        => $fromName,
                '_transport_label' => self::TRANSPORT_DEV_MAILPIT,
            ];
        }

        $host      = trim((string) ($emailConfig->SMTPHost ?? ''));
        $fromEmail = trim((string) ($emailConfig->fromEmail ?? ''));

        if ($host === '' || $fromEmail === '') {
            return null;
        }

        return [
            'host'             => $host,
            'port'             => (int) ($emailConfig->SMTPPort ?: 587),
            'crypto'           => (string) ($emailConfig->SMTPCrypto ?? ''),
            'username'         => trim((string) ($emailConfig->SMTPUser ?? '')),
            'password'         => trim((string) ($emailConfig->SMTPPass ?? '')),
            'from_email'       => $fromEmail,
            'from_name'        => trim((string) ($emailConfig->fromName ?: 'WebScheduler')),
            '_transport_label' => self::TRANSPORT_DEV_MAILPIT,
        ];
    }

    /**
     * Build a normalized error response.
     *
     * @return array{ok: bool, error: string, transport: string, messageId: null}
     */
    private function errorResult(string $error, string $transport): array
    {
        return ['ok' => false, 'error' => $error, 'transport' => $transport, 'messageId' => null];
    }
}
