<?php

namespace Tests\Unit\Services;

use App\Services\NotificationTemplateService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class NotificationTemplateServiceTest extends CIUnitTestCase
{
    public function testRenderUsesStoredTemplateAndLegalContentPlaceholders(): void
    {
        $this->configureTestingDatabaseEnvironment();

        $db = \Config\Database::connect('tests');
        $keys = [
            'notification_template.appointment_confirmed.email',
            'legal.cancellation_policy',
            'legal.terms_url',
            'general.business_name',
        ];

        $db->table('settings')->whereIn('setting_key', $keys)->delete();

        try {
            $this->seedSetting($db, 'notification_template.appointment_confirmed.email', json_encode([
                'subject' => 'Confirmed for {customer_first_name}',
                'body' => 'Hi {customer_name} from {business_name}. {cancellation_policy} See {terms_link}. Book at {booking_url}.',
            ]), 'json');
            $this->seedSetting($db, 'legal.cancellation_policy', 'Cancel at least 24 hours early.', 'string');
            $this->seedSetting($db, 'legal.terms_url', 'https://example.com/terms', 'string');
            $this->seedSetting($db, 'general.business_name', 'WebScheduler Clinic', 'string');

            $service = new NotificationTemplateService();

            $result = $service->render('appointment_confirmed', 'email', [
                'customer_name' => 'Jane Doe',
                'booking_url' => 'https://example.com/book',
            ]);

            $this->assertSame('Confirmed for Jane', $result['subject']);
            $this->assertSame(
                'Hi Jane Doe from WebScheduler Clinic. Cancel at least 24 hours early. See https://example.com/terms. Book at https://example.com/book.',
                $result['body']
            );
        } finally {
            $db->table('settings')->whereIn('setting_key', $keys)->delete();
        }
    }

    public function testRenderUsesTelephoneNumberForBusinessPhonePlaceholder(): void
    {
        $this->configureTestingDatabaseEnvironment();

        $db = \Config\Database::connect('tests');
        $keys = [
            'notification_template.appointment_confirmed.email',
            'general.telephone_number',
        ];

        $db->table('settings')->whereIn('setting_key', $keys)->delete();

        try {
            $this->seedSetting($db, 'notification_template.appointment_confirmed.email', json_encode([
                'subject' => 'Confirmed',
                'body' => 'Call us on {business_phone}.',
            ]), 'json');
            $this->seedSetting($db, 'general.telephone_number', '+27111234567', 'string');

            $service = new NotificationTemplateService();

            $result = $service->render('appointment_confirmed', 'email', []);

            $this->assertSame('Call us on +27111234567.', $result['body']);
        } finally {
            $db->table('settings')->whereIn('setting_key', $keys)->delete();
        }
    }

    public function testValidateTemplateFlagsUnknownPlaceholdersAndUnbalancedBraces(): void
    {
        $service = new NotificationTemplateService();

        $result = $service->validateTemplate('Hello {customer_name {unknown_token}');

        $this->assertFalse($result['valid']);
        $this->assertContains('Unbalanced braces in template', $result['errors']);
        $this->assertContains('Unknown placeholder: {unknown_token}', $result['errors']);
    }

    public function testValidateRequiredPlaceholdersDetectsMissingRescheduleLinkForConfirmedEmail(): void
    {
        $service = new NotificationTemplateService();

        $result = $service->validateRequiredPlaceholders(
            'appointment_confirmed',
            'email',
            'Hello {customer_name}, your booking is confirmed.'
        );

        $this->assertFalse($result['valid']);
        $this->assertSame(['{reschedule_link}'], $result['missing']);
    }

    public function testValidateRequiredPlaceholdersDetectsMissingRescheduleLinkForPendingEmail(): void
    {
        $service = new NotificationTemplateService();

        $result = $service->validateRequiredPlaceholders(
            'appointment_pending',
            'email',
            'Hello {customer_name}, your booking is pending.'
        );

        $this->assertFalse($result['valid']);
        $this->assertSame(['{reschedule_link}'], $result['missing']);
    }

    public function testDefaultConfirmedEmailTemplateIncludesRescheduleLink(): void
    {
        $service = new NotificationTemplateService();
        $defaults = $service->getDefaultTemplates();

        $body = (string) ($defaults['appointment_confirmed']['email']['body'] ?? '');
        $this->assertStringContainsString('{reschedule_link}', $body);
    }

    public function testDefaultPendingEmailTemplateIncludesPendingWordingAndRescheduleLink(): void
    {
        $service = new NotificationTemplateService();
        $defaults = $service->getDefaultTemplates();

        $body = (string) ($defaults['appointment_pending']['email']['body'] ?? '');
        $this->assertStringContainsString('confirm your appointment shortly', $body);
        $this->assertStringContainsString('{reschedule_link}', $body);
    }

    public function testRenderAppendsManageBookingLineForLegacyStoredEmailTemplate(): void
    {
        $this->configureTestingDatabaseEnvironment();

        $db = \Config\Database::connect('tests');
        $keys = [
            'notification_template.appointment_confirmed.email',
        ];

        $db->table('settings')->whereIn('setting_key', $keys)->delete();

        try {
            $this->seedSetting($db, 'notification_template.appointment_confirmed.email', json_encode([
                'subject' => 'Confirmed for {customer_first_name}',
                'body' => 'Hi {customer_name}, your booking is confirmed.',
            ]), 'json');

            $service = new NotificationTemplateService();

            $result = $service->render('appointment_confirmed', 'email', [
                'customer_name' => 'Jane Doe',
                'reschedule_link' => 'https://example.com/manage/abc',
            ]);

            $this->assertStringContainsString('Manage booking: https://example.com/manage/abc', $result['body']);
        } finally {
            $db->table('settings')->whereIn('setting_key', $keys)->delete();
        }
    }

    public function testRenderUsesDefaultWorkingHoursWhenGlobalRowsMissing(): void
    {
        $this->configureTestingDatabaseEnvironment();

        $db = \Config\Database::connect('tests');
        $keys = [
            'notification_template.appointment_confirmed.email',
            'business.work_start',
            'business.work_end',
        ];

        $db->table('settings')->whereIn('setting_key', $keys)->delete();
        $backupRows = $this->backupGlobalBusinessHoursRows($db);

        try {
            $db->table('business_hours')
                ->groupStart()
                ->where('provider_id', 0)
                ->orWhere('provider_id', null)
                ->groupEnd()
                ->delete();

            $this->seedSetting($db, 'notification_template.appointment_confirmed.email', json_encode([
                'subject' => 'Confirmed',
                'body' => 'Hours: {business_hours}',
            ]), 'json');
            $this->seedSetting($db, 'business.work_start', '09:00', 'string');
            $this->seedSetting($db, 'business.work_end', '17:00', 'string');

            $service = new NotificationTemplateService();
            $result = $service->render('appointment_confirmed', 'email', []);

            $this->assertStringContainsString('Mon', $result['body']);
            $this->assertStringContainsString('09:00', $result['body']);
            $this->assertStringContainsString('17:00', $result['body']);
            $this->assertStringNotContainsString('Please contact us for business hours.', $result['body']);
        } finally {
            $this->restoreGlobalBusinessHoursRows($db, $backupRows);
            $db->table('settings')->whereIn('setting_key', $keys)->delete();
        }
    }

    public function testRenderUsesBusinessHoursFallbackTextWhenGlobalRowsAndDefaultSettingsMissing(): void
    {
        $this->configureTestingDatabaseEnvironment();

        $db = \Config\Database::connect('tests');
        $keys = [
            'notification_template.appointment_confirmed.email',
            'business.work_start',
            'business.work_end',
        ];

        $db->table('settings')->whereIn('setting_key', $keys)->delete();
        $backupRows = $this->backupGlobalBusinessHoursRows($db);

        try {
            $db->table('business_hours')
                ->groupStart()
                ->where('provider_id', 0)
                ->orWhere('provider_id', null)
                ->groupEnd()
                ->delete();

            $this->seedSetting($db, 'notification_template.appointment_confirmed.email', json_encode([
                'subject' => 'Confirmed',
                'body' => 'Hours: {business_hours}',
            ]), 'json');

            $service = new NotificationTemplateService();
            $result = $service->render('appointment_confirmed', 'email', []);

            $this->assertSame('Hours: Please contact us for business hours.', $result['body']);
        } finally {
            $this->restoreGlobalBusinessHoursRows($db, $backupRows);
            $db->table('settings')->whereIn('setting_key', $keys)->delete();
        }
    }

    public function testRenderFormatsBusinessHoursWhenGlobalRowsExist(): void
    {
        $this->configureTestingDatabaseEnvironment();

        $db = \Config\Database::connect('tests');
        $keys = [
            'notification_template.appointment_confirmed.email',
        ];

        $db->table('settings')->whereIn('setting_key', $keys)->delete();
        $backupRows = $this->backupGlobalBusinessHoursRows($db);

        try {
            $db->table('business_hours')
                ->groupStart()
                ->where('provider_id', 0)
                ->orWhere('provider_id', null)
                ->groupEnd()
                ->delete();

            $now = date('Y-m-d H:i:s');
            $db->query('SET FOREIGN_KEY_CHECKS=0');
            try {
                $db->table('business_hours')->insertBatch([
                    [
                        'provider_id' => 0,
                        'weekday' => 1,
                        'start_time' => '09:00:00',
                        'end_time' => '17:00:00',
                        'breaks_json' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                    [
                        'provider_id' => 0,
                        'weekday' => 2,
                        'start_time' => '10:00:00',
                        'end_time' => '18:00:00',
                        'breaks_json' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                ]);
            } finally {
                $db->query('SET FOREIGN_KEY_CHECKS=1');
            }

            $this->seedSetting($db, 'notification_template.appointment_confirmed.email', json_encode([
                'subject' => 'Confirmed',
                'body' => "Hours:\n{business_hours}",
            ]), 'json');

            $service = new NotificationTemplateService();
            $result = $service->render('appointment_confirmed', 'email', []);

            $this->assertStringContainsString('Mon', $result['body']);
            $this->assertStringContainsString('09:00', $result['body']);
            $this->assertStringContainsString('Tue', $result['body']);
            $this->assertStringContainsString('10:00', $result['body']);
            $this->assertStringNotContainsString('Please contact us for business hours.', $result['body']);
        } finally {
            $this->restoreGlobalBusinessHoursRows($db, $backupRows);
            $db->table('settings')->whereIn('setting_key', $keys)->delete();
        }
    }

    public function testGetTemplateForInternalReturnsInternalFallbackWhenNoDbRowExists(): void
    {
        // Uses the code-level DEFAULT_INTERNAL_TEMPLATES when no xs_message_templates row exists.
        // This guards against production environments where the migration hasn't been applied.
        $service = new NotificationTemplateService();

        $template = $service->getTemplate('appointment_confirmed', 'email', 'internal');

        $this->assertNotEmpty($template['body'], 'Internal fallback body must not be empty');

        // Must contain customer info section — NOT a customer-facing greeting
        $this->assertStringContainsString('Customer Information', $template['body']);
        $this->assertStringContainsString('{customer_name}', $template['body']);
        $this->assertStringContainsString('{customer_email}', $template['body']);

        // Must contain provider info (the appointment provider, listed as a field)
        $this->assertStringContainsString('Provider: {provider_name}', $template['body']);

        // Must contain internal quick-links
        $this->assertStringContainsString('{internal_view_link}', $template['body']);
        $this->assertStringContainsString('{internal_edit_link}', $template['body']);

        // Subject must reference both customer and provider names
        $this->assertStringContainsString('{customer_name}', $template['subject']);
        $this->assertStringContainsString('{provider_name}', $template['subject']);
    }

    public function testGetTemplateForInternalFallbackExistsForAllFiveEventTypes(): void
    {
        $service = new NotificationTemplateService();
        $events = [
            'appointment_pending',
            'appointment_confirmed',
            'appointment_cancelled',
            'appointment_rescheduled',
            'appointment_reminder',
        ];

        foreach ($events as $event) {
            $template = $service->getTemplate($event, 'email', 'internal');
            $this->assertNotEmpty($template['body'], "Internal fallback body must not be empty for {$event}");
            $this->assertStringContainsString('Customer Information', $template['body'], "Internal template for {$event} must include customer section");
        }
    }

    public function testRenderEmailRendersSessionInfoAsClickableMapButtons(): void
    {
        $this->configureTestingDatabaseEnvironment();

        // Seed a known HTML template via upsert() (which invalidates SettingModel's
        // request cache) so this test is deterministic regardless of stored-template or
        // migration state. Uses the rescheduled key — no other test reads it.
        $key = 'notification_template.appointment_rescheduled.email';
        $db  = \Config\Database::connect('tests');
        $db->table('settings')->where('setting_key', $key)->delete();

        try {
            (new \App\Models\SettingModel())->upsert($key, [
                'subject' => 'Rescheduled',
                'body'    => '<p class="greeting">Hi {customer_first_name}</p>{session_info}<a href="{reschedule_link}">Manage</a>',
            ], 'json');

            $result = (new NotificationTemplateService())->render('appointment_rescheduled', 'email', [
                'customer_name'    => 'Jane Doe',
                'reschedule_link'  => 'https://example.com/manage/abc',
                'location_name'    => 'Sandton Mews',
                'location_address' => '21 Delta Road',
                'delivery_mode'    => 'In Person',
            ]);

            $body = $result['body'];

            // Maps/Waze rendered as friendly buttons, not raw URLs.
            $this->assertStringContainsString('Open in Google Maps', $body);
            $this->assertStringContainsString('Open in Waze', $body);
            $this->assertStringContainsString('<a class="btn"', $body);
            // The legacy plain-text "Maps: https://… | Waze: https://…" line is gone.
            $this->assertStringNotContainsString('Maps: https', $body);
        } finally {
            $db->table('settings')->where('setting_key', $key)->delete();
        }
    }

    public function testRenderWhatsAppKeepsSessionInfoPlainText(): void
    {
        $this->configureTestingDatabaseEnvironment();

        $service = new NotificationTemplateService();

        $result = $service->render('appointment_confirmed', 'whatsapp', [
            'customer_name'    => 'Jane Doe',
            'reschedule_link'  => 'https://example.com/manage/abc',
            'location_name'    => 'Sandton Mews',
            'location_address' => '21 Delta Road',
            'delivery_mode'    => 'In Person',
        ]);

        $body = $result['body'];

        // Non-email channels never receive the email-only HTML buttons/anchors —
        // the body stays plain text regardless of which stored template is used.
        $this->assertStringContainsString('Location', $body);
        $this->assertStringNotContainsString('<a ', $body);
        $this->assertStringNotContainsString('Open in Google Maps', $body);
        $this->assertStringNotContainsString('<table', $body);
    }

    private function seedSetting($db, string $key, string $value, string $type): void
    {
        $db->table('settings')->insert([
            'setting_key' => $key,
            'setting_value' => $value,
            'setting_type' => $type,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function backupGlobalBusinessHoursRows($db): array
    {
        return $db->table('business_hours')
            ->select('provider_id, weekday, start_time, end_time, breaks_json, created_at, updated_at')
            ->groupStart()
            ->where('provider_id', 0)
            ->orWhere('provider_id', null)
            ->groupEnd()
            ->orderBy('weekday', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function restoreGlobalBusinessHoursRows($db, array $rows): void
    {
        $db->table('business_hours')
            ->groupStart()
            ->where('provider_id', 0)
            ->orWhere('provider_id', null)
            ->groupEnd()
            ->delete();

        if ($rows === []) {
            return;
        }

        $db->table('business_hours')->insertBatch($rows);
    }

    private function configureTestingDatabaseEnvironment(): void
    {
        $envPath = ROOTPATH . '.env';
        if (!is_file($envPath)) {
            return;
        }

        $values = [];
        foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#') || !str_contains($trimmed, '=')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode('=', $trimmed, 2));
            $values[$key] = trim($value, " \t\n\r\0\x0B\"'");
        }

        $mapping = [
            'database.tests.hostname' => $values['database.tests.hostname'] ?? $values['database.default.hostname'] ?? null,
            'database.tests.database' => $values['database.tests.database'] ?? $values['database.default.database'] ?? null,
            'database.tests.username' => $values['database.tests.username'] ?? $values['database.default.username'] ?? null,
            'database.tests.password' => $values['database.tests.password'] ?? $values['database.default.password'] ?? null,
            'database.tests.DBDriver' => $values['database.tests.DBDriver'] ?? $values['database.default.DBDriver'] ?? null,
            'database.tests.DBPrefix' => $values['database.tests.DBPrefix'] ?? $values['database.default.DBPrefix'] ?? 'xs_',
            'database.tests.port' => $values['database.tests.port'] ?? $values['database.default.port'] ?? '3306',
        ];

        foreach ($mapping as $key => $value) {
            if ($value === null) {
                continue;
            }

            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}