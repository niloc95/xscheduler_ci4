<?php

namespace Tests\Unit\Services;
use App\Models\SettingModel;
use App\Services\NotificationCatalog;
use App\Services\NotificationEmailService;
use App\Services\NotificationSmsService;
use App\Services\NotificationWhatsAppService;
use App\Services\Settings\GeneralSettingsService;
use App\Services\Settings\NotificationSettingsService;
use App\Services\Settings\SettingsPageService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class SettingsBoundaryServicesTest extends CIUnitTestCase
{
    public function testSettingsPageServiceBuildsDefaultAdminContextWhenSessionUserMissing(): void
    {
        $settingModel = $this->createMock(SettingModel::class);
        $settingModel->expects($this->once())
            ->method('getByKeys')
            ->with($this->callback(static function (array $keys): bool {
                return in_array('general.company_name', $keys, true)
                    && in_array('notifications.default_language', $keys, true);
            }))
            ->willReturn([
                'general.company_name' => 'WebScheduler',
                'notifications.default_language' => 'English',
            ]);

        $notificationService = $this->createMock(NotificationSettingsService::class);
        $notificationService->expects($this->once())
            ->method('getIndexData')
            ->willReturn([
                'notificationRules' => ['appointment_created' => ['email' => true]],
                'notificationEvents' => ['appointment_created' => 'Appointment Created'],
            ]);

        $service = new SettingsPageService($settingModel, $notificationService);

        $result = $service->buildIndexData();

        $this->assertSame('System Administrator', $result['user']['name']);
        $this->assertSame('admin', $result['user']['role']);
        $this->assertSame('WebScheduler', $result['settings']['general.company_name']);
        $this->assertTrue($result['notificationRules']['appointment_created']['email']);
    }

    public function testSettingsPageServicePreservesProvidedSessionUser(): void
    {
        $settingModel = $this->createMock(SettingModel::class);
        $settingModel->method('getByKeys')->willReturn([]);

        $notificationService = $this->createMock(NotificationSettingsService::class);
        $notificationService->method('getIndexData')->willReturn([]);

        $service = new SettingsPageService($settingModel, $notificationService);

        $result = $service->buildIndexData([
            'name' => 'A. Admin',
            'role' => 'admin',
            'email' => 'admin@example.com',
        ]);

        $this->assertSame('A. Admin', $result['user']['name']);
        $this->assertSame('admin@example.com', $result['user']['email']);
        $this->assertArrayHasKey('settings', $result);
    }

    public function testGeneralSettingsServiceSaveNormalizesBlockedPeriodsAndCheckboxFields(): void
    {
        $settingModel = new RecordingSettingModel();
        $service = new GeneralSettingsService($settingModel);

        $result = $service->save([
            'company_name' => 'WebScheduler',
            'timezone' => 'UTC',
            'booking_first_names_display' => '1',
            'booking_email_required' => '1',
            'booking_custom_field_1_enabled' => '1',
            'blocked_periods' => json_encode([
                ['start' => '2026-05-01 09:00:00', 'end' => '2026-05-01 10:00:00', 'notes' => 'Holiday'],
                ['start' => '2026-05-02 09:00:00'],
                'invalid',
            ]),
            'fields' => ['email' => ['display' => true, 'required' => true]],
        ], null, 77);

        $this->assertSame('success', $result['type']);
        $this->assertSame('Settings saved successfully.', $result['message']);

        $this->assertContains([
            'key' => 'general.company_name',
            'value' => 'WebScheduler',
            'type' => 'string',
            'userId' => 77,
        ], $settingModel->upserts);

        $this->assertContains([
            'key' => 'localization.timezone',
            'value' => 'UTC',
            'type' => 'string',
            'userId' => 77,
        ], $settingModel->upserts);

        $this->assertContains([
            'key' => 'booking.first_names_display',
            'value' => '1',
            'type' => 'string',
            'userId' => 77,
        ], $settingModel->upserts);

        $this->assertContains([
            'key' => 'booking.surname_display',
            'value' => '0',
            'type' => 'string',
            'userId' => 77,
        ], $settingModel->upserts);

        $this->assertContains([
            'key' => 'booking.custom_field_1_enabled',
            'value' => '1',
            'type' => 'string',
            'userId' => 77,
        ], $settingModel->upserts);

        $this->assertContains([
            'key' => 'booking.fields',
            'value' => ['email' => ['display' => true, 'required' => true]],
            'type' => 'json',
            'userId' => 77,
        ], $settingModel->upserts);

        $this->assertContains([
            'key' => 'business.blocked_periods',
            'value' => [
                ['start' => '2026-05-01 09:00:00', 'end' => '2026-05-01 10:00:00', 'notes' => 'Holiday'],
            ],
            'type' => 'json',
            'userId' => 77,
        ], $settingModel->upserts);
    }

    public function testNotificationSettingsServiceSaveTemplatesPersistsNormalizedTemplatePayload(): void
    {
        $this->configureTestingDatabaseEnvironment();

        $db = \Config\Database::connect('tests');
        $keys = [
            'notifications.default_language',
            'notification_template.appointment_confirmed.email',
        ];

        $db->table('settings')->whereIn('setting_key', $keys)->delete();

        try {
            $service = new NotificationSettingsService();

            $result = $service->save([
                'intent' => 'save_templates',
                'notification_default_language' => 'French',
                'templates' => [
                    'appointment_confirmed' => [
                        'email' => [
                            'subject' => '  Confirmed Subject  ',
                            'body' => '  Appointment confirmed body. Manage booking: {reschedule_link}  ',
                        ],
                    ],
                ],
            ], 55);

            $this->assertSame('success', $result['type']);
            $this->assertSame('Message templates saved successfully.', $result['message']);

            $settings = $db->table('settings')
                ->whereIn('setting_key', $keys)
                ->get()
                ->getResultArray();

            $byKey = [];
            foreach ($settings as $row) {
                $byKey[$row['setting_key']] = $row;
            }

            $this->assertSame('French', $byKey['notifications.default_language']['setting_value'] ?? null);

            $templatePayload = json_decode((string) ($byKey['notification_template.appointment_confirmed.email']['setting_value'] ?? ''), true);
            $this->assertSame([
                'subject' => 'Confirmed Subject',
                'body' => 'Appointment confirmed body. Manage booking: {reschedule_link}',
            ], $templatePayload);
        } finally {
            $db->table('settings')->whereIn('setting_key', $keys)->delete();
        }
    }

    public function testNotificationSettingsServiceSaveTemplatesRejectsMissingRequiredPlaceholders(): void
    {
        $this->configureTestingDatabaseEnvironment();

        $db = \Config\Database::connect('tests');
        $keys = [
            'notification_template.appointment_confirmed.email',
        ];

        $db->table('settings')->whereIn('setting_key', $keys)->delete();

        try {
            $service = new NotificationSettingsService();

            $result = $service->save([
                'intent' => 'save_templates',
                'notification_default_language' => 'English',
                'templates' => [
                    'appointment_confirmed' => [
                        'email' => [
                            'subject' => 'Confirmed Subject',
                            'body' => 'Appointment confirmed body without required link.',
                        ],
                    ],
                ],
            ], 55);

            $this->assertSame('error', $result['type']);
            $this->assertStringContainsString('appointment_confirmed.email', $result['message']);
            $this->assertStringContainsString('{reschedule_link}', $result['message']);

            $row = $db->table('settings')
                ->where('setting_key', 'notification_template.appointment_confirmed.email')
                ->get()
                ->getFirstRow('array');

            $this->assertNull($row);
        } finally {
            $db->table('settings')->whereIn('setting_key', $keys)->delete();
        }
    }

    public function testNotificationSettingsServiceSaveEmailReturnsStrictIntegrationError(): void
    {
        $settingModel = $this->createMock(SettingModel::class);
        $settingModel->expects($this->once())
            ->method('upsert')
            ->with('notifications.default_language', 'German', 'string', 81)
            ->willReturn(true);

        $emailService = $this->createMock(NotificationEmailService::class);
        $emailService->expects($this->once())
            ->method('saveIntegration')
            ->with(
                NotificationCatalog::BUSINESS_ID_DEFAULT,
                $this->callback(static function (array $payload): bool {
                    return ($payload['provider_name'] ?? null) === 'smtp'
                        && ($payload['host'] ?? null) === null
                        && ($payload['from_email'] ?? null) === null;
                })
            )
            ->willReturn([
                'ok' => false,
                'error' => 'SMTP host is required.',
            ]);

        $service = new TestNotificationSettingsService(
            $settingModel,
            $emailService,
            $this->createMock(NotificationSmsService::class),
            $this->createMock(NotificationWhatsAppService::class)
        );

        $result = $service->save([
            'intent' => 'save_email',
            'notification_default_language' => 'German',
            'email_provider_name' => 'smtp',
        ], 81);

        $this->assertSame('error', $result['type']);
        $this->assertSame('SMTP host is required.', $result['message']);
        $this->assertFalse($result['html']);
    }

    public function testNotificationSettingsServiceTestWhatsAppReturnsEscapedLinkMarkup(): void
    {
        $settingModel = $this->createMock(SettingModel::class);
        $settingModel->expects($this->once())
            ->method('upsert')
            ->with('notifications.default_language', 'English', 'string', 82)
            ->willReturn(true);

        $whatsAppService = $this->createMock(NotificationWhatsAppService::class);
        $whatsAppService->expects($this->once())
            ->method('saveIntegration')
            ->with(
                NotificationCatalog::BUSINESS_ID_DEFAULT,
                $this->callback(static function (array $payload): bool {
                    return ($payload['provider'] ?? null) === 'link_generator'
                        && ($payload['is_active'] ?? null) === false;
                })
            )
            ->willReturn(['ok' => true]);
        $whatsAppService->expects($this->once())
            ->method('sendTestMessage')
            ->with(NotificationCatalog::BUSINESS_ID_DEFAULT, '+15550001111')
            ->willReturn([
                'ok' => true,
                'method' => 'link',
                'link' => 'https://wa.me/15550001111?text=Hello&lang=en',
            ]);

        $service = new TestNotificationSettingsService(
            $settingModel,
            $this->createMock(NotificationEmailService::class),
            $this->createMock(NotificationSmsService::class),
            $whatsAppService
        );

        $result = $service->save([
            'intent' => 'test_whatsapp',
            'language' => 'English',
            'test_whatsapp_to' => '+15550001111',
        ], 82);

        $this->assertSame('success', $result['type']);
        $this->assertTrue($result['html']);
        $this->assertStringContainsString('WhatsApp Link ready!', $result['message']);
        $this->assertStringContainsString('https://wa.me/15550001111?text=Hello&amp;lang=en', $result['message']);
    }

    public function testNotificationSettingsServiceSaveWhatsAppMetaCloudPersistsTemplatesForAllEvents(): void
    {
        $settingModel = $this->createMock(SettingModel::class);
        $settingModel->expects($this->once())
            ->method('upsert')
            ->with('notifications.default_language', 'English', 'string', 83)
            ->willReturn(true);

        $whatsAppService = new SpyNotificationWhatsAppService();

        $service = new TestNotificationSettingsService(
            $settingModel,
            $this->createMock(NotificationEmailService::class),
            $this->createMock(NotificationSmsService::class),
            $whatsAppService
        );

        $result = $service->save([
            'intent' => 'save_whatsapp',
            'language' => 'English',
            'whatsapp_provider' => 'meta_cloud',
            'whatsapp_is_active' => '1',
            'whatsapp_template_appointment_confirmed' => 'appt_confirmed_tpl',
            'whatsapp_locale_appointment_confirmed' => 'en_GB',
        ], 83);

        $this->assertSame('success', $result['type']);
        $this->assertSame('WhatsApp settings saved successfully.', $result['message']);
        $this->assertSame([
            'provider' => 'meta_cloud',
            'is_active' => true,
            'twilio_whatsapp_from' => null,
            'phone_number_id' => null,
            'waba_id' => null,
            'access_token' => null,
        ], $whatsAppService->savedIntegrationPayload);

        $this->assertCount(count(NotificationCatalog::EVENTS), $whatsAppService->savedTemplates);
        $this->assertContains([
            'businessId' => NotificationCatalog::BUSINESS_ID_DEFAULT,
            'eventType' => 'appointment_confirmed',
            'templateName' => 'appt_confirmed_tpl',
            'locale' => 'en_GB',
        ], $whatsAppService->savedTemplates);
        $this->assertContains([
            'businessId' => NotificationCatalog::BUSINESS_ID_DEFAULT,
            'eventType' => 'appointment_cancelled',
            'templateName' => null,
            'locale' => null,
        ], $whatsAppService->savedTemplates);
    }

    public function testNotificationSettingsServiceTestSmsReturnsProviderError(): void
    {
        $settingModel = $this->createMock(SettingModel::class);
        $settingModel->expects($this->once())
            ->method('upsert')
            ->with('notifications.default_language', 'Spanish', 'string', 84)
            ->willReturn(true);

        $smsService = $this->createMock(NotificationSmsService::class);
        $smsService->expects($this->once())
            ->method('saveIntegration')
            ->with(
                NotificationCatalog::BUSINESS_ID_DEFAULT,
                $this->callback(static function (array $payload): bool {
                    return ($payload['provider'] ?? null) === 'twilio'
                        && ($payload['is_active'] ?? null) === false
                        && ($payload['twilio_account_sid'] ?? null) === null;
                })
            )
            ->willReturn(['ok' => true]);
        $smsService->expects($this->once())
            ->method('sendTestSms')
            ->with(NotificationCatalog::BUSINESS_ID_DEFAULT, '+15550002222')
            ->willReturn([
                'ok' => false,
                'error' => 'SMS integration is not configured yet.',
            ]);

        $service = new TestNotificationSettingsService(
            $settingModel,
            $this->createMock(NotificationEmailService::class),
            $smsService,
            $this->createMock(NotificationWhatsAppService::class)
        );

        $result = $service->save([
            'intent' => 'test_sms',
            'notification_default_language' => 'Spanish',
            'sms_provider' => 'twilio',
            'test_sms_to' => '+15550002222',
        ], 84);

        $this->assertSame('error', $result['type']);
        $this->assertSame('SMS integration is not configured yet.', $result['message']);
        $this->assertFalse($result['html']);
    }

    public function testNotificationSettingsServiceTestEmailReturnsProviderError(): void
    {
        $settingModel = $this->createMock(SettingModel::class);
        $settingModel->expects($this->once())
            ->method('upsert')
            ->with('notifications.default_language', 'Italian', 'string', 85)
            ->willReturn(true);

        $emailService = $this->createMock(NotificationEmailService::class);
        $emailService->expects($this->once())
            ->method('saveIntegration')
            ->with(
                NotificationCatalog::BUSINESS_ID_DEFAULT,
                $this->callback(static function (array $payload): bool {
                    return ($payload['provider_name'] ?? null) === 'smtp'
                        && ($payload['is_active'] ?? null) === false
                        && ($payload['from_email'] ?? null) === null;
                })
            )
            ->willReturn(['ok' => true]);
        $emailService->expects($this->once())
            ->method('sendTestEmail')
            ->with(NotificationCatalog::BUSINESS_ID_DEFAULT, 'alerts@example.com')
            ->willReturn([
                'ok' => false,
                'error' => 'Email integration is not configured yet.',
            ]);

        $service = new TestNotificationSettingsService(
            $settingModel,
            $emailService,
            $this->createMock(NotificationSmsService::class),
            $this->createMock(NotificationWhatsAppService::class)
        );

        $result = $service->save([
            'intent' => 'test_email',
            'notification_default_language' => 'Italian',
            'email_provider_name' => 'smtp',
            'test_email_to' => 'alerts@example.com',
        ], 85);

        $this->assertSame('error', $result['type']);
        $this->assertSame('Email integration is not configured yet.', $result['message']);
        $this->assertFalse($result['html']);
    }

    public function testNotificationSettingsServiceSaveWhatsAppReturnsStrictIntegrationError(): void
    {
        $settingModel = $this->createMock(SettingModel::class);
        $settingModel->expects($this->once())
            ->method('upsert')
            ->with('notifications.default_language', 'Portuguese', 'string', 86)
            ->willReturn(true);

        $whatsAppService = $this->createMock(NotificationWhatsAppService::class);
        $whatsAppService->expects($this->once())
            ->method('saveIntegration')
            ->with(
                NotificationCatalog::BUSINESS_ID_DEFAULT,
                $this->callback(static function (array $payload): bool {
                    return ($payload['provider'] ?? null) === 'twilio'
                        && ($payload['is_active'] ?? null) === true
                        && ($payload['twilio_whatsapp_from'] ?? null) === null;
                })
            )
            ->willReturn([
                'ok' => false,
                'error' => 'Twilio WhatsApp From number is required.',
            ]);

        $service = new TestNotificationSettingsService(
            $settingModel,
            $this->createMock(NotificationEmailService::class),
            $this->createMock(NotificationSmsService::class),
            $whatsAppService
        );

        $result = $service->save([
            'intent' => 'save_whatsapp',
            'notification_default_language' => 'Portuguese',
            'whatsapp_provider' => 'twilio',
            'whatsapp_is_active' => '1',
        ], 86);

        $this->assertSame('error', $result['type']);
        $this->assertSame('Twilio WhatsApp From number is required.', $result['message']);
        $this->assertFalse($result['html']);
    }

    public function testNotificationSettingsServiceSaveSmsReturnsStrictIntegrationError(): void
    {
        $settingModel = $this->createMock(SettingModel::class);
        $settingModel->expects($this->once())
            ->method('upsert')
            ->with('notifications.default_language', 'Dutch', 'string', 87)
            ->willReturn(true);

        $smsService = $this->createMock(NotificationSmsService::class);
        $smsService->expects($this->once())
            ->method('saveIntegration')
            ->with(
                NotificationCatalog::BUSINESS_ID_DEFAULT,
                $this->callback(static function (array $payload): bool {
                    return ($payload['provider'] ?? null) === 'twilio'
                        && ($payload['is_active'] ?? null) === true
                        && ($payload['twilio_from_number'] ?? null) === null;
                })
            )
            ->willReturn([
                'ok' => false,
                'error' => 'Twilio From Number must be a valid +E.164 phone number.',
            ]);

        $service = new TestNotificationSettingsService(
            $settingModel,
            $this->createMock(NotificationEmailService::class),
            $smsService,
            $this->createMock(NotificationWhatsAppService::class)
        );

        $result = $service->save([
            'intent' => 'save_sms',
            'notification_default_language' => 'Dutch',
            'sms_provider' => 'twilio',
            'sms_is_active' => '1',
        ], 87);

        $this->assertSame('error', $result['type']);
        $this->assertSame('Twilio From Number must be a valid +E.164 phone number.', $result['message']);
        $this->assertFalse($result['html']);
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

final class RecordingSettingModel extends SettingModel
{
    public array $upserts = [];

    public function __construct()
    {
    }

    public function upsert(string $key, $value, string $type = 'string', ?int $userId = null): bool
    {
        $this->upserts[] = [
            'key' => $key,
            'value' => $value,
            'type' => $type,
            'userId' => $userId,
        ];

        return true;
    }
}

final class SpyNotificationWhatsAppService extends NotificationWhatsAppService
{
    public array $savedTemplates = [];
    public ?array $savedIntegrationPayload = null;

    public function __construct()
    {
    }

    public function saveIntegration(int $businessId, array $data): array
    {
        $this->savedIntegrationPayload = $data;

        return ['ok' => true];
    }

    public function saveTemplate(int $businessId, string $eventType, ?string $templateName, ?string $locale): array
    {
        $this->savedTemplates[] = [
            'businessId' => $businessId,
            'eventType' => $eventType,
            'templateName' => $templateName,
            'locale' => $locale,
{
    public function __construct(
        private readonly SettingModel $settingModel,
        private readonly NotificationEmailService $emailService,
        private readonly NotificationSmsService $smsService,
        private readonly NotificationWhatsAppService $whatsAppService,
    ) {
    }

    protected function getSettingModel(): SettingModel
    {
        return $this->settingModel;
    }

    protected function getEmailService(): NotificationEmailService
    {
        return $this->emailService;
    }

    protected function getSmsService(): NotificationSmsService
    {
        return $this->smsService;
    }

    protected function getWhatsAppService(): NotificationWhatsAppService
    {
        return $this->whatsAppService;
    }
}