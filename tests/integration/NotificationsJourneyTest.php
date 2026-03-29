<?php

namespace App\Tests\Integration;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

final class NotificationsJourneyTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected $namespace = 'App';

    private ?int $queuedNotificationId = null;
    private ?int $deliveryLogId = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureTestingDatabaseEnvironment();
        $this->ensureSetupFlag();
        $this->seedNotificationFixtures();
    }

    protected function tearDown(): void
    {
        $db = \Config\Database::connect('tests');

        if ($this->deliveryLogId !== null) {
            $db->table('notification_delivery_logs')->where('id', $this->deliveryLogId)->delete();
        }

        if ($this->queuedNotificationId !== null) {
            $db->table('notification_queue')->where('id', $this->queuedNotificationId)->delete();
        }

        parent::tearDown();
    }

    public function testNotificationsIndexRedirectsGuestsToLogin(): void
    {
        $response = $this->get('/notifications');

        $response->assertStatus(302);
        $response->assertRedirectTo('/auth/login');
    }

    public function testAuthenticatedNotificationsIndexRendersNotificationCenterContract(): void
    {
        $response = $this->withSession($this->adminSession())
            ->get('/notifications?filter=all');

        $response->assertOK();
        $response->assertSee('Notifications');
        $response->assertSee('Appointment Reminder - Pending');
        $response->assertSee('Appointment Confirmed - Failed');
        $response->assertSee('/notifications/mark-read/log_' . $this->deliveryLogId);
        $response->assertSee('/notifications/mark-all-read');
        $response->assertSee('/notifications/delete/');
    }

    public function testMarkReadAndMarkAllReadLinksRoundTripForAuthenticatedUsers(): void
    {
        $referer = base_url('/notifications');

        $markRead = $this->withSession($this->adminSession())
            ->withHeaders(['Referer' => $referer])
            ->get('/notifications/mark-read/log_' . $this->deliveryLogId);

        $markRead->assertStatus(302);

        $markAllRead = $this->withSession($this->adminSession())
            ->withHeaders(['Referer' => $referer])
            ->get('/notifications/mark-all-read');

        $markAllRead->assertStatus(302);
    }

    private function seedNotificationFixtures(): void
    {
        $db = \Config\Database::connect('tests');
        $now = date('Y-m-d H:i:s');

        $db->table('notification_queue')->insert([
            'business_id' => 1,
            'channel' => 'email',
            'event_type' => 'appointment_reminder',
            'appointment_id' => null,
            'status' => 'queued',
            'attempts' => 0,
            'max_attempts' => 5,
            'run_after' => $now,
            'idempotency_key' => 'notifications-journey-queue-' . uniqid('', true),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->queuedNotificationId = (int) $db->insertID();

        $db->table('notification_delivery_logs')->insert([
            'business_id' => 1,
            'queue_id' => $this->queuedNotificationId,
            'channel' => 'email',
            'event_type' => 'appointment_confirmed',
            'appointment_id' => null,
            'recipient' => 'customer@example.com',
            'provider' => 'smtp',
            'status' => 'failed',
            'attempt' => 1,
            'error_message' => 'SMTP unavailable',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->deliveryLogId = (int) $db->insertID();
    }

    private function adminSession(): array
    {
        return [
            'isLoggedIn' => true,
            'user_id' => 999,
            'user' => [
                'id' => 999,
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'role' => 'admin',
            ],
        ];
    }

    private function ensureSetupFlag(): void
    {
        $flagPath = WRITEPATH . 'setup_complete.flag';

        if (!is_file($flagPath)) {
            file_put_contents($flagPath, 'test');
        }
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