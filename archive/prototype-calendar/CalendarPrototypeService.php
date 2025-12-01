<?php

namespace App\Services;

use App\Models\AppointmentModel;
use App\Models\UserModel;
use DateTimeImmutable;
use DateTimeZone;

class CalendarPrototypeService
{
    private const COLOR_TOKEN_MAP = [
        '#3B82F6' => 'blue-500',
        '#10B981' => 'emerald-500',
        '#F59E0B' => 'amber-500',
        '#EF4444' => 'red-500',
        '#8B5CF6' => 'violet-500',
        '#EC4899' => 'pink-500',
        '#06B6D4' => 'cyan-500',
        '#F97316' => 'orange-500',
        '#84CC16' => 'lime-500',
        '#6366F1' => 'indigo-500',
        '#14B8A6' => 'teal-500',
        '#F43F5E' => 'rose-500',
    ];

    private AppointmentModel $appointments;
    private UserModel $users;
    private LocalizationSettingsService $localization;

    public function __construct(
        ?AppointmentModel $appointments = null,
        ?UserModel $users = null,
        ?LocalizationSettingsService $localization = null,
    ) {
        $this->appointments = $appointments ?? new AppointmentModel();
        $this->users = $users ?? new UserModel();
        $this->localization = $localization ?? new LocalizationSettingsService();
    }

    public function buildBootstrapPayload(?string $rangeStart = null, ?string $rangeEnd = null): array
    {
        $timezone = $this->localization->getTimezone();
        $tz = new DateTimeZone($timezone);
        $range = $this->resolveRange($rangeStart, $rangeEnd, $tz);
        $providers = $this->mapProviders();
        $appointments = $this->mapAppointments($range['start'], $range['end'], $tz);

        $knownProviders = array_flip($providers['order']);
        foreach ($appointments as $appointment) {
            $providerId = $appointment['providerId'];
            if (!isset($knownProviders[$providerId])) {
                $providers['records'][] = [
                    'id' => $providerId,
                    'displayName' => 'Provider ' . $providerId,
                    'colorToken' => 'blue-500',
                    'location' => 'Main',
                    'orderingWeight' => count($providers['records']),
                    'isActive' => true,
                ];
                $providers['order'][] = $providerId;
                $knownProviders[$providerId] = true;
            }
        }

        return [
            'rangeStart' => $range['start']->format('Y-m-d'),
            'rangeEnd' => $range['end']->format('Y-m-d'),
            'timezone' => $timezone,
            'providers' => $providers['records'],
            'providerOrder' => $providers['order'],
            'appointments' => $appointments,
            'meta' => [
                'generatedAt' => (new DateTimeImmutable('now', $tz))->format(DATE_ATOM),
                'featureFlags' => [ 'calendar_prototype' => true ],
            ],
        ];
    }

    private function resolveRange(?string $start, ?string $end, DateTimeZone $tz): array
    {
        $now = new DateTimeImmutable('now', $tz);
        $startCursor = $this->coerceBoundary($start, $tz) ?? $now->modify('first day of this month')->setTime(0, 0);
        $endCursor = $this->coerceBoundary($end, $tz) ?? $startCursor->modify('+1 month');
        if ($endCursor <= $startCursor) {
            $endCursor = $startCursor->modify('+7 days');
        }
        return [
            'start' => $startCursor->setTime(0, 0),
            'end' => $endCursor->setTime(0, 0),
        ];
    }

    private function coerceBoundary(?string $value, DateTimeZone $tz): ?DateTimeImmutable
    {
        if (!$value) {
            return null;
        }
        try {
            return new DateTimeImmutable($value, $tz);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function mapProviders(): array
    {
        $rows = $this->users
            ->select('id, name, color, is_active, profile_image')
            ->where('role', 'provider')
            ->where('is_active', true)
            ->orderBy('name', 'ASC')
            ->findAll();

        $providers = [];
        $order = [];

        foreach ($rows as $index => $row) {
            $providerId = 'provider-' . (int) ($row['id'] ?? 0);
            $providers[] = [
                'id' => $providerId,
                'displayName' => $row['name'] ?? ('Provider ' . ($row['id'] ?? '')),
                'colorToken' => $this->mapColorToken($row['color'] ?? null),
                'location' => 'Main Location',
                'avatarUrl' => $row['profile_image'] ?? null,
                'orderingWeight' => $index,
                'isActive' => (bool) ($row['is_active'] ?? true),
            ];
            $order[] = $providerId;
        }

        return ['records' => $providers, 'order' => $order];
    }

    private function mapAppointments(DateTimeImmutable $start, DateTimeImmutable $end, DateTimeZone $tz): array
    {
        $builder = $this->appointments->builder();
        $builder->select('xs_appointments.*, 
                CONCAT(c.first_name, " ", COALESCE(c.last_name, "")) as customer_name,
                s.name as service_name,
                s.duration_min as service_duration')
            ->join('xs_customers as c', 'c.id = xs_appointments.customer_id', 'left')
            ->join('xs_services as s', 's.id = xs_appointments.service_id', 'left')
            ->where('xs_appointments.start_time >=', $start->format('Y-m-d 00:00:00'))
            ->where('xs_appointments.start_time <', $end->format('Y-m-d 00:00:00'))
            ->orderBy('xs_appointments.start_time', 'ASC');

        $rows = $builder->get()->getResultArray();

        return array_map(function (array $row) use ($tz) {
            $providerKey = 'provider-' . (int) ($row['provider_id'] ?? 0);
            return [
                'id' => 'apt-' . (int) ($row['id'] ?? 0),
                'providerId' => $providerKey,
                'patientName' => trim($row['customer_name'] ?? '') ?: 'Appointment #' . ($row['id'] ?? ''),
                'visitType' => $row['service_name'] ?? 'Visit',
                'status' => $row['status'] ?? 'pending',
                'start' => $this->formatIso($row['start_time'] ?? null, $tz),
                'end' => $this->formatIso($row['end_time'] ?? null, $tz),
                'durationMinutes' => (int) ($row['service_duration'] ?? 30),
            ];
        }, $rows);
    }

    private function formatIso(?string $value, DateTimeZone $tz): ?string
    {
        if (!$value) {
            return null;
        }
        try {
            return (new DateTimeImmutable($value, $tz))->format(DATE_ATOM);
        } catch (\Throwable $e) {
            return $value;
        }
    }

    private function mapColorToken(?string $hex): string
    {
        if (!$hex) {
            return 'blue-500';
        }
        $normalized = strtoupper($hex);
        return self::COLOR_TOKEN_MAP[$normalized] ?? 'blue-500';
    }
}
