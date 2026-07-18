<?php

namespace App\Database\Seeds;

use App\Models\CategoryModel;
use App\Models\CustomerModel;
use App\Models\LocationModel;
use App\Models\ProviderScheduleModel;
use App\Models\ProviderStaffModel;
use App\Models\ServiceModel;
use App\Models\UserModel;
use App\Services\AppointmentBookingService;
use App\Services\AvailabilityService;
use CodeIgniter\Database\Seeder;
use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use DateTimeZone;
use Faker\Factory;
use Faker\Generator;

/**
 * Production-like sample dataset.
 *
 * Base data (providers, staff, services, locations, schedules, customers) and
 * PAST appointment history are inserted directly. FUTURE appointments are
 * created through AppointmentBookingService::createAppointment() so every
 * normal application process runs: availability validation, customer
 * upsert/onboarding, UTC conversion, video-link generation, and notification
 * enqueue + inline dispatch. Reminders then flow via the enqueueDueReminders
 * scan (php spark notifications:dispatch-queue / web heartbeat).
 *
 * Email safety: an ACTIVE xs_business_integrations email row takes priority
 * over the Mailpit dev fallback — deactivate it before seeding if it points
 * at a real SMTP host.
 */
class SchedulingSampleDataSeeder extends Seeder
{
    private const SAMPLE_DOMAIN = 'sample.local';
    private const CUSTOMER_DOMAIN = 'samplepatients.local';
    private const STAFF_COUNT = 2;
    private const CUSTOMER_COUNT = 40;
    private const APPOINTMENTS_MONTHS = 3;
    private const PAST_HISTORY_WEEKS = 4;
    private const APPOINTMENTS_PER_PROVIDER_PER_DAY = 2;
    private const NEW_CUSTOMER_RATIO = 0.3;
    private const CONFIRMED_RATIO = 0.6;
    private const ONLINE_DELIVERY_RATIO = 0.15;
    private const SERVICES_PER_PROVIDER = 6;
    private const DAILY_SLOTS = ['08:00', '09:30', '11:00', '13:30', '15:00'];
    private const TIMEZONE = 'Africa/Johannesburg';

    private const PROVIDERS = [
        ['slug' => 'ayanda-mbeki',  'name' => 'Dr. Ayanda Mbeki',  'specialty' => 'Family Medicine',        'color' => '#0EA5E9'],
        ['slug' => 'kabelo-naidoo', 'name' => 'Dr. Kabelo Naidoo', 'specialty' => 'Sports Physiotherapy',  'color' => '#22C55E'],
        ['slug' => 'lindiwe-jacobs','name' => 'Dr. Lindiwe Jacobs','specialty' => 'Dermatology',           'color' => '#F97316'],
        ['slug' => 'thabo-radebe',  'name' => 'Dr. Thabo Radebe',  'specialty' => 'Cardiology',            'color' => '#8B5CF6'],
    ];

    private const CATEGORY_DEFINITIONS = [
        ['code' => 'general',     'name' => 'General & Preventative Care',    'color' => '#0EA5E9'],
        ['code' => 'derm',        'name' => 'Skin & Aesthetics',              'color' => '#F97316'],
        ['code' => 'physio',      'name' => 'Physiotherapy & Rehab',          'color' => '#22C55E'],
        ['code' => 'cardio',      'name' => 'Cardiac Wellness',               'color' => '#FB7185'],
        ['code' => 'neuro',       'name' => 'Mental & Neuro Care',            'color' => '#6366F1'],
        ['code' => 'dental',      'name' => 'Dental Hygiene',                 'color' => '#FACC15'],
        ['code' => 'nutrition',   'name' => 'Nutrition & Lifestyle',          'color' => '#14B8A6'],
        ['code' => 'ortho',       'name' => 'Orthopedic Support',             'color' => '#0EA5E9'],
        ['code' => 'peds',        'name' => 'Pediatric Care',                 'color' => '#22C55E'],
        ['code' => 'wellness',    'name' => 'Corporate & Wellness Programs',  'color' => '#EC4899'],
    ];

    private const SERVICE_DEFINITIONS = [
        ['code' => 'general-consult',     'name' => 'Comprehensive Consultation',        'category' => 'general',   'duration' => 60, 'price' => 850.00],
        ['code' => 'follow-up',           'name' => 'Follow-up Review',                 'category' => 'general',   'duration' => 45, 'price' => 620.00],
        ['code' => 'skin-assessment',     'name' => 'Advanced Skin Assessment',         'category' => 'derm',      'duration' => 60, 'price' => 950.00],
        ['code' => 'derma-peel',          'name' => 'Therapeutic Derma Peel',           'category' => 'derm',      'duration' => 45, 'price' => 780.00],
        ['code' => 'sports-rehab',        'name' => 'Sports Injury Rehabilitation',     'category' => 'physio',    'duration' => 60, 'price' => 900.00],
        ['code' => 'mobility-session',    'name' => 'Mobility & Stretch Session',       'category' => 'physio',    'duration' => 45, 'price' => 650.00],
        ['code' => 'cardio-screen',       'name' => 'Cardiac Health Screening',         'category' => 'cardio',    'duration' => 60, 'price' => 1100.00],
        ['code' => 'stress-echo',         'name' => 'Stress Echo Review',               'category' => 'cardio',    'duration' => 45, 'price' => 1250.00],
        ['code' => 'therapy-session',     'name' => 'Mindfulness Therapy Session',      'category' => 'neuro',     'duration' => 60, 'price' => 820.00],
        ['code' => 'burnout-recovery',    'name' => 'Burnout Recovery Coaching',        'category' => 'neuro',     'duration' => 60, 'price' => 890.00],
        ['code' => 'dental-cleaning',     'name' => 'Pro Dental Cleaning',              'category' => 'dental',    'duration' => 60, 'price' => 780.00],
        ['code' => 'whitening',           'name' => 'Smile Whitening Boost',            'category' => 'dental',    'duration' => 75, 'price' => 1250.00],
        ['code' => 'nutrition-plan',      'name' => 'Personalised Nutrition Plan',      'category' => 'nutrition', 'duration' => 60, 'price' => 690.00],
        ['code' => 'metabolic-reset',     'name' => 'Metabolic Reset Program',          'category' => 'nutrition', 'duration' => 45, 'price' => 720.00],
        ['code' => 'posture-assess',      'name' => 'Orthopedic Posture Assessment',    'category' => 'ortho',     'duration' => 60, 'price' => 970.00],
        ['code' => 'joint-care',          'name' => 'Joint Care Consultation',          'category' => 'ortho',     'duration' => 45, 'price' => 880.00],
        ['code' => 'pediatric-check',     'name' => 'Pediatric Wellness Check',         'category' => 'peds',      'duration' => 45, 'price' => 600.00],
        ['code' => 'teen-coaching',       'name' => 'Teen Wellness Coaching',           'category' => 'peds',      'duration' => 45, 'price' => 650.00],
        ['code' => 'corporate-wellness',  'name' => 'Corporate Wellness Workshop',      'category' => 'wellness',  'duration' => 90, 'price' => 1850.00],
        ['code' => 'executive-reset',     'name' => 'Executive Energy Reset',           'category' => 'wellness',  'duration' => 75, 'price' => 1650.00],
        ['code' => 'chronic-care',        'name' => 'Chronic Care Review',              'category' => 'general',   'duration' => 45, 'price' => 700.00],
        ['code' => 'acne-program',        'name' => 'Acne Treatment Program',           'category' => 'derm',      'duration' => 60, 'price' => 860.00],
        ['code' => 'hydro-therapy',       'name' => 'Hydrotherapy Session',             'category' => 'physio',    'duration' => 60, 'price' => 750.00],
        ['code' => 'bp-consult',          'name' => 'Blood Pressure Consultation',      'category' => 'cardio',    'duration' => 30, 'price' => 550.00],
    ];

    private const HOLIDAYS = [
        '2025-12-16' => 'Day of Reconciliation',
        '2025-12-25' => 'Christmas Day',
        '2025-12-26' => 'Day of Goodwill',
        '2026-01-01' => 'New Year\'s Day',
        '2026-03-21' => 'Human Rights Day',
        '2026-04-03' => 'Good Friday',
        '2026-04-06' => 'Family Day',
        '2026-08-10' => 'National Women\'s Day (observed)',
        '2026-09-24' => 'Heritage Day',
    ];

    public function run(): void
    {
        $faker = Factory::create('en_ZA');
        $this->db->transException(true)->transStart();

        $this->purgeExistingSampleData();

        $categoryIds = $this->seedCategories();
        $services = $this->seedServices($categoryIds);
        $serviceDurations = array_column($services, 'duration', 'id');
        $providerIds = $this->seedProviders($faker);
        $staffIds = $this->seedStaff(self::STAFF_COUNT, $faker);
        $this->seedUserRoles($providerIds, $staffIds);
        $providerServiceMap = $this->linkServicesToProviders($providerIds, array_column($services, 'id'));
        $this->assignStaffToProviders($providerIds, $staffIds);

        $weeklyTemplate = $this->getWeeklyTemplate();
        $this->seedSchedules($providerIds, $weeklyTemplate);
        $this->seedBusinessHours($providerIds, $weeklyTemplate);
        $holidayBlocks = $this->seedPublicHolidays();

        $locationMap = $this->seedLocations($providerIds, $faker);
        $customerIds = $this->seedCustomers(self::CUSTOMER_COUNT, $faker);

        $this->seedPastAppointments(
            $providerIds,
            $providerServiceMap,
            $serviceDurations,
            $customerIds,
            $locationMap,
            $weeklyTemplate,
            $holidayBlocks
        );

        $this->db->transComplete();

        echo "✅ Base sample data seeded (providers, staff, services, locations, customers, past history).\n";
        echo "→ Creating future appointments through the booking pipeline (notifications dispatch inline)...\n";

        // Runs OUTSIDE the transaction: createAppointment opens its own
        // transaction and dispatches SMTP inline — neither may nest inside
        // an open seeder transaction.
        $this->seedFutureAppointments(
            $providerIds,
            $providerServiceMap,
            $locationMap,
            $customerIds,
            $weeklyTemplate,
            $holidayBlocks,
            $faker
        );

        echo "✅ Scheduling sample data seeded successfully.\n";
    }

    private function purgeExistingSampleData(): void
    {
        $userBuilder = $this->db->table('users');
        $sampleUsers = $userBuilder
            ->select('id, role')
            ->like('email', '@' . self::SAMPLE_DOMAIN, 'before')
            ->get()
            ->getResultArray();

        if (!empty($sampleUsers)) {
            $providerIds = array_map('intval', array_column(array_filter($sampleUsers, static fn ($row) => $row['role'] === 'provider'), 'id'));
            $staffIds    = array_map('intval', array_column(array_filter($sampleUsers, static fn ($row) => $row['role'] === 'staff'), 'id'));

            if ($providerIds) {
                $this->purgeNotificationRowsForAppointments(
                    $this->collectAppointmentIds('provider_id', $providerIds)
                );
                $this->db->table('appointments')->whereIn('provider_id', $providerIds)->delete();
                $this->db->table('providers_services')->whereIn('provider_id', $providerIds)->delete();
                $this->db->table('provider_schedules')->whereIn('provider_id', $providerIds)->delete();
                $this->db->table('business_hours')->whereIn('provider_id', $providerIds)->delete();
                $this->db->table('blocked_times')->whereIn('provider_id', $providerIds)->delete();

                $locationRows = $this->db->table('locations')
                    ->select('id')
                    ->whereIn('provider_id', $providerIds)
                    ->get()
                    ->getResultArray();
                if ($locationRows) {
                    $locationIds = array_map('intval', array_column($locationRows, 'id'));
                    $this->db->table('location_days')->whereIn('location_id', $locationIds)->delete();
                    $this->db->table('locations')->whereIn('id', $locationIds)->delete();
                }
            }

            if ($providerIds || $staffIds) {
                $builder = $this->db->table('provider_staff_assignments');
                $builder->groupStart();
                if ($providerIds) {
                    $builder->whereIn('provider_id', $providerIds);
                }
                if ($providerIds && $staffIds) {
                    $builder->orWhereIn('staff_id', $staffIds);
                } elseif ($staffIds) {
                    $builder->whereIn('staff_id', $staffIds);
                }
                $builder->groupEnd();
                $builder->delete();
            }

            $sampleUserIds = array_map('intval', array_column($sampleUsers, 'id'));
            $this->db->table('user_roles')->whereIn('user_id', $sampleUserIds)->delete();
            $userBuilder->whereIn('id', $sampleUserIds)->delete();
        }

        $customerBuilder = $this->db->table('customers');
        $sampleCustomers = $customerBuilder
            ->select('id')
            ->like('email', '@' . self::CUSTOMER_DOMAIN, 'before')
            ->get()
            ->getResultArray();
        if ($sampleCustomers) {
            $ids = array_column($sampleCustomers, 'id');
            $this->purgeNotificationRowsForAppointments(
                $this->collectAppointmentIds('customer_id', $ids)
            );
            $this->db->table('appointments')->whereIn('customer_id', $ids)->delete();
            $customerBuilder->whereIn('id', $ids)->delete();
        }

        if (!empty(self::SERVICE_DEFINITIONS)) {
            $serviceNames = array_column(self::SERVICE_DEFINITIONS, 'name');
            $serviceRows = $this->db->table('services')
                ->select('id')
                ->whereIn('name', $serviceNames)
                ->get()
                ->getResultArray();
            if ($serviceRows) {
                $serviceIds = array_column($serviceRows, 'id');
                $this->purgeNotificationRowsForAppointments(
                    $this->collectAppointmentIds('service_id', $serviceIds)
                );
                $this->db->table('appointments')->whereIn('service_id', $serviceIds)->delete();
                $this->db->table('providers_services')->whereIn('service_id', $serviceIds)->delete();
                $this->db->table('services')->whereIn('id', $serviceIds)->delete();
            }
        }

        if (!empty(self::CATEGORY_DEFINITIONS)) {
            $categoryNames = array_column(self::CATEGORY_DEFINITIONS, 'name');
            $this->db->table('categories')->whereIn('name', $categoryNames)->delete();
        }

        $this->db->table('blocked_times')->like('reason', 'Public Holiday (SA):', 'after')->delete();
    }

    private function collectAppointmentIds(string $column, array $values): array
    {
        if (!$values) {
            return [];
        }

        $rows = $this->db->table('appointments')
            ->select('id')
            ->whereIn($column, $values)
            ->get()
            ->getResultArray();

        return array_map('intval', array_column($rows, 'id'));
    }

    private function purgeNotificationRowsForAppointments(array $appointmentIds): void
    {
        foreach (array_chunk($appointmentIds, 500) as $chunk) {
            $this->db->table('notification_queue')->whereIn('appointment_id', $chunk)->delete();
            $this->db->table('notification_delivery_logs')->whereIn('appointment_id', $chunk)->delete();
        }
    }

    private function seedCategories(): array
    {
        $categoryModel = new CategoryModel();
        $ids = [];
        foreach (self::CATEGORY_DEFINITIONS as $category) {
            $existing = $categoryModel->where('name', $category['name'])->first();
            if ($existing) {
                $ids[$category['code']] = (int) $existing['id'];
                continue;
            }

            $ids[$category['code']] = (int) $categoryModel->insert([
                'name'        => $category['name'],
                'description' => 'Sample dataset category',
                'color'       => $category['color'],
                'created_at'  => date('Y-m-d H:i:s'),
            ], true);
        }

        return $ids;
    }

    private function seedServices(array $categoryIds): array
    {
        $serviceModel = new ServiceModel();
        $services = [];
        foreach (self::SERVICE_DEFINITIONS as $service) {
            $existing = $serviceModel->where('name', $service['name'])->first();
            if ($existing) {
                $services[] = [
                    'id'       => (int) $existing['id'],
                    'code'     => $service['code'],
                    'duration' => (int) $existing['duration_min'],
                ];
                continue;
            }

            $id = (int) $serviceModel->insert([
                'name'         => $service['name'],
                'description'  => 'Auto-generated sample service',
                'duration_min' => $service['duration'],
                'price'        => $service['price'],
                'category_id'  => $categoryIds[$service['category']] ?? null,
                'active'       => 1,
                'created_at'   => date('Y-m-d H:i:s'),
            ], true);

            $services[] = [
                'id'       => $id,
                'code'     => $service['code'],
                'duration' => $service['duration'],
            ];
        }

        return $services;
    }

    private function seedProviders(Generator $faker): array
    {
        $userModel = new UserModel();
        $ids = [];
        foreach (self::PROVIDERS as $def) {
            $email = sprintf('%s@%s', $def['slug'], self::SAMPLE_DOMAIN);
            $existing = $userModel->where('email', $email)->first();
            if ($existing) {
                $ids[$def['slug']] = (int) $existing['id'];
                continue;
            }

            $ids[$def['slug']] = (int) $userModel->insert([
                'name'          => $def['name'],
                'email'         => $email,
                'phone'         => $this->fakerPhone($faker),
                'password_hash' => password_hash('SamplePass!23', PASSWORD_BCRYPT),
                'role'          => 'provider',
                'permissions'   => json_encode(['appointments' => true, 'calendar' => true]),
                'status'        => 'active',
                'is_active'     => 1,
                'color'         => $def['color'],
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ], true);
        }

        return $ids;
    }

    private function seedStaff(int $count, Generator $faker): array
    {
        $userModel = new UserModel();
        $staffIds = [];
        for ($i = 1; $i <= $count; $i++) {
            $email = sprintf('staff%02d@%s', $i, self::SAMPLE_DOMAIN);
            $existing = $userModel->where('email', $email)->first();
            if ($existing) {
                $staffIds[] = (int) $existing['id'];
                continue;
            }

            $staffIds[] = (int) $userModel->insert([
                'name'          => $faker->name(),
                'email'         => $email,
                'phone'         => $this->fakerPhone($faker),
                'password_hash' => password_hash('SamplePass!23', PASSWORD_BCRYPT),
                'role'          => 'staff',
                'permissions'   => json_encode(['appointments' => true]),
                'status'        => 'active',
                'is_active'     => 1,
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ], true);
        }

        return $staffIds;
    }

    /**
     * xs_user_roles is the authoritative role membership (auth-rbac contract);
     * xs_users.role is only the compatibility primary role. Mirrors the write
     * pattern in UserManagementMutationService::createUser().
     */
    private function seedUserRoles(array $providerIds, array $staffIds): void
    {
        $now = date('Y-m-d H:i:s');
        $rows = [];
        foreach ($providerIds as $userId) {
            $rows[] = ['user_id' => (int) $userId, 'role' => 'provider', 'created_at' => $now];
        }
        foreach ($staffIds as $userId) {
            $rows[] = ['user_id' => (int) $userId, 'role' => 'staff', 'created_at' => $now];
        }

        if (!$rows) {
            return;
        }

        $this->db->table('user_roles')->whereIn('user_id', array_column($rows, 'user_id'))->delete();
        $this->db->table('user_roles')->insertBatch($rows);
    }

    /**
     * Each staff member covers multiple providers (round-robin): with 2 staff
     * and 4 providers, staff01 → providers 1+3, staff02 → providers 2+4.
     */
    private function assignStaffToProviders(array $providerIds, array $staffIds): void
    {
        if (!$providerIds || !$staffIds) {
            return;
        }

        $providerStaff = new ProviderStaffModel();
        $staffCount = count($staffIds);

        foreach (array_values($providerIds) as $index => $providerId) {
            $staffId = $staffIds[$index % $staffCount];
            $providerStaff->assignStaff((int) $providerId, (int) $staffId, null, 'active');
        }
    }

    private function linkServicesToProviders(array $providerIds, array $serviceIds): array
    {
        $map = [];
        if (!$providerIds || !$serviceIds) {
            return $map;
        }

        $serviceChunks = array_chunk($serviceIds, self::SERVICES_PER_PROVIDER);
        $now = date('Y-m-d H:i:s');

        $index = 0;
        foreach ($providerIds as $providerId) {
            $servicesForProvider = $serviceChunks[$index] ?? $serviceChunks[array_key_last($serviceChunks)];
            $map[$providerId] = $servicesForProvider;
            $this->db->table('providers_services')->where('provider_id', $providerId)->delete();
            foreach ($servicesForProvider as $serviceId) {
                $this->db->table('providers_services')->insert([
                    'provider_id' => $providerId,
                    'service_id'  => $serviceId,
                    'created_at'  => $now,
                ]);
            }
            $index = ($index + 1) % count($serviceChunks);
        }

        return $map;
    }

    private function seedSchedules(array $providerIds, array $weeklyTemplate): void
    {
        $scheduleModel = new ProviderScheduleModel();
        foreach ($providerIds as $providerId) {
            $entries = [];
            foreach ($weeklyTemplate as $day => $config) {
                $entries[$day] = [
                    'start_time'  => $config['start'],
                    'end_time'    => $config['end'],
                    'break_start' => $config['break_start'],
                    'break_end'   => $config['break_end'],
                    'is_active'   => $config['active'] ? 1 : 0,
                ];
            }
            $scheduleModel->saveSchedule($providerId, $entries);
        }
    }

    private function seedBusinessHours(array $providerIds, array $weeklyTemplate): void
    {
        $now = date('Y-m-d H:i:s');
        foreach ($providerIds as $providerId) {
            $this->db->table('business_hours')->where('provider_id', $providerId)->delete();
            foreach ($weeklyTemplate as $day => $config) {
                if (!$config['active']) {
                    continue;
                }
                $weekday = $this->weekdayNumber($day);
                $this->db->table('business_hours')->insert([
                    'provider_id' => $providerId,
                    'weekday'     => $weekday,
                    'start_time'  => $config['start'],
                    'end_time'    => $config['end'],
                    'breaks_json' => json_encode([
                        ['start' => $config['break_start'], 'end' => $config['break_end']],
                    ]),
                    'created_at'  => $now,
                ]);
            }
        }
    }

    private function seedPublicHolidays(): array
    {
        $blocked = [];
        $now = date('Y-m-d H:i:s');
        foreach (self::HOLIDAYS as $date => $label) {
            $start = $date . ' 00:00:00';
            $exists = $this->db->table('blocked_times')
                ->where('provider_id', null)
                ->where('start_at', $start)
                ->countAllResults();
            if (!$exists) {
                $this->db->table('blocked_times')->insert([
                    'provider_id' => null,
                    'start_at'  => $start,
                    'end_at'    => $date . ' 23:59:59',
                    'reason'      => 'Public Holiday (SA): ' . $label,
                    'created_at'  => $now,
                ]);
            }
            $blocked[$date] = true;
        }

        return $blocked;
    }

    /**
     * One primary location per provider. Locations MUST be created with
     * working days (xs_location_days) — availability rejects every day for a
     * location that has no day rows. Days use 0=Sunday..6=Saturday; the weekly
     * template is Mon–Fri, so [1..5].
     *
     * @return array<int,int> providerId → locationId
     */
    private function seedLocations(array $providerIds, Generator $faker): array
    {
        $locationModel = new LocationModel();
        $map = [];

        foreach ($providerIds as $slug => $providerId) {
            $name = ucwords(str_replace('-', ' ', (string) $slug)) . ' Primary Practice';

            $existing = $locationModel->where('provider_id', $providerId)->where('name', $name)->first();
            if ($existing) {
                $locationId = (int) $existing['id'];
                if (empty($locationModel->getLocationDays($locationId))) {
                    $locationModel->setLocationDays($locationId, [1, 2, 3, 4, 5]);
                }
                $map[(int) $providerId] = $locationId;
                continue;
            }

            $locationId = $locationModel->createWithDays([
                'provider_id'    => (int) $providerId,
                'name'           => $name,
                'address'        => $faker->streetAddress() . ', ' . $faker->city(),
                'city'           => $faker->city(),
                'contact_number' => $this->fakerPhone($faker),
                'is_primary'     => 1,
                'is_active'      => 1,
            ], [1, 2, 3, 4, 5]);

            if ($locationId) {
                $map[(int) $providerId] = (int) $locationId;
            }
        }

        return $map;
    }

    private function seedCustomers(int $count, Generator $faker): array
    {
        $customerModel = new CustomerModel();
        $ids = [];
        for ($i = 1; $i <= $count; $i++) {
            $email = sprintf('patient%03d@%s', $i, self::CUSTOMER_DOMAIN);
            $existing = $customerModel->where('email', $email)->first();
            if ($existing) {
                $ids[] = (int) $existing['id'];
                continue;
            }

            $ids[] = (int) $customerModel->insert([
                'first_name' => $faker->firstName(),
                'last_name'  => $faker->lastName(),
                'email'      => $email,
                'phone'      => $this->samplePhone(),
                'address'    => $faker->streetAddress(),
                'created_at' => date('Y-m-d H:i:s'),
            ], true);
        }

        return $ids;
    }

    /**
     * Past appointment history for the returning-customer pool. Inserted
     * directly (terminal statuses only) — the booking pipeline would dispatch
     * confirmation emails for them, which is wrong for history. Times are
     * stored in UTC with stored_timezone + location snapshot, matching what
     * the pipeline persists.
     */
    private function seedPastAppointments(
        array $providerIds,
        array $providerServiceMap,
        array $serviceDurations,
        array $customerIds,
        array $locationMap,
        array $weeklyTemplate,
        array $holidayBlocks
    ): void {
        if (!$providerIds || !$customerIds) {
            return;
        }

        $locationModel = new LocationModel();
        $snapshots = [];
        foreach ($locationMap as $providerId => $locationId) {
            $snapshots[$providerId] = $locationModel->getLocationSnapshot((int) $locationId);
        }

        $tz = new DateTimeZone(self::TIMEZONE);
        $utc = new DateTimeZone('UTC');
        $endDate = new DateTimeImmutable('today', $tz);
        $startDate = $endDate->sub(new DateInterval('P' . (self::PAST_HISTORY_WEEKS * 7) . 'D'));

        $batch = [];
        $customerCount = count($customerIds);
        $customerIndex = 0;

        $period = new DatePeriod($startDate, new DateInterval('P1D'), $endDate);
        foreach ($period as $day) {
            $dateKey = $day->format('Y-m-d');
            $dayKey = strtolower($day->format('l'));
            $dayConfig = $weeklyTemplate[$dayKey] ?? null;
            if (!$dayConfig || !$dayConfig['active'] || isset($holidayBlocks[$dateKey])) {
                continue;
            }

            foreach ($providerIds as $providerId) {
                $services = $providerServiceMap[$providerId] ?? null;
                if (!$services) {
                    continue;
                }

                $slots = self::DAILY_SLOTS;
                shuffle($slots);
                $appointmentsForDay = 0;

                foreach ($slots as $slot) {
                    if ($appointmentsForDay >= self::APPOINTMENTS_PER_PROVIDER_PER_DAY) {
                        break;
                    }

                    $serviceId = $services[array_rand($services)];
                    $duration = $serviceDurations[$serviceId] ?? 60;
                    if (!$this->slotFitsSchedule($slot, $duration, $dayConfig)) {
                        continue;
                    }

                    $startTime = DateTimeImmutable::createFromFormat('Y-m-d H:i', $dateKey . ' ' . $slot, $tz);
                    if (!$startTime) {
                        continue;
                    }
                    $endTime = $startTime->add(new DateInterval('PT' . $duration . 'M'));

                    if ($this->overlapsBreak($startTime, $endTime, $dayConfig)) {
                        continue;
                    }

                    $customerId = $customerIds[$customerIndex % $customerCount];
                    $customerIndex++;

                    $snapshot = $snapshots[$providerId] ?? [];
                    $bookedAt = $startTime->sub(new DateInterval('P' . random_int(3, 14) . 'D'));

                    $batch[] = [
                        'provider_id'      => $providerId,
                        'customer_id'      => $customerId,
                        'service_id'       => $serviceId,
                        'start_at'         => $startTime->setTimezone($utc)->format('Y-m-d H:i:s'),
                        'end_at'           => $endTime->setTimezone($utc)->format('Y-m-d H:i:s'),
                        'stored_timezone'  => self::TIMEZONE,
                        'status'           => $this->pastStatus(),
                        'notes'            => 'Auto-generated sample appointment',
                        'hash'             => bin2hex(random_bytes(16)),
                        'location_id'      => $snapshot['location_id'] ?? null,
                        'location_name'    => $snapshot['location_name'] ?? null,
                        'location_address' => $snapshot['location_address'] ?? null,
                        'location_contact' => $snapshot['location_contact'] ?? null,
                        'created_at'       => $bookedAt->setTimezone($utc)->format('Y-m-d H:i:s'),
                        'updated_at'       => $bookedAt->setTimezone($utc)->format('Y-m-d H:i:s'),
                    ];

                    $appointmentsForDay++;
                }
            }
        }

        if ($batch) {
            $this->db->table('appointments')->insertBatch($batch);
        }

        echo '→ Past history: ' . count($batch) . " appointments inserted for the returning-customer pool.\n";
    }

    /**
     * Future appointments through the REAL booking pipeline — this is what
     * makes the dataset production-like: availability validation, customer
     * upsert (returning vs new), UTC conversion, video links for online
     * sessions, and notification enqueue + inline dispatch all execute per
     * booking.
     */
    private function seedFutureAppointments(
        array $providerIds,
        array $providerServiceMap,
        array $locationMap,
        array $customerIds,
        array $weeklyTemplate,
        array $holidayBlocks,
        Generator $faker
    ): void {
        if (!$providerIds || !$customerIds) {
            return;
        }

        $bookingService = new AppointmentBookingService();
        // One instance for the whole run: static lookups (schedules, hours,
        // locations, settings) are cached per instance, while busy periods are
        // queried fresh per call — so booking #2 on a day sees booking #1.
        $availability = new AvailabilityService();

        $tz = new DateTimeZone(self::TIMEZONE);
        $startDate = new DateTimeImmutable('today', $tz);
        $endDate = $startDate->add(new DateInterval('P' . self::APPOINTMENTS_MONTHS . 'M'));

        $created = 0;
        $failed = 0;
        $skipped = 0;
        $newCustomers = 0;

        $period = new DatePeriod($startDate, new DateInterval('P1D'), $endDate);
        foreach ($period as $day) {
            $dateKey = $day->format('Y-m-d');
            $dayKey = strtolower($day->format('l'));
            $dayConfig = $weeklyTemplate[$dayKey] ?? null;
            if (!$dayConfig || !$dayConfig['active'] || isset($holidayBlocks[$dateKey])) {
                continue;
            }

            $dayCreated = 0;
            foreach ($providerIds as $providerId) {
                $providerId = (int) $providerId;
                $services = $providerServiceMap[$providerId] ?? [];
                if (!$services) {
                    continue;
                }

                for ($i = 0; $i < self::APPOINTMENTS_PER_PROVIDER_PER_DAY; $i++) {
                    $servicePick = null;
                    $slotPick = null;

                    $shuffled = $services;
                    shuffle($shuffled);
                    foreach ($shuffled as $serviceId) {
                        $slots = $availability->getAvailableSlots(
                            $providerId,
                            $dateKey,
                            (int) $serviceId,
                            0,
                            self::TIMEZONE,
                            null,
                            $locationMap[$providerId] ?? null
                        );
                        if ($slots) {
                            $servicePick = (int) $serviceId;
                            $slotPick = $slots[array_rand($slots)];
                            break;
                        }
                    }

                    if (!$slotPick) {
                        $skipped++;
                        continue;
                    }

                    $payload = [
                        'service_id'       => $servicePick,
                        'provider_id'      => $providerId,
                        'appointment_date' => $dateKey,
                        'appointment_time' => $slotPick['start']->format('H:i'),
                        'booking_channel'  => 'internal',
                        'status'           => (random_int(1, 100) <= self::CONFIRMED_RATIO * 100) ? 'confirmed' : 'pending',
                        'delivery_mode'    => (random_int(1, 100) <= self::ONLINE_DELIVERY_RATIO * 100) ? 'online_jitsi' : 'onsite',
                        'notes'            => 'Sample dataset booking',
                    ];
                    if (!empty($locationMap[$providerId])) {
                        $payload['location_id'] = $locationMap[$providerId];
                    }

                    if (random_int(1, 100) <= self::NEW_CUSTOMER_RATIO * 100) {
                        // Brand-new customer — exercises the onboarding/upsert path.
                        $first = $faker->firstName();
                        $last = $faker->lastName();
                        $payload['customer_first_name'] = $first;
                        $payload['customer_last_name'] = $last;
                        $payload['customer_email'] = sprintf(
                            '%s.%s.%04d@%s',
                            strtolower(preg_replace('/[^A-Za-z]/', '', $first) ?: 'new'),
                            strtolower(preg_replace('/[^A-Za-z]/', '', $last) ?: 'patient'),
                            random_int(0, 9999),
                            self::CUSTOMER_DOMAIN
                        );
                        $payload['customer_phone'] = $this->samplePhone();
                        $newCustomers++;
                    } else {
                        // Returning customer from the pre-seeded pool.
                        $payload['customer_id'] = $customerIds[array_rand($customerIds)];
                    }

                    try {
                        $result = $bookingService->createAppointment($payload, self::TIMEZONE);
                    } catch (\Throwable $e) {
                        $result = ['success' => false, 'message' => $e->getMessage()];
                    }

                    if (!empty($result['success'])) {
                        $created++;
                        $dayCreated++;
                    } else {
                        $failed++;
                        echo sprintf("  [fail] provider %d %s %s: %s\n", $providerId, $dateKey, $payload['appointment_time'], $result['message'] ?? 'unknown error');
                    }
                }
            }

            echo sprintf("  %s: +%d booked (total %d, skipped %d, failed %d)\n", $dateKey, $dayCreated, $created, $skipped, $failed);
        }

        echo sprintf(
            "→ Future bookings: %d created via the booking pipeline (%d new customers onboarded, %d slots skipped, %d failed).\n",
            $created,
            $newCustomers,
            $skipped,
            $failed
        );
    }

    private function getWeeklyTemplate(): array
    {
        return [
            'monday'    => ['start' => '08:00', 'end' => '17:00', 'break_start' => '12:30', 'break_end' => '13:30', 'active' => true],
            'tuesday'   => ['start' => '08:00', 'end' => '17:00', 'break_start' => '12:30', 'break_end' => '13:30', 'active' => true],
            'wednesday' => ['start' => '08:00', 'end' => '17:00', 'break_start' => '12:30', 'break_end' => '13:30', 'active' => true],
            'thursday'  => ['start' => '08:00', 'end' => '17:00', 'break_start' => '12:30', 'break_end' => '13:30', 'active' => true],
            'friday'    => ['start' => '08:00', 'end' => '17:00', 'break_start' => '12:30', 'break_end' => '13:15', 'active' => true],
            'saturday'  => ['start' => '09:00', 'end' => '13:00', 'break_start' => null,    'break_end' => null,     'active' => false],
            'sunday'    => ['start' => '00:00', 'end' => '00:00', 'break_start' => null,    'break_end' => null,     'active' => false],
        ];
    }

    private function weekdayNumber(string $day): int
    {
        return match ($day) {
            'sunday'    => 0,
            'monday'    => 1,
            'tuesday'   => 2,
            'wednesday' => 3,
            'thursday'  => 4,
            'friday'    => 5,
            'saturday'  => 6,
            default     => 1,
        };
    }

    private function slotFitsSchedule(string $slot, int $duration, array $config): bool
    {
        $start = strtotime($slot);
        $end = strtotime($slot) + ($duration * 60);
        $dayStart = strtotime($config['start']);
        $dayEnd = strtotime($config['end']);
        return $start >= $dayStart && $end <= $dayEnd;
    }

    private function overlapsBreak(DateTimeImmutable $start, DateTimeImmutable $end, array $config): bool
    {
        if (empty($config['break_start']) || empty($config['break_end'])) {
            return false;
        }
        $breakStart = DateTimeImmutable::createFromFormat('Y-m-d H:i', $start->format('Y-m-d') . ' ' . $config['break_start'], $start->getTimezone());
        $breakEnd = DateTimeImmutable::createFromFormat('Y-m-d H:i', $start->format('Y-m-d') . ' ' . $config['break_end'], $start->getTimezone());
        if (!$breakStart || !$breakEnd) {
            return false;
        }
        return $start < $breakEnd && $end > $breakStart;
    }

    private function pastStatus(): string
    {
        $options = ['completed', 'completed', 'completed', 'no-show', 'cancelled'];
        return $options[array_rand($options)];
    }

    /**
     * E.164 ZA mobile number — CustomerService normalizes phones through
     * PhoneNumberService and an unparseable phone fails the whole booking,
     * so generate strictly valid numbers.
     */
    private function samplePhone(): string
    {
        return sprintf('+2782%07d', random_int(0, 9999999));
    }

    private function fakerPhone(Generator $faker): string
    {
        if (method_exists($faker, 'mobileNumber')) {
            return $faker->mobileNumber();
        }

        return $faker->phoneNumber();
    }
}
