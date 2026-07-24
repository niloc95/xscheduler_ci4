<?php

/**
 * =============================================================================
 * V1 BUSINESS HOURS API CONTROLLER
 * =============================================================================
 *
 * @file        app/Controllers/Api/V1/BusinessHours.php
 * @description External read/write for the business working-hours window.
 *
 * API ENDPOINTS:
 * -----------------------------------------------------------------------------
 * GET  /api/v1/business-hours   : Per-day business hours (canonical payload)
 * PUT  /api/v1/business-hours   : Set the global work window   (admin only)
 * POST /api/v1/business-hours   : Alias of PUT (WAF-friendly)  (admin only)
 *
 * SCOPE NOTE:
 * -----------------------------------------------------------------------------
 * The global work window lives in xs_settings (`business.work_start` /
 * `business.work_end`). Per-provider weekly schedules live in xs_business_hours
 * and are written through the provider create/update `schedule` payload — this
 * controller deliberately does NOT touch per-provider rows (Rule #2: every
 * xs_business_hours query is provider-scoped elsewhere).
 *
 * Both read and write delegate to SettingsApiService, the same audited surface
 * the settings panel uses, so there is no second write path to keep in sync.
 *
 * @see         app/Services/Settings/SettingsApiService.php
 * @package     App\Controllers\Api\V1
 * @extends     BaseApiController
 * =============================================================================
 */

namespace App\Controllers\Api\V1;

use App\Controllers\Api\BaseApiController;
use App\Services\Settings\SettingsApiService;

class BusinessHours extends BaseApiController
{
    protected SettingsApiService $settings;

    public function __construct()
    {
        $this->settings = new SettingsApiService();
    }

    // GET /api/v1/business-hours
    public function index()
    {
        $payload = $this->settings->getBusinessHoursPayload();

        return $this->ok($payload['data'], $payload['meta'] ?? []);
    }

    // PUT|POST /api/v1/business-hours
    public function update()
    {
        $body = $this->request->getJSON(true) ?? $this->request->getPost();
        if (!$body) {
            return $this->badRequest('Missing body');
        }

        $start = trim((string) ($body['workStart'] ?? $body['work_start'] ?? ''));
        $end   = trim((string) ($body['workEnd'] ?? $body['work_end'] ?? ''));

        $errors = [];
        if (!$this->isTime($start)) {
            $errors['workStart'] = 'workStart must be HH:MM (24-hour).';
        }
        if (!$this->isTime($end)) {
            $errors['workEnd'] = 'workEnd must be HH:MM (24-hour).';
        }
        if (!$errors && $start >= $end) {
            $errors['workEnd'] = 'workEnd must be later than workStart.';
        }
        if ($errors) {
            return $this->validationError($errors);
        }

        try {
            $this->settings->updateSettings([
                'business.work_start' => $start,
                'business.work_end'   => $end,
            ], current_user_id());
        } catch (\InvalidArgumentException $e) {
            return $this->validationError($e->getMessage());
        } catch (\Throwable $e) {
            return $this->handleCaughtException($e, 'Unable to update business hours');
        }

        $payload = $this->settings->getBusinessHoursPayload();

        return $this->ok($payload['data'], $payload['meta'] ?? []);
    }

    /**
     * Accept HH:MM or HH:MM:SS in 24-hour form.
     */
    private function isTime(string $value): bool
    {
        return $value !== '' && (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $value);
    }
}
