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
 * @author      Nilesh Nagin Cara
 * @copyright   2024-2026 Nilesh Nagin Cara
 * =============================================================================
 */

namespace App\Controllers\Api\V1;

use App\Controllers\Api\BaseApiController;
use App\Models\SettingModel;
use App\Services\Settings\SettingsApiService;

class Settings extends BaseApiController
{
    protected $model;
    protected SettingsApiService $settingsApiService;

    public function __construct(?SettingModel $model = null, ?SettingsApiService $settingsApiService = null)
    {
        $this->model = $model ?? new SettingModel();
        $this->settingsApiService = $settingsApiService ?? new SettingsApiService($this->model);
    }

    /**
     * GET /api/v1/settings?prefix=general.
     * Returns a flat key=>value map.
     */
    public function index()
    {
        return $this->ok($this->settingsApiService->getSettings($this->request->getGet('prefix')));
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
        return $this->ok($this->settingsApiService->getCalendarConfig());
    }

    /**
     * GET /api/v1/settings/localization
     * Returns localization settings (timezone, time format, etc.)
     */
    public function localization()
    {
        try {
            return $this->ok($this->settingsApiService->getLocalization());
        } catch (\Throwable $e) {
            log_message('error', 'Failed to load localization settings: ' . $e->getMessage());
            return $this->serverError('Failed to load localization settings', ['exception' => $e->getMessage()]);
        }
    }

    /**
     * GET /api/v1/settings/booking
     * Returns booking form configuration (required fields, custom fields, etc.)
     */
    public function booking()
    {
        try {
            return $this->ok($this->settingsApiService->getBooking());
        } catch (\Throwable $e) {
            log_message('error', 'Failed to load booking settings: ' . $e->getMessage());
            return $this->serverError('Failed to load booking settings', ['exception' => $e->getMessage()]);
        }
    }

    /**
     * GET /api/v1/settings/business-hours
     * Returns business hours configuration for all days of the week
     */
    public function businessHours()
    {
        $payload = $this->settingsApiService->getBusinessHoursPayload();

        return $this->ok($payload['data'], $payload['meta']);
    }

    /**
     * PUT|POST /api/v1/settings (bulk upsert)
     * Body: { "general.company_name":"Acme", ... }
     */
    public function update()
    {
        try {
            $payload = null;
            $jsonParseFailed = false;

            // Try JSON first, but do not fail hard on malformed JSON.
            try {
                $payload = $this->request->getJSON(true);
            } catch (\Throwable $jsonError) {
                $jsonParseFailed = true;
                log_message('warning', 'Settings update: JSON parse failed, attempting form fallback: {msg}', [
                    'msg' => $jsonError->getMessage(),
                ]);
            }

            // If JSON is absent/invalid, fall back to form data.
            if (!is_array($payload) || empty($payload)) {
                $payload = $this->request->getPost();
            }

            if (!is_array($payload) || empty($payload)) {
                if ($jsonParseFailed) {
                    return $this->validationError('Invalid JSON payload');
                }

                return $this->validationError('Invalid payload - must be JSON or form data');
            }

            $userId = session()->get('user_id');
            $count = $this->settingsApiService->updateSettings($payload, $userId);

            return $this->ok(['updated' => $count]);
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

            return $this->serverError('Failed to save settings', [
                'code' => 'settings_update_failed',
                'error_id' => $errorId,
            ]);
        }
    }

    /**
     * POST /api/v1/settings/logo
     * Handles company logo uploads independently of full settings form submission.
     */
    public function uploadLogo()
    {
        return $this->respondToUploadResult(
            $this->settingsApiService->uploadLogo($this->request->getFile('company_logo'), session()->get('user_id'))
        );
    }

    /**
     * POST /api/v1/settings/icon
     * Handles company icon (favicon) uploads independently of full settings form submission.
     */
    public function uploadIcon()
    {
        return $this->respondToUploadResult(
            $this->settingsApiService->uploadIcon($this->request->getFile('company_icon'), session()->get('user_id'))
        );
    }

    private function respondToUploadResult(array $result)
    {
        return match ($result['status'] ?? 'server_error') {
            'ok' => $this->ok($result['data'] ?? []),
            'validation_error' => $this->validationError($result['message'] ?? 'Validation failed'),
            'bad_request' => $this->badRequest($result['message'] ?? 'Bad request'),
            default => $this->serverError($result['message'] ?? 'Upload failed'),
        };
    }
}
