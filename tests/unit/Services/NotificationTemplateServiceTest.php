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
        $this->assertStringContainsString('pending confirmation', $body);
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