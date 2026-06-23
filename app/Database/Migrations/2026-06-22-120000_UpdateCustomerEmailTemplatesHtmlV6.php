<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;
use App\Models\SettingModel;
use App\Services\NotificationTemplateService;

/**
 * V6 — Convert the stored customer EMAIL templates to responsive HTML.
 *
 * Notification emails now send as HTML (NotificationEmailService::sendEmail wraps the
 * body in app/Views/emails/notification.php). The customer email bodies in
 * NotificationTemplateService::DEFAULT_TEMPLATES were redesigned as HTML fragments with
 * friendly clickable buttons (Maps/Waze, Manage appointment, Add to calendar) instead of
 * raw URLs. Stored settings rows take priority over the code defaults, so any environment
 * that ran the customer-template seed (2026-04-18-100000) still holds the old plain-text
 * bodies and must be updated here.
 *
 * Only the `email` channel rows change. SMS and WhatsApp templates are intentionally left
 * untouched — those channels remain plain text.
 *
 * Source of truth: the new HTML bodies are pulled from
 * NotificationTemplateService::getDefaultTemplates() so this migration never drifts from
 * the code defaults.
 *
 * down(): deletes the upserted email rows so the service falls back to the code-level
 * DEFAULT_TEMPLATES. Pair with a code rollback to restore the previous plain-text bodies.
 */
class Migration_UpdateCustomerEmailTemplatesHtmlV6 extends MigrationBase
{
    private const EVENTS = [
        'appointment_pending',
        'appointment_confirmed',
        'appointment_reminder',
        'appointment_cancelled',
        'appointment_rescheduled',
    ];

    public function up(): void
    {
        $defaults     = (new NotificationTemplateService())->getDefaultTemplates();
        $settingModel = new SettingModel();

        foreach (self::EVENTS as $event) {
            $email = $defaults[$event]['email'] ?? null;
            if (!is_array($email) || empty($email['body'])) {
                continue;
            }

            $settingModel->upsert(
                "notification_template.{$event}.email",
                [
                    'subject' => $email['subject'] ?? '',
                    'body'    => $email['body'],
                ],
                'json'
            );
        }
    }

    public function down(): void
    {
        $keys = array_map(
            static fn (string $event): string => "notification_template.{$event}.email",
            self::EVENTS
        );

        $this->db->table('settings')
            ->whereIn('setting_key', $keys)
            ->delete();
    }
}
