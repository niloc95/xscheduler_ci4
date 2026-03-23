<?php

namespace App\Tests\Integration;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Regression coverage for routed auth contracts on the authenticated V1 settings API.
 */
final class SettingsApiV1AuthTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected $namespace = 'App';

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureSetupFlag();
    }

    public function testUpdateRequiresApiAuthentication(): void
    {
        $result = $this->withBodyFormat('json')
            ->post('/api/v1/settings', [
                'general.company_name' => 'Unauthorized Attempt',
            ]);

        $result->assertStatus(401);

        $payload = json_decode($result->getJSON(), true);
        $error = $payload['error'] ?? [];

        $this->assertSame('unauthorized', $error['code'] ?? null);
        $this->assertSame('Unauthorized', $error['message'] ?? null);
    }

    public function testAuthenticatedUpdateRejectsEmptyPayload(): void
    {
        $result = $this->withSession($this->authenticatedSession())
            ->post('/api/v1/settings', []);

        $result->assertStatus(422);

        $payload = json_decode($result->getJSON(), true);
        $error = $payload['error'] ?? [];

        $this->assertSame('VALIDATION_ERROR', $error['code'] ?? null);
        $this->assertSame('Validation failed', $error['message'] ?? null);
        $this->assertSame('Invalid payload - must be JSON or form data', $error['details'] ?? null);
    }

    public function testAuthenticatedLogoUploadRejectsMissingFile(): void
    {
        $result = $this->withSession($this->authenticatedSession())
            ->post('/api/v1/settings/logo', []);

        $result->assertStatus(422);

        $payload = json_decode($result->getJSON(), true);
        $error = $payload['error'] ?? [];

        $this->assertSame('VALIDATION_ERROR', $error['code'] ?? null);
        $this->assertSame('Validation failed', $error['message'] ?? null);
        $this->assertSame('No logo file received.', $error['details'] ?? null);
    }

    public function testAuthenticatedIconUploadRejectsMissingFile(): void
    {
        $result = $this->withSession($this->authenticatedSession())
            ->post('/api/v1/settings/icon', []);

        $result->assertStatus(422);

        $payload = json_decode($result->getJSON(), true);
        $error = $payload['error'] ?? [];

        $this->assertSame('VALIDATION_ERROR', $error['code'] ?? null);
        $this->assertSame('Validation failed', $error['message'] ?? null);
        $this->assertSame('No icon file received.', $error['details'] ?? null);
    }

    public function testAuthenticatedUpdateRejectsMalformedJsonPayload(): void
    {
        $result = $this->withSession($this->authenticatedSession())
            ->withHeaders(['Content-Type' => 'application/json'])
            ->withBody('{"general.company_name":')
            ->post('/api/v1/settings');

        $result->assertStatus(422);

        $payload = json_decode($result->getJSON(), true);
        $error = $payload['error'] ?? [];

        $this->assertSame('VALIDATION_ERROR', $error['code'] ?? null);
        $this->assertSame('Validation failed', $error['message'] ?? null);
        $this->assertSame('Invalid JSON payload', $error['details'] ?? null);
    }

    public function testAuthenticatedUpdatePersistsSettingAndIgnoresTransportKeys(): void
    {
        $db = \Config\Database::connect('tests');
        $keys = [
            'general.integration_transport_marker',
            'csrf_test_name',
            'form_source',
        ];

        $db->table('settings')->whereIn('setting_key', $keys)->delete();

        try {
            $result = $this->withSession($this->authenticatedSession())
                ->post('/api/v1/settings', [
                    'general.integration_transport_marker' => 'transport-clean',
                    'csrf_test_name' => 'abc123',
                    'form_source' => 'settings-ui',
                ]);

            $result->assertOK();

            $payload = json_decode($result->getJSON(), true);
            $this->assertSame(1, $payload['data']['updated'] ?? null);

            $rows = $db->table('settings')
                ->whereIn('setting_key', $keys)
                ->get()
                ->getResultArray();

            $byKey = [];
            foreach ($rows as $row) {
                $byKey[$row['setting_key']] = $row;
            }

            $this->assertSame('transport-clean', $byKey['general.integration_transport_marker']['setting_value'] ?? null);
            $this->assertArrayNotHasKey('csrf_test_name', $byKey);
            $this->assertArrayNotHasKey('form_source', $byKey);
        } finally {
            $db->table('settings')->whereIn('setting_key', $keys)->delete();
        }
    }

    public function testAuthenticatedJsonUpdatePersistsTypedValues(): void
    {
        $db = \Config\Database::connect('tests');
        $keys = [
            'general.integration_json_marker',
            'general.integration_bool_marker',
        ];

        $db->table('settings')->whereIn('setting_key', $keys)->delete();

        try {
            $result = $this->withSession($this->authenticatedSession())
                ->withBodyFormat('json')
                ->post('/api/v1/settings', [
                    'general.integration_json_marker' => ['mode' => 'json'],
                    'general.integration_bool_marker' => true,
                ]);

            $result->assertOK();

            $payload = json_decode($result->getJSON(), true);
            $this->assertSame(2, $payload['data']['updated'] ?? null);

            $rows = $db->table('settings')
                ->whereIn('setting_key', $keys)
                ->get()
                ->getResultArray();

            $byKey = [];
            foreach ($rows as $row) {
                $byKey[$row['setting_key']] = $row;
            }

            $this->assertSame('json', $byKey['general.integration_json_marker']['setting_type'] ?? null);
            $this->assertSame(['mode' => 'json'], json_decode((string) ($byKey['general.integration_json_marker']['setting_value'] ?? ''), true));
            $this->assertSame('bool', $byKey['general.integration_bool_marker']['setting_type'] ?? null);
            $this->assertSame('true', (string) ($byKey['general.integration_bool_marker']['setting_value'] ?? ''));
        } finally {
            $db->table('settings')->whereIn('setting_key', $keys)->delete();
        }
    }

    public function testAuthenticatedIndexCanFilterByPrefix(): void
    {
        $db = \Config\Database::connect('tests');
        $keys = [
            'general.integration_prefix_marker',
            'localization.integration_prefix_marker',
        ];

        $db->table('settings')->whereIn('setting_key', $keys)->delete();

        try {
            $now = date('Y-m-d H:i:s');
            $db->table('settings')->insertBatch([
                [
                    'setting_key' => 'general.integration_prefix_marker',
                    'setting_value' => 'general-only',
                    'setting_type' => 'string',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'setting_key' => 'localization.integration_prefix_marker',
                    'setting_value' => 'localization-only',
                    'setting_type' => 'string',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);

            $result = $this->withSession($this->authenticatedSession())
                ->get('/api/v1/settings?prefix=general.');

            $result->assertOK();

            $payload = json_decode($result->getJSON(), true);
            $data = $payload['data'] ?? [];

            $this->assertSame('general-only', $data['general.integration_prefix_marker'] ?? null);
            $this->assertArrayNotHasKey('localization.integration_prefix_marker', $data);
        } finally {
            $db->table('settings')->whereIn('setting_key', $keys)->delete();
        }
    }

    private function authenticatedSession(): array
    {
        return [
            'isLoggedIn' => true,
            'user_id' => 1,
            'user' => [
                'id' => 1,
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
}