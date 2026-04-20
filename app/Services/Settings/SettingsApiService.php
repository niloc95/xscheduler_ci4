<?php

namespace App\Services\Settings;

use App\Models\BusinessHourModel;
use App\Models\SettingModel;
use App\Services\BookingSettingsService;
use App\Services\CalendarConfigService;
use App\Services\LocalizationSettingsService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\Files\UploadedFile;

class SettingsApiService
{
    private const DAY_NAMES = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

    private SettingModel $settingModel;
    private CalendarConfigService $calendarConfigService;
    private LocalizationSettingsService $localizationSettingsService;
    private BookingSettingsService $bookingSettingsService;
    private BusinessHourModel $businessHourModel;
    private GeneralSettingsService $generalSettingsService;
    private BaseConnection $db;

    /** @var array<string, bool> */
    private array $columnExistsCache = [];

    public function __construct(
        ?SettingModel $settingModel = null,
        ?CalendarConfigService $calendarConfigService = null,
        ?LocalizationSettingsService $localizationSettingsService = null,
        ?BookingSettingsService $bookingSettingsService = null,
        ?BusinessHourModel $businessHourModel = null,
        ?GeneralSettingsService $generalSettingsService = null,
    ) {
        $this->settingModel = $settingModel ?? new SettingModel();
        $this->calendarConfigService = $calendarConfigService ?? new CalendarConfigService();
        $this->localizationSettingsService = $localizationSettingsService ?? new LocalizationSettingsService();
        $this->bookingSettingsService = $bookingSettingsService ?? new BookingSettingsService();
        $this->businessHourModel = $businessHourModel ?? new BusinessHourModel();
        $this->generalSettingsService = $generalSettingsService ?? new GeneralSettingsService($this->settingModel);
        $this->db = \Config\Database::connect();
    }

    public function getSettings(?string $prefix = null): array
    {
        if ($prefix !== null && $prefix !== '') {
            return $this->settingModel->getByPrefix($prefix);
        }

        return $this->settingModel->getAllAsMap();
    }

    public function getCalendarConfig(): array
    {
        return $this->calendarConfigService->getJavaScriptConfig();
    }

    public function getLocalization(): array
    {
        $firstDayOfWeek = $this->calendarConfigService->getFirstDayOfWeek();

        return [
            'timezone' => $this->localizationSettingsService->getTimezone(),
            'timeFormat' => $this->localizationSettingsService->getTimeFormat(),
            'is12Hour' => $this->localizationSettingsService->isTwelveHour(),
            'firstDayOfWeek' => $firstDayOfWeek,
            'first_day_of_week' => $firstDayOfWeek,
            'context' => $this->localizationSettingsService->getContext(),
        ];
    }

    public function getBooking(): array
    {
        return [
            'fieldConfiguration' => $this->bookingSettingsService->getFieldConfiguration(),
            'customFields' => $this->bookingSettingsService->getCustomFieldConfiguration(),
            'visibleFields' => $this->bookingSettingsService->getVisibleFields(),
            'requiredFields' => $this->bookingSettingsService->getRequiredFields(),
        ];
    }

    public function getBusinessHoursPayload(): array
    {
        try {
            $rows = $this->getDefaultBusinessHourRows();

            if ($rows === []) {
                $rows = $this->getProviderTemplateBusinessHourRows();
            }

            $formatted = $this->formatBusinessHoursRows($rows);

            if (!$this->hasWorkingDay($formatted)) {
                foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday'] as $day) {
                    $formatted[$day]['isWorkingDay'] = true;
                }
            }

            return [
                'data' => $formatted,
                'meta' => [],
            ];
        } catch (\Throwable $e) {
            log_message('error', 'Failed to load business hours: ' . $e->getMessage());

            return [
                'data' => $this->buildFallbackBusinessHours(),
                'meta' => ['fallback' => true],
            ];
        }
    }

    public function updateSettings(array $payload, ?int $userId): int
    {
        unset($payload['csrf_test_name'], $payload['form_source']);

        $count = 0;

        foreach ($payload as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }

            if ($this->isBusinessTimeSetting($key)) {
                $value = $this->normalizeBusinessTimeSetting($key, $value);
            }

            $type = is_array($value) ? 'json' : (is_bool($value) ? 'bool' : 'string');
            if ($this->settingModel->upsert($key, $value, $type, $userId)) {
                $count++;
            }
        }

        return $count;
    }

    private function isBusinessTimeSetting(string $key): bool
    {
        return in_array($key, [
            'business.work_start',
            'business.work_end',
            'business.break_start',
            'business.break_end',
        ], true);
    }

    private function normalizeBusinessTimeSetting(string $key, mixed $value): string
    {
        $raw = is_scalar($value) ? (string) $value : '';
        $raw = trim($raw);

        if ($raw === '') {
            return '';
        }

        $normalized = $this->localizationSettingsService->normaliseTimeInput($raw);
        if ($normalized === null) {
            throw new \InvalidArgumentException(
                sprintf('Invalid value for %s. %s', $key, $this->localizationSettingsService->describeExpectedFormat())
            );
        }

        return substr($normalized, 0, 5);
    }

    public function uploadLogo(?UploadedFile $file, ?int $userId): array
    {
        return $this->generalSettingsService->uploadLogoForApi($file, $userId);
    }

    public function uploadIcon(?UploadedFile $file, ?int $userId): array
    {
        return $this->generalSettingsService->uploadIconForApi($file, $userId);
    }

    protected function getDefaultBusinessHourRows(): array
    {
        return $this->businessHourModel
            ->builder()
            ->select('weekday, start_time, end_time, breaks_json')
            ->groupStart()
            ->where('provider_id', 0)
            ->orWhere('provider_id', null)
            ->groupEnd()
            ->orderBy('weekday', 'ASC')
            ->get()
            ->getResultArray();
    }

    protected function getProviderTemplateBusinessHourRows(): array
    {
        $builder = $this->businessHourModel
            ->builder('xs_business_hours bh')
            ->select('bh.weekday, bh.start_time, bh.end_time, bh.breaks_json')
            ->join('xs_users u', 'u.id = bh.provider_id')
            ->where('u.role', 'provider');

        if ($this->hasUsersColumn('is_active')) {
            $builder->where('u.is_active', 1);
        } elseif ($this->hasUsersColumn('status')) {
            $builder->where('u.status', 'active');
        }

        return $builder
            ->orderBy('bh.weekday', 'ASC')
            ->limit(7)
            ->get()
            ->getResultArray();
    }

    private function hasUsersColumn(string $column): bool
    {
        $key = 'users.' . $column;

        if (array_key_exists($key, $this->columnExistsCache)) {
            return $this->columnExistsCache[$key];
        }

        $exists = false;

        try {
            if (!method_exists($this->db, 'getFieldData')) {
                $this->columnExistsCache[$key] = true;
                return true;
            }

            $prefix = method_exists($this->db, 'getPrefix') ? (string) $this->db->getPrefix() : '';
            $candidates = [];
            if (method_exists($this->db, 'prefixTable')) {
                $candidates[] = (string) $this->db->prefixTable('users');
            }
            $candidates[] = 'users';

            if ($prefix !== '') {
                $candidates[] = $prefix . 'users';
            }

            foreach (array_values(array_unique($candidates)) as $candidate) {
                try {
                    $fields = $this->db->getFieldData($candidate);
                    foreach ($fields as $field) {
                        if ((string) ($field->name ?? '') === $column) {
                            $exists = true;
                            break 2;
                        }
                    }
                } catch (\Throwable $inner) {
                    // Try the next candidate table name.
                }
            }
        } catch (\Throwable $e) {
            $exists = false;
        }

        $this->columnExistsCache[$key] = $exists;

        return $exists;
    }

    private function formatBusinessHoursRows(array $rows): array
    {
        $formatted = [];

        foreach (self::DAY_NAMES as $day) {
            $formatted[$day] = [
                'isWorkingDay' => false,
                'startTime' => '09:00:00',
                'endTime' => '17:00:00',
                'breaks' => [],
            ];
        }

        foreach ($rows as $row) {
            $dayName = self::DAY_NAMES[(int) ($row['weekday'] ?? -1)] ?? null;
            if ($dayName === null) {
                continue;
            }

            $formatted[$dayName] = [
                'isWorkingDay' => !empty($row['start_time']) && !empty($row['end_time']),
                'startTime' => $row['start_time'] ?? '09:00:00',
                'endTime' => $row['end_time'] ?? '17:00:00',
                'breaks' => $this->decodeBreaks($row['breaks_json'] ?? null),
            ];
        }

        return $formatted;
    }

    private function hasWorkingDay(array $formatted): bool
    {
        foreach ($formatted as $config) {
            if (!empty($config['isWorkingDay'])) {
                return true;
            }
        }

        return false;
    }

    private function buildFallbackBusinessHours(): array
    {
        $fallback = [];

        foreach (self::DAY_NAMES as $day) {
            $fallback[$day] = [
                'isWorkingDay' => in_array($day, ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'], true),
                'startTime' => '09:00:00',
                'endTime' => '17:00:00',
                'breaks' => [],
            ];
        }

        return $fallback;
    }

    private function decodeBreaks(?string $breaksJson): array
    {
        if ($breaksJson === null || $breaksJson === '') {
            return [];
        }

        $decoded = json_decode($breaksJson, true);

        return is_array($decoded) ? $decoded : [];
    }
}