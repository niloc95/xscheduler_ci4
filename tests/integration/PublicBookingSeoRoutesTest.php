<?php

namespace App\Tests\Integration;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;

/**
 * Integration coverage for public booking SEO routes and filtering behavior.
 */
final class PublicBookingSeoRoutesTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected $namespace = 'App';

    private int $providerPrimaryId;
    private int $providerOtherId;
    private int $providerNoServiceId;

    private int $servicePrimaryId;
    private int $serviceOtherId;
    private int $serviceNoProviderId;

    private string $providerPrimarySlug = 'dr-ada';
    private string $providerNoServiceSlug = 'dr-no-service';
    private string $servicePrimarySlug = 'deep-clean';
    private string $serviceNoProviderSlug = 'unmapped-service';
    private static bool $schemaEnsured = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureTestingDatabaseEnvironment();
        $this->ensureSetupFlag();
        $this->ensureSeoSchemaCompatibility();
        $this->seedFixtureData();
    }

    protected function tearDown(): void
    {
        $db = \Config\Database::connect('tests');

        if (isset($this->providerPrimaryId, $this->providerOtherId, $this->providerNoServiceId)) {
            $providerIds = [$this->providerPrimaryId, $this->providerOtherId, $this->providerNoServiceId];
            $locationRows = $db->table('locations')->select('id')->whereIn('provider_id', $providerIds)->get()->getResultArray();
            $locationIds = array_map(static fn(array $row): int => (int) $row['id'], $locationRows);

            if ($locationIds !== []) {
                $db->table('location_days')->whereIn('location_id', $locationIds)->delete();
                $db->table('locations')->whereIn('id', $locationIds)->delete();
            }

            $db->table('providers_services')->whereIn('provider_id', $providerIds)->delete();
            $db->table('provider_schedules')->whereIn('provider_id', $providerIds)->delete();
            $db->table('business_hours')->whereIn('provider_id', $providerIds)->delete();
            $db->table('users')->whereIn('id', $providerIds)->delete();
        }

        if (isset($this->servicePrimaryId, $this->serviceOtherId, $this->serviceNoProviderId)) {
            $db->table('services')->whereIn('id', [$this->servicePrimaryId, $this->serviceOtherId, $this->serviceNoProviderId])->delete();
        }

        parent::tearDown();
    }

    public function testProviderRouteRendersExpectedSeoContextHappyPath(): void
    {
        $response = $this->get('/booking/p/' . $this->providerPrimarySlug);
        $response->assertOK();

        $context = $this->extractBookingContext($response);

        $this->assertSame($this->providerPrimarySlug, $context['initialFilter']['providerSlug'] ?? null);
        $this->assertArrayNotHasKey('providerId', $context['initialFilter'] ?? []);
        $this->assertArrayNotHasKey('id', $context['providers'][0] ?? []);
        $this->assertSame($this->providerPrimarySlug, (string) ($context['providers'][0]['slug'] ?? ''));
        $this->assertSame('/booking/p/' . $this->providerPrimarySlug, parse_url((string) ($context['seo']['canonical'] ?? ''), PHP_URL_PATH));
    }

    public function testProviderRouteNoResultPathKeepsProviderButHasNoBookableServices(): void
    {
        $response = $this->get('/booking/p/' . $this->providerNoServiceSlug);
        $response->assertOK();

        $context = $this->extractBookingContext($response);

        $this->assertSame($this->providerNoServiceSlug, $context['initialFilter']['providerSlug'] ?? null);
        $this->assertArrayNotHasKey('providerId', $context['initialFilter'] ?? []);
        $this->assertSame([], $context['services'] ?? []);
    }

    public function testServiceRouteFiltersProvidersByServiceHappyPath(): void
    {
        $response = $this->get('/booking/s/' . $this->servicePrimarySlug);
        $response->assertOK();

        $context = $this->extractBookingContext($response);
        $providerSlugs = array_map(static fn(array $provider): string => (string) ($provider['slug'] ?? ''), $context['providers'] ?? []);

        $this->assertSame($this->servicePrimarySlug, $context['initialFilter']['serviceSlug'] ?? null);
        $this->assertSame($this->servicePrimaryId, (int) ($context['initialFilter']['serviceId'] ?? 0));
        $this->assertContains($this->providerPrimarySlug, $providerSlugs);
        $this->assertNotContains('dr-other-provider', $providerSlugs);
        $this->assertSame('/booking/s/' . $this->servicePrimarySlug, parse_url((string) ($context['seo']['canonical'] ?? ''), PHP_URL_PATH));
    }

    public function testServiceRouteNoResultPathHasEmptyProvidersForUnmappedService(): void
    {
        $response = $this->get('/booking/s/' . $this->serviceNoProviderSlug);
        $response->assertOK();

        $context = $this->extractBookingContext($response);

        $this->assertSame($this->serviceNoProviderSlug, $context['initialFilter']['serviceSlug'] ?? null);
        $this->assertSame($this->serviceNoProviderId, (int) ($context['initialFilter']['serviceId'] ?? 0));
        $this->assertSame([], $context['providers'] ?? []);
    }

    public function testCityRouteFiltersByCityOrAreaHappyPath(): void
    {
        $cityResponse = $this->get('/booking/s/' . $this->servicePrimarySlug . '/mumbai');
        $cityResponse->assertOK();

        $cityContext = $this->extractBookingContext($cityResponse);
        $cityProviderSlugs = array_map(static fn(array $provider): string => (string) ($provider['slug'] ?? ''), $cityContext['providers'] ?? []);

        $this->assertContains($this->providerPrimarySlug, $cityProviderSlugs);
        $this->assertNotContains('dr-other-provider', $cityProviderSlugs);
        $this->assertSame('mumbai', strtolower((string) ($cityContext['initialFilter']['city'] ?? '')));

        $areaResponse = $this->get('/booking/s/' . $this->servicePrimarySlug . '/andheri%20west');
        $areaResponse->assertOK();

        $areaContext = $this->extractBookingContext($areaResponse);
        $areaProviderSlugs = array_map(static fn(array $provider): string => (string) ($provider['slug'] ?? ''), $areaContext['providers'] ?? []);

        $this->assertContains($this->providerPrimarySlug, $areaProviderSlugs);
    }

    public function testCityRouteNoResultPathReturnsNoProvidersForUnknownLocation(): void
    {
        $response = $this->get('/booking/s/' . $this->servicePrimarySlug . '/antarctica');
        $response->assertOK();

        $context = $this->extractBookingContext($response);

        $this->assertSame('antarctica', strtolower((string) ($context['initialFilter']['city'] ?? '')));
        $this->assertSame([], $context['providers'] ?? []);
        $this->assertSame('/booking/s/' . $this->servicePrimarySlug . '/antarctica', parse_url((string) ($context['seo']['canonical'] ?? ''), PHP_URL_PATH));
    }

    public function testIndexPageSchemaGraphIncludesProviderPersonNodes(): void
    {
        $response = $this->get('/booking');
        $response->assertOK();

        $schemas = $this->extractJsonLdBlocks($response);
        $graphNodes = [];
        foreach ($schemas as $schema) {
            if (isset($schema['@graph']) && is_array($schema['@graph'])) {
                $graphNodes = array_merge($graphNodes, $schema['@graph']);
            }
        }

        $this->assertNotSame([], $graphNodes, 'Expected JSON-LD @graph nodes on /booking.');

        $localBusinessNodes = array_values(array_filter($graphNodes, static fn(array $node): bool => ($node['@type'] ?? null) === 'LocalBusiness'));
        $personNodes = array_values(array_filter($graphNodes, static fn(array $node): bool => ($node['@type'] ?? null) === 'Person'));

        $this->assertNotSame([], $localBusinessNodes, 'Expected a LocalBusiness node in JSON-LD graph.');
        $this->assertNotSame([], $personNodes, 'Expected provider Person nodes in JSON-LD graph.');

        $personNames = array_values(array_map(static fn(array $node): string => (string) ($node['name'] ?? ''), $personNodes));
        $this->assertContains('Dr Ada Primary', $personNames);
    }

    private function extractBookingContext(TestResponse $response): array
    {
        $body = $response->getBody();
        $matched = preg_match('/<script id="public-booking-context" type="application\/json">(.*?)<\/script>/s', $body, $parts);

        $this->assertSame(1, $matched, 'Expected embedded booking context JSON script in response body.');

        $decoded = json_decode((string) ($parts[1] ?? ''), true);
        $this->assertIsArray($decoded, 'Embedded booking context JSON could not be decoded.');

        return $decoded;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractJsonLdBlocks(TestResponse $response): array
    {
        $body = $response->getBody();
        preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $body, $matches);

        $payloads = $matches[1] ?? [];
        $decoded = [];

        foreach ($payloads as $json) {
            $data = json_decode((string) $json, true);
            if (is_array($data)) {
                $decoded[] = $data;
            }
        }

        return $decoded;
    }

    private function seedFixtureData(): void
    {
        $db = \Config\Database::connect('tests');
        $now = date('Y-m-d H:i:s');

        $db->table('users')->insert([
            'name' => 'Dr Ada Primary',
            'email' => 'provider-ada-' . uniqid('', true) . '@example.com',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'provider',
            'slug' => $this->providerPrimarySlug,
            'status' => 'active',
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->providerPrimaryId = (int) $db->insertID();

        $db->table('users')->insert([
            'name' => 'Dr Other Provider',
            'email' => 'provider-other-' . uniqid('', true) . '@example.com',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'provider',
            'slug' => 'dr-other-provider',
            'status' => 'active',
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->providerOtherId = (int) $db->insertID();

        $db->table('users')->insert([
            'name' => 'Dr No Service',
            'email' => 'provider-noservice-' . uniqid('', true) . '@example.com',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'provider',
            'slug' => $this->providerNoServiceSlug,
            'status' => 'active',
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->providerNoServiceId = (int) $db->insertID();

        $db->table('services')->insert([
            'name' => 'Deep Clean',
            'slug' => $this->servicePrimarySlug,
            'description' => 'Primary service used for route filtering tests',
            'duration_min' => 30,
            'price' => 95.00,
            'active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->servicePrimaryId = (int) $db->insertID();

        $db->table('services')->insert([
            'name' => 'Other Service',
            'slug' => 'other-service',
            'description' => 'Service linked to other provider',
            'duration_min' => 45,
            'price' => 120.00,
            'active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->serviceOtherId = (int) $db->insertID();

        $db->table('services')->insert([
            'name' => 'Unmapped Service',
            'slug' => $this->serviceNoProviderSlug,
            'description' => 'No providers mapped to this service',
            'duration_min' => 20,
            'price' => 50.00,
            'active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->serviceNoProviderId = (int) $db->insertID();

        $db->table('providers_services')->insertBatch([
            [
                'provider_id' => $this->providerPrimaryId,
                'service_id' => $this->servicePrimaryId,
                'created_at' => $now,
            ],
            [
                'provider_id' => $this->providerOtherId,
                'service_id' => $this->serviceOtherId,
                'created_at' => $now,
            ],
        ]);

        $db->table('locations')->insert([
            'provider_id' => $this->providerPrimaryId,
            'name' => 'Mumbai Clinic',
            'address' => 'Andheri West, Mumbai',
            'city' => 'Mumbai',
            'area' => 'Andheri West',
            'contact_number' => '+919900000001',
            'is_primary' => 1,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $primaryLocationId = (int) $db->insertID();

        $db->table('locations')->insert([
            'provider_id' => $this->providerOtherId,
            'name' => 'Pune Clinic',
            'address' => 'Koregaon Park, Pune',
            'city' => 'Pune',
            'area' => 'Koregaon Park',
            'contact_number' => '+919900000002',
            'is_primary' => 1,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $otherLocationId = (int) $db->insertID();

        $db->table('location_days')->insertBatch([
            ['location_id' => $primaryLocationId, 'day_of_week' => 1],
            ['location_id' => $otherLocationId, 'day_of_week' => 1],
        ]);

        $db->table('business_hours')->insertBatch([
            [
                'provider_id' => $this->providerPrimaryId,
                'weekday' => 1,
                'start_time' => '09:00:00',
                'end_time' => '17:00:00',
                'breaks_json' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'provider_id' => $this->providerOtherId,
                'weekday' => 1,
                'start_time' => '09:00:00',
                'end_time' => '17:00:00',
                'breaks_json' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'provider_id' => $this->providerNoServiceId,
                'weekday' => 1,
                'start_time' => '09:00:00',
                'end_time' => '17:00:00',
                'breaks_json' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $this->seedSetting($db, 'localization.timezone', 'UTC', 'string');
        $this->seedSetting($db, 'general.company_name', 'SEO Test Clinic', 'string');
    }

    private function seedSetting($db, string $key, string $value, string $type): void
    {
        $now = date('Y-m-d H:i:s');
        $existing = $db->table('settings')->where('setting_key', $key)->get()->getRowArray();

        $payload = [
            'setting_value' => $value,
            'setting_type' => $type,
            'updated_at' => $now,
        ];

        if ($existing !== null) {
            $db->table('settings')->where('setting_key', $key)->update($payload);
            return;
        }

        $db->table('settings')->insert($payload + [
            'setting_key' => $key,
            'created_at' => $now,
        ]);
    }

    private function ensureSetupFlag(): void
    {
        $flagPath = WRITEPATH . 'setup_complete.flag';

        if (!is_file($flagPath)) {
            file_put_contents($flagPath, 'test');
        }
    }

    private function ensureSeoSchemaCompatibility(): void
    {
        if (self::$schemaEnsured) {
            return;
        }

        $db = \Config\Database::connect('tests');
        $forge = \Config\Database::forge();

        if (! $this->hasColumn($db, 'users', 'slug')) {
            $forge->addColumn('users', [
                'slug' => [
                    'type' => 'VARCHAR',
                    'constraint' => 150,
                    'null' => true,
                ],
            ]);
        }

        if (! $this->hasColumn($db, 'services', 'slug')) {
            $forge->addColumn('services', [
                'slug' => [
                    'type' => 'VARCHAR',
                    'constraint' => 150,
                    'null' => true,
                ],
            ]);
        }

        if (! $this->hasColumn($db, 'locations', 'city')) {
            $forge->addColumn('locations', [
                'city' => [
                    'type' => 'VARCHAR',
                    'constraint' => 100,
                    'null' => true,
                ],
            ]);
        }

        if (! $this->hasColumn($db, 'locations', 'area')) {
            $forge->addColumn('locations', [
                'area' => [
                    'type' => 'VARCHAR',
                    'constraint' => 100,
                    'null' => true,
                ],
            ]);
        }

        self::$schemaEnsured = true;
    }

    private function hasColumn($db, string $table, string $column): bool
    {
        $database = $db->getDatabase();
        $tableName = $db->prefixTable($table);
        $query = $db->query(
            'SELECT 1 FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ? LIMIT 1',
            [$database, $tableName, $column]
        );

        return $query->getNumRows() > 0;
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
            'database.tests.database' => $values['database.tests.database'] ?? null, // never fall back to the app/dev DB
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
