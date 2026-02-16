<?php

/**
 * =============================================================================
 * V1 SETTINGS API CONTROLLER
 * =============================================================================
 * 
 * @file        app/Controllers/Api/V1/Settings.php
 * @description API for retrieving and updating system settings including
 *              localization, booking, calendar, and branding configuration.
 * 
 * API ENDPOINTS:
 * -----------------------------------------------------------------------------
 * GET  /api/v1/settings                     : Get settings (with prefix filter)
 * PUT  /api/v1/settings                     : Update settings (admin only)
 * GET  /api/v1/settings/localization        : Get localization settings
 * GET  /api/v1/settings/calendar            : Get calendar configuration
 * GET  /api/v1/settings/booking             : Get booking settings
 * GET  /api/v1/settings/branding            : Get branding (logo, colors)
 * POST /api/v1/settings/logo                : Upload logo file
 * POST /api/v1/settings/icon                : Upload icon (favicon) file
 * 
 * QUERY PARAMETERS (GET /api/v1/settings):
 * -----------------------------------------------------------------------------
 * - prefix        : Filter settings by prefix (e.g., 'general.', 'booking.')
 * 
 * SETTINGS CATEGORIES:
 * -----------------------------------------------------------------------------
 * - general.*       : Business name, contact info
 * - localization.*  : Timezone, date/time format, currency
 * - booking.*       : Slot duration, buffer time, advance booking
 * - calendar.*      : Week start, working hours, view defaults
 * - notifications.* : Email, SMS, WhatsApp settings
 * - branding.*      : Logo, colors, theme
 * 
 * RESPONSE FORMAT:
 * -----------------------------------------------------------------------------
 * {
 *   "data": {
 *     "general.business_name": "My Salon",
 *     "localization.timezone": "Africa/Johannesburg",
 *     "localization.currency": "ZAR"
 *   }
 * }
 * 
 * @see         app/Models/SettingModel.php for data layer
 * @see         app/Services/LocalizationSettingsService.php
 * @package     App\Controllers\Api\V1
 * @extends     BaseController
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

namespace App\Controllers\Api\V1;

use App\Controllers\Api\V1\BaseApiController;
use App\Models\SettingFileModel;
use App\Models\SettingModel;
use App\Services\CalendarConfigService;
use App\Services\LocalizationSettingsService;
use App\Services\BookingSettingsService;
use CodeIgniter\HTTP\ResponseInterface;

class Settings extends BaseApiController
{
    protected $model;

    public function __construct()
    {
        $this->model = new SettingModel();
    }

    /**
     * GET /api/v1/settings?prefix=general.
     * Returns a flat key=>value map.
     */
    public function index()
    {
        $prefix = $this->request->getGet('prefix');
        if ($prefix) {
            $data = $this->model->getByPrefix($prefix);
        } else {
            // Return all settings as a map
            $rows = $this->model->select(['setting_key', 'setting_value', 'setting_type'])->findAll();
            $data = [];
            foreach ($rows as $r) {
                $data[$r['setting_key']] = $this->model->getByKeys([$r['setting_key']])[$r['setting_key']] ?? null;
            }
        }
        return $this->response->setJSON(['ok' => true, 'data' => $data]);
    }

    /**
     * GET /api/v1/settings/calendar-config
     * Returns calendar/scheduler-specific configuration including time format,
     * business hours, timezone, etc.
     * 
     * Note: Previously optimized for FullCalendar, now generic for custom scheduler
     */
    public function calendarConfig()
    {
        $calendarService = new CalendarConfigService();
        $config = $calendarService->getJavaScriptConfig();
        
        return $this->response->setJSON([
            'ok' => true,
            'data' => $config
        ]);
    }

    /**
     * GET /api/v1/settings/localization
     * Returns localization settings (timezone, time format, etc.)
     */
    public function localization()
    {
        try {
            $service = new LocalizationSettingsService();
            $calendarService = new CalendarConfigService();
            
            $data = [
                'timezone' => $service->getTimezone(),
                'timeFormat' => $service->getTimeFormat(),
                'is12Hour' => $service->isTwelveHour(),
                'firstDayOfWeek' => $calendarService->getFirstDayOfWeek(),
                'first_day_of_week' => $calendarService->getFirstDayOfWeek(), // snake_case for compatibility
                'context' => $service->getContext(),
            ];
            
            return $this->response->setJSON([
                'ok' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            log_message('error', 'Failed to load localization settings: ' . $e->getMessage());
            return $this->failServerError('Failed to load localization settings');
        }
    }

    /**
     * GET /api/v1/settings/booking
     * Returns booking form configuration (required fields, custom fields, etc.)
     */
    public function booking()
    {
        try {
            $service = new BookingSettingsService();
            
            $data = [
                'fieldConfiguration' => $service->getFieldConfiguration(),
                'customFields' => $service->getCustomFieldConfiguration(),
                'visibleFields' => $service->getVisibleFields(),
                'requiredFields' => $service->getRequiredFields(),
            ];
            
            return $this->response->setJSON([
                'ok' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            log_message('error', 'Failed to load booking settings: ' . $e->getMessage());
            return $this->failServerError('Failed to load booking settings');
        }
    }

    /**
     * GET /api/v1/settings/business-hours
     * Returns business hours configuration for all days of the week
     */
    public function businessHours()
    {
        try {
            // Query business_hours table - returns provider-specific schedules
            // For general business hours, we'll use default settings or first provider
            $db = \Config\Database::connect();
            
            // Check if there's default business hours (provider_id = 0 or null)
            $hours = $db->table('xs_business_hours')
                ->select('weekday, start_time, end_time, breaks_json')
                ->where('provider_id', 0)
                ->orWhere('provider_id IS NULL')
                ->orderBy('weekday', 'ASC')
                ->get()
                ->getResultArray();
            
            // If no default hours, get from first active provider as template
            if (empty($hours)) {
                $hours = $db->table('xs_business_hours bh')
                    ->select('bh.weekday, bh.start_time, bh.end_time, bh.breaks_json')
                    ->join('xs_users u', 'u.id = bh.provider_id')
                    ->where('u.role', 'provider')
                    ->where('u.is_active', 1)
                    ->orderBy('bh.weekday', 'ASC')
                    ->limit(7)
                    ->get()
                    ->getResultArray();
            }
            
            // Format for frontend - convert numeric weekday to day names
            $dayNames = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
            
            // First, initialize all days as non-working (closed)
            $formatted = [];
            foreach ($dayNames as $day) {
                $formatted[$day] = [
                    'isWorkingDay' => false,
                    'startTime' => '09:00:00',
                    'endTime' => '17:00:00',
                    'breaks' => [],
                ];
            }
            
            // Then override with actual working days from database
            foreach ($hours as $row) {
                $dayName = $dayNames[(int)$row['weekday']] ?? null;
                if ($dayName) {
                    $formatted[$dayName] = [
                        'isWorkingDay' => !empty($row['start_time']) && !empty($row['end_time']),
                        'startTime' => $row['start_time'] ?? '09:00:00',
                        'endTime' => $row['end_time'] ?? '17:00:00',
                        'breaks' => !empty($row['breaks_json']) ? json_decode($row['breaks_json'], true) : [],
                    ];
                }
            }
            
            // If NO hours were found at all, use default Mon-Fri schedule
            $hasAnyWorkingDays = false;
            foreach ($formatted as $day => $config) {
                if ($config['isWorkingDay']) {
                    $hasAnyWorkingDays = true;
                    break;
                }
            }
            
            if (!$hasAnyWorkingDays) {
                // Default: Monday-Friday working, Sat-Sun closed
                foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday'] as $day) {
                    $formatted[$day]['isWorkingDay'] = true;
                }
            }
            
            return $this->response->setJSON([
                'ok' => true,
                'data' => $formatted
            ]);
        } catch (\Exception $e) {
            log_message('error', 'Failed to load business hours: ' . $e->getMessage());
            
            // Return fallback default hours on error
            $dayNames = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
            $fallback = [];
            foreach ($dayNames as $day) {
                $fallback[$day] = [
                    'isWorkingDay' => in_array($day, ['monday', 'tuesday', 'wednesday', 'thursday', 'friday']),
                    'startTime' => '09:00:00',
                    'endTime' => '17:00:00',
                    'breaks' => [],
                ];
            }
            
            return $this->response->setJSON([
                'ok' => true,
                'data' => $fallback
            ]);
        }
    }

    /**
     * PUT|POST /api/v1/settings (bulk upsert)
     * Body: { "general.company_name":"Acme", ... }
     */
    public function update()
    {
        try {
            // Try to get JSON payload first
            $payload = $this->request->getJSON(true);
            
            // If JSON parsing fails or returns null, try form data
            if ($payload === null || !is_array($payload)) {
                $payload = $this->request->getPost();
                if (!is_array($payload) || empty($payload)) {
                    return $this->failValidationErrors('Invalid payload - must be JSON or form data');
                }
            }

            $userId = session()->get('user_id');
            $count = 0;

            foreach ($payload as $key => $value) {
                // Infer type: JSON if array/object; boolean if true/false; else string
                $type = is_array($value) ? 'json' : (is_bool($value) ? 'bool' : 'string');
                if ($this->model->upsert($key, $value, $type, $userId)) {
                    $count++;
                }
            }

            return $this->response->setJSON(['ok' => true, 'updated' => $count]);
        } catch (\Throwable $e) {
            $errorId = null;
            try {
                $errorId = bin2hex(random_bytes(8));
            } catch (\Throwable $ignored) {
                $errorId = uniqid('settings_', true);
            }

            log_message('error', 'Settings update failed: {msg}', [
                'msg' => $e->getMessage(),
            ]);

            // Fallback for restrictive hosting: always emit something to PHP's error log.
            // This is especially important when writable/logs is not writeable.
            try {
                error_log('[XSCHEDULR][' . $errorId . '] Settings update failed: ' . $e->getMessage());
            } catch (\Throwable $ignored) {
                // no-op
            }

            // Add extra context for production-only failures.
            try {
                log_message('error', 'Settings update context: method={method} path={path} user_id={user_id}', [
                    'method' => (string) $this->request->getMethod(),
                    'path' => (string) $this->request->getPath(),
                    'user_id' => (string) (session()->get('user_id') ?? ''),
                ]);

                try {
                    error_log('[XSCHEDULR][' . $errorId . '] context method=' . (string) $this->request->getMethod() . ' path=' . (string) $this->request->getPath() . ' user_id=' . (string) (session()->get('user_id') ?? ''));
                } catch (\Throwable $ignored) {
                    // no-op
                }
            } catch (\Throwable $ignored) {
                // no-op
            }

            return $this->response
                ->setStatusCode(500)
                ->setJSON([
                    'ok' => false,
                    'error' => [
                        'code' => 'settings_update_failed',
                        'message' => 'Failed to save settings',
                        'error_id' => $errorId,
                    ],
                ]);
        }
    }

    /**
     * POST /api/v1/settings/logo
     * Handles company logo uploads independently of full settings form submission.
     */
    public function uploadLogo()
    {
        $file = $this->request->getFile('company_logo');
        if (!$file) {
            return $this->failValidationErrors('No logo file received.');
        }

        if ($file->getError() === UPLOAD_ERR_NO_FILE) {
            return $this->failValidationErrors('Please choose a logo file to upload.');
        }

        if (!$file->isValid()) {
            return $this->fail($file->getErrorString(), ResponseInterface::HTTP_BAD_REQUEST);
        }

        if ($file->hasMoved()) {
            return $this->fail('Upload failed: file has already been moved.', ResponseInterface::HTTP_BAD_REQUEST);
        }

        $sizeBytes = (int) $file->getSize();
        if ($sizeBytes > (2 * 1024 * 1024)) {
            return $this->failValidationErrors('Logo upload too large. Maximum size is 2MB.');
        }

        $clientMime = strtolower((string) $file->getClientMimeType());
        $realMime   = strtolower((string) $file->getMimeType());
        $ext        = strtolower($file->getExtension() ?: pathinfo($file->getName(), PATHINFO_EXTENSION));

        $allowedMimes = [
            'image/png','image/x-png','image/jpeg','image/pjpeg','image/webp','image/svg+xml','image/svg','image/gif'
        ];
        $allowedExts = ['png','jpg','jpeg','webp','svg','gif'];

        $mimeOk = in_array($clientMime, $allowedMimes, true) || in_array($realMime, $allowedMimes, true);
        $extOk  = in_array($ext, $allowedExts, true);
        if (!$mimeOk && !$extOk) {
            return $this->failValidationErrors('Unsupported logo format. Use PNG, JPG, SVG, WebP, or GIF.');
        }

        $targetDir = rtrim(FCPATH, '/').'/assets/settings';
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0755, true);
        }
        if (!is_dir($targetDir) || !is_writable($targetDir)) {
            return $this->failServerError('Logo upload directory is not writable.');
        }

        $existing = $this->model->getByKeys(['general.company_logo']);
        $previous = $existing['general.company_logo'] ?? null;
        if ($previous) {
            $prevPath = $this->resolveLogoPath((string) $previous);
            if ($prevPath && is_file($prevPath)) {
                @unlink($prevPath);
            }
        }

        try {
            $safeName = 'logo_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        } catch (\Throwable $e) {
            $safeName = 'logo_' . date('Ymd_His') . '_' . uniqid('', true) . '.' . $ext;
        }

        if (!$file->move($targetDir, $safeName)) {
            return $this->failServerError('Unable to store uploaded logo.');
        }

        $absolute = rtrim($targetDir, '/').'/'.$safeName;
        $mimeForResize = $realMime ?: $clientMime;

        if (!in_array($mimeForResize, ['image/svg+xml','image/svg'], true)) {
            [$w, $h] = @getimagesize($absolute) ?: [null, null];
            if ($w && $w > 1200) {
                $ratio = $h ? ($h / $w) : 1;
                $newW = 1200;
                $newH = max(1, (int) round($newW * $ratio));
                $this->resizeImageInPlace($absolute, $mimeForResize, $newW, $newH);
            }
        }

        $relative = 'assets/settings/' . $safeName;
        $userId = session()->get('user_id');

        $this->model->upsert('general.company_logo', $relative, 'string', $userId);

        try {
            $bytes = @file_get_contents($absolute);
            if ($bytes !== false) {
                $fileModel = new SettingFileModel();
                $fileModel->upsert('general.company_logo', $safeName, $mimeForResize ?: $clientMime, $bytes, $userId);
            }
        } catch (\Throwable $e) {
            log_message('warning', 'Logo upload: failed storing bytes to DB: {msg}', ['msg' => $e->getMessage()]);
        }

        return $this->response->setJSON([
            'ok' => true,
            'path' => $relative,
            'url' => base_url($relative)
        ]);
    }

    /**
     * POST /api/v1/settings/icon
     * Handles company icon (favicon) uploads independently of full settings form submission.
     */
    public function uploadIcon()
    {
        $file = $this->request->getFile('company_icon');
        if (!$file) {
            return $this->failValidationErrors('No icon file received.');
        }

        if ($file->getError() === UPLOAD_ERR_NO_FILE) {
            return $this->failValidationErrors('Please choose an icon file to upload.');
        }

        if (!$file->isValid()) {
            return $this->fail($file->getErrorString(), ResponseInterface::HTTP_BAD_REQUEST);
        }

        if ($file->hasMoved()) {
            return $this->fail('Upload failed: file has already been moved.', ResponseInterface::HTTP_BAD_REQUEST);
        }

        $sizeBytes = (int) $file->getSize();
        if ($sizeBytes > (2 * 1024 * 1024)) {
            return $this->failValidationErrors('Icon upload too large. Maximum size is 2MB.');
        }

        $clientMime = strtolower((string) $file->getClientMimeType());
        $realMime   = strtolower((string) $file->getMimeType());
        $ext        = strtolower($file->getExtension() ?: pathinfo($file->getName(), PATHINFO_EXTENSION));

        $allowedMimes = [
            'image/png','image/x-png','image/x-icon','image/vnd.microsoft.icon','image/svg+xml','image/svg'
        ];
        $allowedExts = ['png','ico','svg'];

        $mimeOk = in_array($clientMime, $allowedMimes, true) || in_array($realMime, $allowedMimes, true);
        $extOk  = in_array($ext, $allowedExts, true);
        if (!$mimeOk && !$extOk) {
            return $this->failValidationErrors('Unsupported icon format. Use ICO, PNG, or SVG.');
        }

        $targetDir = rtrim(FCPATH, '/').'/assets/settings';
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0755, true);
        }
        if (!is_dir($targetDir) || !is_writable($targetDir)) {
            return $this->failServerError('Icon upload directory is not writable.');
        }

        $existing = $this->model->getByKeys(['general.company_icon']);
        $previous = $existing['general.company_icon'] ?? null;
        if ($previous) {
            $prevPath = $this->resolveLogoPath((string) $previous);
            if ($prevPath && is_file($prevPath)) {
                @unlink($prevPath);
            }
        }

        try {
            $safeName = 'icon_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        } catch (\Throwable $e) {
            $safeName = 'icon_' . date('Ymd_His') . '_' . uniqid('', true) . '.' . $ext;
        }

        if (!$file->move($targetDir, $safeName)) {
            return $this->failServerError('Unable to store uploaded icon.');
        }

        $absolute = rtrim($targetDir, '/').'/'.$safeName;
        $mimeForResize = $realMime ?: $clientMime;

        // Skip resize for icons - they're typically small already
        // SVG and ICO don't need resizing, PNG icons are usually tiny

        $relative = 'assets/settings/' . $safeName;
        $userId = session()->get('user_id');

        $this->model->upsert('general.company_icon', $relative, 'string', $userId);

        try {
            $bytes = @file_get_contents($absolute);
            if ($bytes !== false) {
                $fileModel = new SettingFileModel();
                $fileModel->upsert('general.company_icon', $safeName, $mimeForResize ?: $clientMime, $bytes, $userId);
            }
        } catch (\Throwable $e) {
            log_message('warning', 'Icon upload: failed storing bytes to DB: {msg}', ['msg' => $e->getMessage()]);
        }

        return $this->response->setJSON([
            'ok' => true,
            'path' => $relative,
            'url' => base_url($relative)
        ]);
    }

    private function resolveLogoPath(string $stored): ?string
    {
        $normalized = ltrim($stored, '/');
        if ($normalized === '' || str_starts_with($normalized, 'db://')) {
            return null;
        }
        if (str_starts_with($normalized, 'assets/settings/')) {
            return rtrim(FCPATH, '/').'/'.$normalized;
        }
        if (str_starts_with($normalized, 'uploads/settings/')) {
            return rtrim(WRITEPATH, '/').'/'.$normalized;
        }
        return rtrim(WRITEPATH, '/').'/'.$normalized;
    }

    private function resizeImageInPlace(string $path, string $mime, int $newW, int $newH): void
    {
        switch ($mime) {
            case 'image/jpeg': $src = @imagecreatefromjpeg($path); break;
            case 'image/png': $src = @imagecreatefrompng($path); break;
            case 'image/gif': $src = @imagecreatefromgif($path); break;
            case 'image/webp':
                if (function_exists('imagecreatefromwebp')) {
                    $src = @imagecreatefromwebp($path);
                } else {
                    return;
                }
                break;
            default:
                return;
        }
        if (!$src) {
            return;
        }

        $dst = imagecreatetruecolor($newW, $newH);
        if (in_array($mime, ['image/png','image/gif'], true)) {
            imagecolortransparent($dst, imagecolorallocatealpha($dst, 0, 0, 0, 127));
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
        }

        $sw = imagesx($src);
        $sh = imagesy($src);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $sw, $sh);

        switch ($mime) {
            case 'image/jpeg': @imagejpeg($dst, $path, 85); break;
            case 'image/png': @imagepng($dst, $path, 6); break;
            case 'image/gif': @imagegif($dst, $path); break;
            case 'image/webp':
                if (function_exists('imagewebp')) {
                    @imagewebp($dst, $path, 85);
                }
                break;
        }

        imagedestroy($src);
        imagedestroy($dst);
    }
}
