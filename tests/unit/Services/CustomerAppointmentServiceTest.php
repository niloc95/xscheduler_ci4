<?php

namespace Tests\Unit\Services;

use App\Services\CustomerAppointmentService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class CustomerAppointmentServiceTest extends CIUnitTestCase
{
    public function testGetAutofillDataIncludesFavoriteProviderServiceAndStats(): void
    {
        $this->configureTestingDatabaseEnvironment();

        $db = \Config\Database::connect('tests');
        $now = date('Y-m-d H:i:s');

        $providerEmail = 'customer-appointment-provider-' . uniqid('', true) . '@example.com';
        $customerEmail = 'customer-appointment-' . uniqid('', true) . '@example.com';

        $db->table('users')->insert([
            'name' => 'Autofill Provider',
            'email' => $providerEmail,
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'provider',
            'status' => 'active',
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $providerId = (int) $db->insertID();

        $db->table('services')->insert([
            'name' => 'Autofill Service',
            'description' => 'Regression service for customer appointment autofill',
            'category_id' => null,
            'duration_min' => 45,
            'price' => 120.00,
            'active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $serviceId = (int) $db->insertID();

        $customerHash = hash('sha256', uniqid('customer_', true));
        $db->table('customers')->insert([
            'first_name' => 'Jamie',
            'last_name' => 'Customer',
            'email' => $customerEmail,
            'phone' => '+15550004444',
            'address' => '123 Service Lane',
            'hash' => $customerHash,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $customerId = (int) $db->insertID();

        $appointmentIds = [];

        try {
            $db->table('appointments')->insert([
                'customer_id' => $customerId,
                'provider_id' => $providerId,
                'service_id' => $serviceId,
                'hash' => hash('sha256', uniqid('appointment_', true)),
                'start_at' => gmdate('Y-m-d H:i:s', strtotime('-7 days')),
                'end_at' => gmdate('Y-m-d H:i:s', strtotime('-7 days +45 minutes')),
                'status' => 'completed',
                'notes' => 'Completed appointment for autofill stats',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $appointmentIds[] = (int) $db->insertID();

            $db->table('appointments')->insert([
                'customer_id' => $customerId,
                'provider_id' => $providerId,
                'service_id' => $serviceId,
                'hash' => hash('sha256', uniqid('appointment_', true)),
                'start_at' => gmdate('Y-m-d H:i:s', strtotime('+3 days')),
                'end_at' => gmdate('Y-m-d H:i:s', strtotime('+3 days +45 minutes')),
                'status' => 'confirmed',
                'notes' => 'Upcoming appointment for autofill stats',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $appointmentIds[] = (int) $db->insertID();

            $service = new CustomerAppointmentService();
            $result = $service->getAutofillData($customerId);

            $this->assertSame('Jamie', $result['customer']['first_name'] ?? null);
            $this->assertSame('Customer', $result['customer']['last_name'] ?? null);
            $this->assertSame($providerId, $result['preferences']['favorite_provider_id'] ?? null);
            $this->assertSame('Autofill Provider', $result['preferences']['favorite_provider_name'] ?? null);
            $this->assertSame($serviceId, $result['preferences']['favorite_service_id'] ?? null);
            $this->assertSame('Autofill Service', $result['preferences']['favorite_service_name'] ?? null);
            $this->assertSame(2, $result['stats']['total_appointments'] ?? null);
            $this->assertSame(1, $result['stats']['completed'] ?? null);
        } finally {
            if ($appointmentIds !== []) {
                $db->table('appointments')->whereIn('id', $appointmentIds)->delete();
            }
            $db->table('customers')->where('id', $customerId ?? 0)->delete();
            $db->table('services')->where('id', $serviceId ?? 0)->delete();
            $db->table('users')->where('id', $providerId ?? 0)->delete();
        }
    }

    public function testGetCustomerByHashReturnsCustomerWithAppointmentStats(): void
    {
        $this->configureTestingDatabaseEnvironment();

        $db = \Config\Database::connect('tests');
        $now = date('Y-m-d H:i:s');

        $providerEmail = 'customer-hash-provider-' . uniqid('', true) . '@example.com';
        $customerEmail = 'customer-hash-' . uniqid('', true) . '@example.com';

        $db->table('users')->insert([
            'name' => 'Hash Lookup Provider',
            'email' => $providerEmail,
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'provider',
            'status' => 'active',
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $providerId = (int) $db->insertID();

        $db->table('services')->insert([
            'name' => 'Hash Lookup Service',
            'description' => 'Regression service for customer hash lookup',
            'category_id' => null,
            'duration_min' => 30,
            'price' => 95.00,
            'active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $serviceId = (int) $db->insertID();

        $customerHash = hash('sha256', uniqid('customer_', true));
        $db->table('customers')->insert([
            'first_name' => 'Taylor',
            'last_name' => 'Lookup',
            'email' => $customerEmail,
            'phone' => '+15550005555',
            'hash' => $customerHash,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $customerId = (int) $db->insertID();

        $appointmentIds = [];

        try {
            $db->table('appointments')->insert([
                'customer_id' => $customerId,
                'provider_id' => $providerId,
                'service_id' => $serviceId,
                'hash' => hash('sha256', uniqid('appointment_', true)),
                'start_at' => gmdate('Y-m-d H:i:s', strtotime('-2 days')),
                'end_at' => gmdate('Y-m-d H:i:s', strtotime('-2 days +30 minutes')),
                'status' => 'completed',
                'notes' => 'Completed appointment for customer hash stats',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $appointmentIds[] = (int) $db->insertID();

            $service = new CustomerAppointmentService();
            $result = $service->getCustomerByHash($customerHash);

            $this->assertSame($customerId, $result['id'] ?? null);
            $this->assertSame('Taylor', $result['first_name'] ?? null);
            $this->assertSame(1, $result['appointment_stats']['total'] ?? null);
            $this->assertSame(1, $result['appointment_stats']['completed'] ?? null);
            $this->assertSame($providerId, $result['appointment_stats']['favorite_provider_id'] ?? null);
            $this->assertSame($serviceId, $result['appointment_stats']['favorite_service_id'] ?? null);
            $this->assertNull($service->getCustomerByHash('missing-hash-value'));
        } finally {
            if ($appointmentIds !== []) {
                $db->table('appointments')->whereIn('id', $appointmentIds)->delete();
            }
            $db->table('customers')->where('id', $customerId ?? 0)->delete();
            $db->table('services')->where('id', $serviceId ?? 0)->delete();
            $db->table('users')->where('id', $providerId ?? 0)->delete();
        }
    }

    public function testSearchAllAppointmentsFiltersAndEnrichesAdminResults(): void
    {
        $this->configureTestingDatabaseEnvironment();

        $db = \Config\Database::connect('tests');
        $now = date('Y-m-d H:i:s');

        $providerOneEmail = 'search-provider-one-' . uniqid('', true) . '@example.com';
        $providerTwoEmail = 'search-provider-two-' . uniqid('', true) . '@example.com';
        $customerOneEmail = 'search-customer-one-' . uniqid('', true) . '@example.com';
        $customerTwoEmail = 'search-customer-two-' . uniqid('', true) . '@example.com';

        $providerIds = [];
        $serviceIds = [];
        $customerIds = [];
        $appointmentIds = [];

        try {
            $db->table('users')->insert([
                'name' => 'Search Provider One',
                'email' => $providerOneEmail,
                'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
                'role' => 'provider',
                'status' => 'active',
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $providerIds[] = (int) $db->insertID();

            $db->table('users')->insert([
                'name' => 'Search Provider Two',
                'email' => $providerTwoEmail,
                'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
                'role' => 'provider',
                'status' => 'active',
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $providerIds[] = (int) $db->insertID();

            $db->table('services')->insert([
                'name' => 'Deep Tissue Massage',
                'description' => 'Regression service for admin search results',
                'category_id' => null,
                'duration_min' => 60,
                'price' => 140.00,
                'active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $serviceIds[] = (int) $db->insertID();

            $db->table('services')->insert([
                'name' => 'Quick Trim',
                'description' => 'Secondary service for admin search filtering',
                'category_id' => null,
                'duration_min' => 20,
                'price' => 45.00,
                'active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $serviceIds[] = (int) $db->insertID();

            $db->table('customers')->insert([
                'first_name' => 'Morgan',
                'last_name' => 'Search',
                'email' => $customerOneEmail,
                'phone' => '+15550006666',
                'hash' => hash('sha256', uniqid('customer_', true)),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $customerIds[] = (int) $db->insertID();

            $db->table('customers')->insert([
                'first_name' => 'Jordan',
                'last_name' => 'Other',
                'email' => $customerTwoEmail,
                'phone' => '+15550007777',
                'hash' => hash('sha256', uniqid('customer_', true)),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $customerIds[] = (int) $db->insertID();

            $db->table('appointments')->insert([
                'customer_id' => $customerIds[0],
                'provider_id' => $providerIds[0],
                'service_id' => $serviceIds[0],
                'hash' => hash('sha256', uniqid('appointment_', true)),
                'start_at' => '2026-03-15 14:00:00',
                'end_at' => '2026-03-15 15:00:00',
                'status' => 'confirmed',
                'notes' => 'Massage appointment for admin search regression',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $appointmentIds[] = (int) $db->insertID();

            $db->table('appointments')->insert([
                'customer_id' => $customerIds[1],
                'provider_id' => $providerIds[1],
                'service_id' => $serviceIds[1],
                'hash' => hash('sha256', uniqid('appointment_', true)),
                'start_at' => '2026-03-16 09:00:00',
                'end_at' => '2026-03-16 09:20:00',
                'status' => 'cancelled',
                'notes' => 'Trim appointment for admin search filtering',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $appointmentIds[] = (int) $db->insertID();

            $service = new CustomerAppointmentService();
            $result = $service->searchAllAppointments([
                'search' => 'Massage',
                'provider_id' => $providerIds[0],
                'status' => 'confirmed',
                'date_from' => '2026-03-15',
                'date_to' => '2026-03-15',
            ], 1, 10);

            $this->assertSame(1, $result['pagination']['total'] ?? null);
            $this->assertSame(false, $result['pagination']['has_more'] ?? null);
            $this->assertCount(1, $result['data'] ?? []);
            $this->assertSame('Morgan Search', $result['data'][0]['customer_name'] ?? null);
            $this->assertSame('Search Provider One', $result['data'][0]['provider_name'] ?? null);
            $this->assertSame('Deep Tissue Massage', $result['data'][0]['service_name'] ?? null);
            $this->assertSame(60, $result['data'][0]['duration_min'] ?? null);
            $this->assertArrayHasKey('status_label', $result['data'][0]);
            $this->assertSame('Massage', $result['filters']['search'] ?? null);
        } finally {
            if ($appointmentIds !== []) {
                $db->table('appointments')->whereIn('id', $appointmentIds)->delete();
            }
            if ($customerIds !== []) {
                $db->table('customers')->whereIn('id', $customerIds)->delete();
            }
            if ($serviceIds !== []) {
                $db->table('services')->whereIn('id', $serviceIds)->delete();
            }
            if ($providerIds !== []) {
                $db->table('users')->whereIn('id', $providerIds)->delete();
            }
        }
    }

    public function testGetHistoryAppliesLocalDateFiltersAndReturnsPaginatedEnrichedAppointments(): void
    {
        $this->configureTestingDatabaseEnvironment();

        $db = \Config\Database::connect('tests');
        $now = date('Y-m-d H:i:s');

        $providerEmail = 'history-provider-' . uniqid('', true) . '@example.com';
        $customerEmail = 'history-customer-' . uniqid('', true) . '@example.com';
        $settingKeys = ['localization.timezone'];
        $appointmentIds = [];

        $db->table('settings')->whereIn('setting_key', $settingKeys)->delete();

        $db->table('users')->insert([
            'name' => 'History Provider',
            'email' => $providerEmail,
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'provider',
            'status' => 'active',
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $providerId = (int) $db->insertID();

        $db->table('services')->insert([
            'name' => 'History Service',
            'description' => 'Regression service for customer history filtering',
            'category_id' => null,
            'duration_min' => 30,
            'price' => 90.00,
            'active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $serviceId = (int) $db->insertID();

        $customerHash = hash('sha256', uniqid('customer_', true));
        $db->table('customers')->insert([
            'first_name' => 'Riley',
            'last_name' => 'History',
            'email' => $customerEmail,
            'phone' => '+15550008888',
            'hash' => $customerHash,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $customerId = (int) $db->insertID();

        try {
            $db->table('settings')->insert([
                'setting_key' => 'localization.timezone',
                'setting_value' => 'Europe/Amsterdam',
                'setting_type' => 'string',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $db->table('appointments')->insert([
                'customer_id' => $customerId,
                'provider_id' => $providerId,
                'service_id' => $serviceId,
                'hash' => hash('sha256', uniqid('appointment_', true)),
                'start_at' => '2026-03-14 23:30:00',
                'end_at' => '2026-03-15 00:00:00',
                'status' => 'completed',
                'notes' => 'Included local-day appointment',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $appointmentIds[] = (int) $db->insertID();

            $db->table('appointments')->insert([
                'customer_id' => $customerId,
                'provider_id' => $providerId,
                'service_id' => $serviceId,
                'hash' => hash('sha256', uniqid('appointment_', true)),
                'start_at' => '2026-03-15 23:30:00',
                'end_at' => '2026-03-16 00:00:00',
                'status' => 'completed',
                'notes' => 'Excluded next-local-day appointment',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $appointmentIds[] = (int) $db->insertID();

            $service = new CustomerAppointmentService();
            $result = $service->getHistory($customerId, [
                'status' => ['completed'],
                'date_from' => '2026-03-15',
                'date_to' => '2026-03-15',
                'provider_id' => $providerId,
                'service_id' => $serviceId,
            ], 1, 1);

            $this->assertSame(1, $result['pagination']['total'] ?? null);
            $this->assertSame(1, $result['pagination']['total_pages'] ?? null);
            $this->assertFalse($result['pagination']['has_more'] ?? true);
            $this->assertCount(1, $result['data'] ?? []);
            $this->assertSame('History Service', $result['data'][0]['service_name'] ?? null);
            $this->assertSame('History Provider', $result['data'][0]['provider_name'] ?? null);
            $this->assertSame(30, $result['data'][0]['duration_min'] ?? null);
            $this->assertSame('Completed', $result['data'][0]['status_label'] ?? null);
            $this->assertSame('Sunday, March 15, 2026', $result['data'][0]['date_formatted'] ?? null);
            $this->assertSame('2026-03-15', $result['filters']['date_from'] ?? null);
        } finally {
            if ($appointmentIds !== []) {
                $db->table('appointments')->whereIn('id', $appointmentIds)->delete();
            }
            $db->table('settings')->whereIn('setting_key', $settingKeys)->delete();
            $db->table('customers')->where('id', $customerId ?? 0)->delete();
            $db->table('services')->where('id', $serviceId ?? 0)->delete();
            $db->table('users')->where('id', $providerId ?? 0)->delete();
        }
    }

    public function testGetUpcomingReturnsOnlyFuturePendingAndConfirmedAppointmentsInAscendingOrder(): void
    {
        $this->configureTestingDatabaseEnvironment();

        $db = \Config\Database::connect('tests');
        $now = date('Y-m-d H:i:s');

        $providerEmail = 'upcoming-provider-' . uniqid('', true) . '@example.com';
        $customerEmail = 'upcoming-customer-' . uniqid('', true) . '@example.com';
        $appointmentIds = [];

        $db->table('users')->insert([
            'name' => 'Upcoming Provider',
            'email' => $providerEmail,
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'provider',
            'status' => 'active',
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $providerId = (int) $db->insertID();

        $db->table('services')->insert([
            'name' => 'Upcoming Service',
            'description' => 'Regression service for upcoming customer appointments',
            'category_id' => null,
            'duration_min' => 45,
            'price' => 110.00,
            'active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $serviceId = (int) $db->insertID();

        $db->table('customers')->insert([
            'first_name' => 'Avery',
            'last_name' => 'Upcoming',
            'email' => $customerEmail,
            'phone' => '+15550001111',
            'hash' => hash('sha256', uniqid('customer_', true)),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $customerId = (int) $db->insertID();

        try {
            $rows = [
                [
                    'start_at' => gmdate('Y-m-d H:i:s', strtotime('+1 day 09:00')),
                    'end_at' => gmdate('Y-m-d H:i:s', strtotime('+1 day 09:45')),
                    'status' => 'pending',
                    'notes' => 'Earliest upcoming appointment',
                ],
                [
                    'start_at' => gmdate('Y-m-d H:i:s', strtotime('+2 days 11:00')),
                    'end_at' => gmdate('Y-m-d H:i:s', strtotime('+2 days 11:45')),
                    'status' => 'confirmed',
                    'notes' => 'Second upcoming appointment',
                ],
                [
                    'start_at' => gmdate('Y-m-d H:i:s', strtotime('+3 days 14:00')),
                    'end_at' => gmdate('Y-m-d H:i:s', strtotime('+3 days 14:45')),
                    'status' => 'completed',
                    'notes' => 'Excluded completed future appointment',
                ],
                [
                    'start_at' => gmdate('Y-m-d H:i:s', strtotime('-1 day 08:00')),
                    'end_at' => gmdate('Y-m-d H:i:s', strtotime('-1 day 08:45')),
                    'status' => 'confirmed',
                    'notes' => 'Excluded past confirmed appointment',
                ],
            ];

            foreach ($rows as $row) {
                $db->table('appointments')->insert([
                    'customer_id' => $customerId,
                    'provider_id' => $providerId,
                    'service_id' => $serviceId,
                    'hash' => hash('sha256', uniqid('appointment_', true)),
                    'start_at' => $row['start_at'],
                    'end_at' => $row['end_at'],
                    'status' => $row['status'],
                    'notes' => $row['notes'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $appointmentIds[] = (int) $db->insertID();
            }

            $service = new CustomerAppointmentService();
            $result = $service->getUpcoming($customerId, 2);

            $this->assertCount(2, $result);
            $this->assertSame('pending', $result[0]['status'] ?? null);
            $this->assertSame('confirmed', $result[1]['status'] ?? null);
            $this->assertSame('Upcoming Service', $result[0]['service_name'] ?? null);
            $this->assertSame('Upcoming Provider', $result[0]['provider_name'] ?? null);
            $this->assertLessThanOrEqual(
                strtotime($result[1]['start_at'] ?? ''),
                strtotime($result[0]['start_at'] ?? '')
            );
            $this->assertSame('Pending', $result[0]['status_label'] ?? null);
        } finally {
            if ($appointmentIds !== []) {
                $db->table('appointments')->whereIn('id', $appointmentIds)->delete();
            }
            $db->table('customers')->where('id', $customerId ?? 0)->delete();
            $db->table('services')->where('id', $serviceId ?? 0)->delete();
            $db->table('users')->where('id', $providerId ?? 0)->delete();
        }
    }

    public function testGetPastReturnsOnlyPastAppointmentsWithPastTypeMetadata(): void
    {
        $this->configureTestingDatabaseEnvironment();

        $db = \Config\Database::connect('tests');
        $now = date('Y-m-d H:i:s');

        $providerEmail = 'past-provider-' . uniqid('', true) . '@example.com';
        $customerEmail = 'past-customer-' . uniqid('', true) . '@example.com';
        $appointmentIds = [];

        $db->table('users')->insert([
            'name' => 'Past Provider',
            'email' => $providerEmail,
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'provider',
            'status' => 'active',
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $providerId = (int) $db->insertID();

        $db->table('services')->insert([
            'name' => 'Past Service',
            'description' => 'Regression service for past customer appointments',
            'category_id' => null,
            'duration_min' => 30,
            'price' => 80.00,
            'active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $serviceId = (int) $db->insertID();

        $db->table('customers')->insert([
            'first_name' => 'Parker',
            'last_name' => 'Past',
            'email' => $customerEmail,
            'phone' => '+15550002222',
            'hash' => hash('sha256', uniqid('customer_', true)),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $customerId = (int) $db->insertID();

        try {
            $rows = [
                [
                    'start_at' => gmdate('Y-m-d H:i:s', strtotime('-3 days 09:00')),
                    'end_at' => gmdate('Y-m-d H:i:s', strtotime('-3 days 09:30')),
                    'status' => 'completed',
                    'notes' => 'Completed past appointment',
                ],
                [
                    'start_at' => gmdate('Y-m-d H:i:s', strtotime('+2 days 10:00')),
                    'end_at' => gmdate('Y-m-d H:i:s', strtotime('+2 days 10:30')),
                    'status' => 'cancelled',
                    'notes' => 'Cancelled future appointment counted as past by status',
                ],
                [
                    'start_at' => gmdate('Y-m-d H:i:s', strtotime('+1 day 11:00')),
                    'end_at' => gmdate('Y-m-d H:i:s', strtotime('+1 day 11:30')),
                    'status' => 'confirmed',
                    'notes' => 'Excluded upcoming appointment',
                ],
            ];

            foreach ($rows as $row) {
                $db->table('appointments')->insert([
                    'customer_id' => $customerId,
                    'provider_id' => $providerId,
                    'service_id' => $serviceId,
                    'hash' => hash('sha256', uniqid('appointment_', true)),
                    'start_at' => $row['start_at'],
                    'end_at' => $row['end_at'],
                    'status' => $row['status'],
                    'notes' => $row['notes'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $appointmentIds[] = (int) $db->insertID();
            }

            $service = new CustomerAppointmentService();
            $result = $service->getPast($customerId, 1, 10);

            $this->assertSame('past', $result['filters']['type'] ?? null);
            $this->assertSame(2, $result['pagination']['total'] ?? null);
            $this->assertCount(2, $result['data'] ?? []);
            $this->assertSame('cancelled', $result['data'][0]['status'] ?? null);
            $this->assertSame('completed', $result['data'][1]['status'] ?? null);
            $this->assertSame('Cancelled', $result['data'][0]['status_label'] ?? null);
            $this->assertSame('Past Service', $result['data'][0]['service_name'] ?? null);
        } finally {
            if ($appointmentIds !== []) {
                $db->table('appointments')->whereIn('id', $appointmentIds)->delete();
            }
            $db->table('customers')->where('id', $customerId ?? 0)->delete();
            $db->table('services')->where('id', $serviceId ?? 0)->delete();
            $db->table('users')->where('id', $providerId ?? 0)->delete();
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