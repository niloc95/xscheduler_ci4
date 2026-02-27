<?php

/**
 * =============================================================================
 * CALENDAR API CONTROLLER
 * =============================================================================
 *
 * @file        app/Controllers/Api/CalendarController.php
 * @description RESTful API for pre-computed calendar view models.
 *              Returns server-side render models for day, week, and month views,
 *              eliminating client-side slot generation (slot-engine.js).
 *
 * API ENDPOINTS:
 * ─────────────────────────────────────────────────────────────────
 * GET /api/calendar/day?date=2026-02-26
 * GET /api/calendar/week?date=2026-02-26
 * GET /api/calendar/month?year=2026&month=2
 *
 * COMMON QUERY PARAMETERS:
 * ─────────────────────────────────────────────────────────────────
 * - provider_id    Filter by provider
 * - service_id     Filter by service
 * - location_id    Filter by location
 * - status         Filter by appointment status
 *
 * Role scoping is automatic:
 * - providers always see only their own appointments
 * - admins/staff see all appointments (or filtered subset)
 *
 * RESPONSE (all views):
 * ─────────────────────────────────────────────────────────────────
 * {
 *   "data": { ...view model... },
 *   "meta": { "view": "week", "date": "2026-02-26", "generated_at": "..." }
 * }
 *
 * @see         app/Services/Calendar/DayViewService.php
 * @see         app/Services/Calendar/WeekViewService.php
 * @see         app/Services/Calendar/MonthViewService.php
 * @package     App\Controllers\Api
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

namespace App\Controllers\Api;

use App\Services\Calendar\DayViewService;
use App\Services\Calendar\WeekViewService;
use App\Services\Calendar\MonthViewService;
use App\Models\SettingModel;

class CalendarController extends BaseApiController
{
    // ─────────────────────────────────────────────────────────────────
    // GET /api/calendar/day?date=YYYY-MM-DD
    // ─────────────────────────────────────────────────────────────────

    /**
     * Return a pre-computed day view render model.
     *
     * Query params: date, provider_id, service_id, location_id, status
     */
    public function day()
    {
        try {
            if ($resp = $this->ensureCalendarEnabled()) {
                return $resp;
            }
            $date = $this->resolveDate($this->request->getGet('date'));

            $service = new DayViewService();
            $model   = $service->build($date, $this->buildFilters());

            return $this->ok($model, [
                'view'         => 'day',
                'date'         => $date,
                'generated_at' => date('Y-m-d\TH:i:s'),
            ]);
        } catch (\Throwable $e) {
            log_message('error', '[CalendarController::day] ' . $e->getMessage());
            return $this->serverError('Failed to build day view model', ['details' => $e->getMessage()]);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // GET /api/calendar/week?date=YYYY-MM-DD
    // ─────────────────────────────────────────────────────────────────

    /**
     * Return a pre-computed week view render model.
     *
     * Query params: date, provider_id, service_id, location_id, status
     */
    public function week()
    {
        try {
            if ($resp = $this->ensureCalendarEnabled()) {
                return $resp;
            }
            $date = $this->resolveDate($this->request->getGet('date'));

            $service = new WeekViewService();
            $model   = $service->build($date, $this->buildFilters());

            return $this->ok($model, [
                'view'         => 'week',
                'date'         => $date,
                'generated_at' => date('Y-m-d\TH:i:s'),
            ]);
        } catch (\Throwable $e) {
            log_message('error', '[CalendarController::week] ' . $e->getMessage());
            return $this->serverError('Failed to build week view model', ['details' => $e->getMessage()]);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // GET /api/calendar/month?year=2026&month=2
    // ─────────────────────────────────────────────────────────────────

    /**
     * Return a pre-computed month view render model.
     *
     * Query params: year, month (or date), provider_id, service_id, location_id, status
     * Accepts either ?year=2026&month=2  OR  ?date=2026-02-01 for convenience.
     */
    public function month()
    {
        try {
            if ($resp = $this->ensureCalendarEnabled()) {
                return $resp;
            }
            // Accept year+month or date string
            $dateParam = $this->request->getGet('date');
            if ($dateParam) {
                $d     = new \DateTimeImmutable($dateParam);
                $year  = (int) $d->format('Y');
                $month = (int) $d->format('n');
            } else {
                $year  = (int) ($this->request->getGet('year')  ?? date('Y'));
                $month = (int) ($this->request->getGet('month') ?? date('n'));
            }

            if ($month < 1 || $month > 12) {
                return $this->badRequest('month must be 1–12');
            }

            $service = new MonthViewService();
            $model   = $service->build($year, $month, $this->buildFilters());

            return $this->ok($model, [
                'view'         => 'month',
                'date'         => sprintf('%04d-%02d-01', $year, $month),
                'generated_at' => date('Y-m-d\TH:i:s'),
            ]);
        } catch (\Throwable $e) {
            log_message('error', '[CalendarController::month] ' . $e->getMessage());
            return $this->serverError('Failed to build month view model', ['details' => $e->getMessage()]);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────

    /**
     * Parse and validate a date string, defaulting to today.
     */
    private function resolveDate(?string $dateParam): string
    {
        if (!$dateParam) {
            return date('Y-m-d');
        }
        try {
            return (new \DateTimeImmutable($dateParam))->format('Y-m-d');
        } catch (\Throwable $e) {
            return date('Y-m-d');
        }
    }

    /**
     * Guard calendar endpoints behind rebuild flag.
     */
    private function ensureCalendarEnabled()
    {
        $settings = (new SettingModel())->getByKeys(['calendar.rebuild_enabled']);
        $enabled = $settings['calendar.rebuild_enabled'] ?? true;
        if (!$enabled) {
            return $this->error(503, 'Calendar rebuild in progress', 'CALENDAR_REBUILD_DISABLED');
        }
        return null;
    }

    /**
     * Build the filters array from request params + session scoping.
     * Passed directly to view services → AppointmentQueryService.
     */
    private function buildFilters(): array
    {
        $providerId = $this->request->getGet('provider_id');
        $serviceId  = $this->request->getGet('service_id');
        $locationId = $this->request->getGet('location_id');
        $status     = $this->request->getGet('status');

        return [
            'provider_id'      => $providerId ? (int) $providerId : null,
            'service_id'       => $serviceId  ? (int) $serviceId  : null,
            'location_id'      => $locationId ? (int) $locationId : null,
            'status'           => $status ?: null,
            // Role scoping (RISK-06 enforced in AppointmentQueryService)
            'user_role'        => current_user_role(),
            'scope_to_user_id' => session()->get('user_id'),
        ];
    }
}
