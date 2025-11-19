<?php

namespace App\Database\Seeds;

use App\Models\CategoryModel;
use App\Models\CustomerModel;
use App\Models\ProviderScheduleModel;
use App\Models\ProviderStaffModel;
use App\Models\ServiceModel;
use App\Models\UserModel;
use CodeIgniter\Database\Seeder;
use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use DateTimeZone;
use Faker\Factory;
use Faker\Generator;

class SchedulingSampleDataSeeder extends Seeder
{
    private const SAMPLE_DOMAIN = 'sample.local';
    private const CUSTOMER_DOMAIN = 'samplepatients.local';
    private const STAFF_PER_PROVIDER = 2;
    private const EXTRA_STAFF = 10;
    private const CUSTOMER_COUNT = 120;
    private const APPOINTMENTS_MONTHS = 6;
    private const DAILY_SLOTS = ['08:00', '09:30', '11:00', '13:30', '15:00'];
    private const TIMEZONE = 'Africa/Johannesburg';

    private const PROVIDERS = [
        ['slug' => 'ayanda-mbeki',  'name' => 'Dr. Ayanda Mbeki',  'specialty' => 'Family Medicine',        'color' => '#0EA5E9'],
        ['slug' => 'kabelo-naidoo', 'name' => 'Dr. Kabelo Naidoo', 'specialty' => 'Sports Physiotherapy',  'color' => '#22C55E'],
        ['slug' => 'lindiwe-jacobs','name' => 'Dr. Lindiwe Jacobs','specialty' => 'Dermatology',           'color' => '#F97316'],
        ['slug' => 'thabo-radebe',  'name' => 'Dr. Thabo Radebe',  'specialty' => 'Cardiology',            'color' => '#8B5CF6'],
        ['slug' => 'naledi-khumalo','name' => 'Dr. Naledi Khumalo','specialty' => 'Mental Wellness',       'color' => '#EC4899'],
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
    ];

    private const HOLIDAYS = [
        '2025-12-16' => 'Day of Reconciliation',
        '2025-12-25' => 'Christmas Day',
        '2025-12-26' => 'Day of Goodwill',
        '2026-01-01' => 'New Year\'s Day',
        '2026-03-21' => 'Human Rights Day',
        '2026-04-03' => 'Good Friday',
        '2026-04-06' => 'Family Day',
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
        $staffIds = $this->seedStaff((count($providerIds) * self::STAFF_PER_PROVIDER) + self::EXTRA_STAFF, $faker);
        $providerServiceMap = $this->linkServicesToProviders($providerIds, array_column($services, 'id'));
        $this->assignStaffToProviders($providerIds, $staffIds);

        $weeklyTemplate = $this->getWeeklyTemplate();
        $this->seedSchedules($providerIds, $weeklyTemplate);
        $this->seedBusinessHours($providerIds, $weeklyTemplate);
        $holidayBlocks = $this->seedPublicHolidays();

        $customerIds = $this->seedCustomers(self::CUSTOMER_COUNT, $faker);

        $this->seedAppointments(
            $providerIds,
            $providerServiceMap,
            $serviceDurations,
            $customerIds,
            $weeklyTemplate,
            $holidayBlocks
        );

        $this->db->transComplete();

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
                $this->db->table('appointments')->whereIn('provider_id', $providerIds)->delete();
                $this->db->table('providers_services')->whereIn('provider_id', $providerIds)->delete();
                $this->db->table('provider_schedules')->whereIn('provider_id', $providerIds)->delete();
                $this->db->table('business_hours')->whereIn('provider_id', $providerIds)->delete();
                $this->db->table('blocked_times')->whereIn('provider_id', $providerIds)->delete();
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

            $userBuilder->whereIn('id', array_column($sampleUsers, 'id'))->delete();
        }

        $customerBuilder = $this->db->table('customers');
        $sampleCustomers = $customerBuilder
            ->select('id')
            ->like('email', '@' . self::CUSTOMER_DOMAIN, 'before')
            ->get()
            ->getResultArray();
        if ($sampleCustomers) {
            $ids = array_column($sampleCustomers, 'id');
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

    private function assignStaffToProviders(array $providerIds, array $staffIds): void
    {
        if (!$providerIds || !$staffIds) {
            return;
        }

        $providerStaff = new ProviderStaffModel();
        $cursor = 0;

        foreach ($providerIds as $providerId) {
            $assigned = array_slice($staffIds, $cursor, self::STAFF_PER_PROVIDER);
            if (!$assigned) {
                break;
            }
            $cursor += self::STAFF_PER_PROVIDER;

            foreach ($assigned as $staffId) {
                $providerStaff->assignStaff($providerId, $staffId, null, 'active');
            }
        }
    }

    private function linkServicesToProviders(array $providerIds, array $serviceIds): array
    {
        $map = [];
        if (!$providerIds || !$serviceIds) {
            return $map;
        }

        $chunkSize = 4;
        $serviceChunks = array_chunk($serviceIds, $chunkSize);
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
                ->where('start_time', $start)
                ->countAllResults();
            if (!$exists) {
                $this->db->table('blocked_times')->insert([
                    'provider_id' => null,
                    'start_time'  => $start,
                    'end_time'    => $date . ' 23:59:59',
                    'reason'      => 'Public Holiday (SA): ' . $label,
                    'created_at'  => $now,
                ]);
            }
            $blocked[$date] = true;
        }

        return $blocked;
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
                'phone'      => $this->fakerPhone($faker),
                'address'    => $faker->streetAddress(),
                'created_at' => date('Y-m-d H:i:s'),
            ], true);
        }

        return $ids;
    }

    private function seedAppointments(
        array $providerIds,
        array $providerServiceMap,
        array $serviceDurations,
        array $customerIds,
        array $weeklyTemplate,
        array $holidayBlocks
    ): void {
        if (!$providerIds || !$customerIds) {
            return;
        }

        $tz = new DateTimeZone(self::TIMEZONE);
        $startDate = new DateTimeImmutable('today', $tz);
        $endDate = $startDate->add(new DateInterval('P' . self::APPOINTMENTS_MONTHS . 'M'));

        $appointmentBuilder = $this->db->table('appointments');
        $batch = [];
        $customerCount = count($customerIds);
        $customerIndex = 0;

        $period = new DatePeriod($startDate, new DateInterval('P1D'), $endDate);
        foreach ($period as $day) {
            $dateKey = $day->format('Y-m-d');
            $dayKey = strtolower($day->format('l'));
            $dayConfig = $weeklyTemplate[$dayKey] ?? null;
            if (!$dayConfig || !$dayConfig['active']) {
                continue;
            }
            if (isset($holidayBlocks[$dateKey])) {
                continue;
            }

            foreach ($providerIds as $providerId) {
                $services = $providerServiceMap[$providerId] ?? null;
                if (!$services) {
                    continue;
                }

                $appointmentsForDay = 0;
                foreach (self::DAILY_SLOTS as $slot) {
                    $serviceId = $services[$appointmentsForDay % count($services)];
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

                    $batch[] = [
                        'provider_id'      => $providerId,
                        'customer_id'      => $customerId,
                        'service_id'       => $serviceId,
                        'start_time'       => $startTime->format('Y-m-d H:i:s'),
                        'end_time'         => $endTime->format('Y-m-d H:i:s'),
                        'appointment_date' => $startTime->format('Y-m-d'),
                        'appointment_time' => $startTime->format('H:i:s'),
                        'status'           => $this->randomStatus($startTime),
                        'notes'            => 'Auto-generated sample appointment',
                        'hash'             => bin2hex(random_bytes(16)),
                        'created_at'       => date('Y-m-d H:i:s'),
                        'updated_at'       => date('Y-m-d H:i:s'),
                    ];

                    $appointmentsForDay++;
                    if ($appointmentsForDay >= 5) {
                        break;
                    }
                }
            }

            if (count($batch) >= 400) {
                $appointmentBuilder->insertBatch($batch);
                $batch = [];
            }
        }

        if ($batch) {
            $appointmentBuilder->insertBatch($batch);
        }
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

    private function randomStatus(DateTimeImmutable $start): string
    {
        $now = new DateTimeImmutable('now', new DateTimeZone(self::TIMEZONE));
        if ($start < $now) {
            $options = ['completed', 'completed', 'completed', 'no-show', 'cancelled'];
        } else {
            $options = ['confirmed', 'confirmed', 'pending', 'confirmed', 'cancelled'];
        }
        return $options[array_rand($options)];
    }

    private function fakerPhone(Generator $faker): string
    {
        if (method_exists($faker, 'mobileNumber')) {
            return $faker->mobileNumber();
        }

        return $faker->phoneNumber();
    }
}
