<?php

/**
 * =============================================================================
 * PROVIDER SCHEDULE CONTROLLER
 * =============================================================================
 * 
 * @file        app/Controllers/ProviderSchedule.php
 * @description API controller for managing provider working hours and
 *              availability schedules (weekly recurring patterns).
 * 
 * ROUTES HANDLED:
 * -----------------------------------------------------------------------------
 * GET  /provider-schedule/:providerId       : Get provider's schedule
 * POST /provider-schedule/:providerId/save  : Save/update schedule
 * GET  /provider-schedule/:providerId/breaks: Get break periods
 * POST /provider-schedule/:providerId/breaks: Save break periods
 * 
 * PURPOSE:
 * -----------------------------------------------------------------------------
 * Manages provider availability configuration:
 * - Weekly working hours (per day of week)
 * - Start and end times for each working day
 * - Break periods (lunch, etc.)
 * - Non-working days (days off)
 * 
 * SCHEDULE STRUCTURE:
 * -----------------------------------------------------------------------------
 * Each provider has 7 day records (Mon-Sun) containing:
 * - day_of_week: monday through sunday
 * - is_working: boolean (works on this day)
 * - start_time: HH:MM format
 * - end_time: HH:MM format
 * - breaks: array of {start, end} break periods
 * 
 * ACCESS CONTROL:
 * -----------------------------------------------------------------------------
 * - Admin: Can view/edit any provider's schedule
 * - Provider: Can only view/edit own schedule
 * 
 * RESPONSE FORMAT:
 * -----------------------------------------------------------------------------
 * Returns JSON with schedule data and localization context
 * (time format, timezone) for proper display.
 * 
 * @see         app/Models/ProviderScheduleModel.php for data layer
 * @see         app/Services/LocalizationSettingsService.php for formatting
 * @package     App\Controllers
 * @extends     BaseController
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

namespace App\Controllers;

use App\Models\ProviderScheduleModel;
use App\Models\UserModel;
use App\Services\LocalizationSettingsService;
use CodeIgniter\HTTP\ResponseInterface;

class ProviderSchedule extends BaseController
{
    protected ProviderScheduleModel $scheduleModel;
    protected UserModel $userModel;
    protected LocalizationSettingsService $localization;

    public function __construct()
    {
        $this->scheduleModel = new ProviderScheduleModel();
        $this->userModel     = new UserModel();
        $this->localization  = new LocalizationSettingsService();
    }

    public function index(int $providerId)
    {
        if (!$this->isAuthorized($providerId)) {
            return $this->failUnauthorized('Not permitted to view this schedule.');
        }

        $schedule = $this->scheduleModel->getByProvider($providerId);

        return $this->response->setJSON([
            'provider_id' => $providerId,
            'schedule'    => $schedule,
            'localization' => $this->localization->getContext(),
        ]);
    }

    public function save(int $providerId)
    {
        if (!$this->isAuthorized($providerId)) {
            return $this->failUnauthorized('Not permitted to update this schedule.');
        }

        $payload = $this->request->getJSON(true);
        if ($payload === null) {
            $payload = $this->request->getPost();
        }

        $entries = $payload['schedule'] ?? [];
        if (!is_array($entries)) {
            return $this->failValidationErrors(['schedule' => 'Invalid schedule payload.']);
        }

        [$validEntries, $errors] = $this->validateEntries($entries);
        if (!empty($errors)) {
            return $this->failValidationErrors($errors);
        }

        if (!$this->scheduleModel->saveSchedule($providerId, $validEntries)) {
            return $this->response->setStatusCode(ResponseInterface::HTTP_INTERNAL_SERVER_ERROR)
                ->setJSON([
                    'status'  => 'error',
                    'message' => 'Failed to persist provider schedule.',
                ]);
        }

        return $this->response->setJSON([
            'status'   => 'success',
            'message'  => 'Schedule updated.',
            'schedule' => $this->scheduleModel->getByProvider($providerId),
            'localization' => $this->localization->getContext(),
        ]);
    }

    public function delete(int $providerId)
    {
        if (!$this->isAuthorized($providerId)) {
            return $this->failUnauthorized('Not permitted to modify this schedule.');
        }

        $this->scheduleModel->deleteByProvider($providerId);

        return $this->response->setJSON([
            'status'  => 'success',
            'message' => 'Schedule deleted.',
        ]);
    }

    protected function isAuthorized(int $providerId): bool
    {
        $currentUserId = (int) session()->get('user_id');
        if (!$currentUserId) {
            return false;
        }

        $currentUser = $this->userModel->find($currentUserId);
        if (!$currentUser) {
            return false;
        }

        if ($currentUser['role'] === 'admin') {
            return true;
        }

        if ($currentUser['role'] === 'provider' && $currentUserId === $providerId) {
            return true;
        }

        return false;
    }

    /**
     * @param array $entries Raw schedule payload keyed by day.
     *
     * @return array{0: array, 1: array}
     */
    protected function validateEntries(array $entries): array
    {
        $clean  = [];
        $errors = [];

        foreach ($entries as $key => $row) {
            $day = ProviderScheduleModel::normalizeDayKey($key);
            if ($day === null) {
                $errors[$key] = 'Invalid weekday key.';
                continue;
            }

            $clean[$day] = $row;
        }

        foreach ($clean as $day => $row) {
            $isActive = !empty($row['is_active']);

            if (!$isActive) {
                // Inactive days are simply omitted from persistence.
                continue;
            }

            $rawStart = $row['start_time'] ?? null;
            $rawEnd = $row['end_time'] ?? null;
            $rawBreakStart = $row['break_start'] ?? null;
            $rawBreakEnd = $row['break_end'] ?? null;

            $start = $this->localization->normaliseTimeInput($rawStart);
            $end   = $this->localization->normaliseTimeInput($rawEnd);
            $breakStart = $this->localization->normaliseTimeInput($rawBreakStart);
            $breakEnd   = $this->localization->normaliseTimeInput($rawBreakEnd);

            if (!$start || !$end) {
                $errors[$day] = 'Start and end time are required. ' . $this->localization->describeExpectedFormat();
                continue;
            }

            if (strtotime($start) >= strtotime($end)) {
                $errors[$day] = 'Start time must be earlier than end time.';
                continue;
            }

            $hasBreakStartInput = is_string($rawBreakStart) && trim($rawBreakStart) !== '';
            $hasBreakEndInput = is_string($rawBreakEnd) && trim($rawBreakEnd) !== '';

            if ($hasBreakStartInput && !$breakStart) {
                $errors[$day] = 'Break start must use the expected time format. ' . $this->localization->describeExpectedFormat();
                continue;
            }

            if ($hasBreakEndInput && !$breakEnd) {
                $errors[$day] = 'Break end must use the expected time format. ' . $this->localization->describeExpectedFormat();
                continue;
            }

            if (($breakStart && !$breakEnd) || (!$breakStart && $breakEnd)) {
                $errors[$day] = 'Both break start and break end are required when using breaks. ' . $this->localization->describeExpectedFormat();
                continue;
            }

            if ($breakStart && $breakEnd) {
                if (strtotime($breakStart) >= strtotime($breakEnd)) {
                    $errors[$day] = 'Break start must be earlier than break end.';
                    continue;
                }

                if (strtotime($breakStart) < strtotime($start) || strtotime($breakEnd) > strtotime($end)) {
                    $errors[$day] = 'Break must fall within working hours.';
                    continue;
                }
            }

            $clean[$day] = [
                'is_active'   => 1,
                'start_time'  => $start,
                'end_time'    => $end,
                'break_start' => $breakStart,
                'break_end'   => $breakEnd,
            ];
        }

        return [$clean, $errors];
    }

    protected function failValidationErrors(array $errors)
    {
        return $this->response->setStatusCode(ResponseInterface::HTTP_UNPROCESSABLE_ENTITY)
            ->setJSON([
                'status' => 'error',
                'errors' => $errors,
            ]);
    }

    protected function failUnauthorized(string $message)
    {
        return $this->response->setStatusCode(ResponseInterface::HTTP_FORBIDDEN)
            ->setJSON([
                'status'  => 'error',
                'message' => $message,
            ]);
    }
}
