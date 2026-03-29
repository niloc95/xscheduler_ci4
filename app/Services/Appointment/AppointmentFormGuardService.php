<?php

namespace App\Services\Appointment;

use App\Models\AppointmentModel;
use App\Services\LocalizationSettingsService;
use CodeIgniter\Exceptions\PageNotFoundException;

class AppointmentFormGuardService
{
    private LocalizationSettingsService $localizationSettingsService;
    private AppointmentModel $appointmentModel;

    public function __construct(
        ?LocalizationSettingsService $localizationSettingsService = null,
        ?AppointmentModel $appointmentModel = null,
    ) {
        $this->localizationSettingsService = $localizationSettingsService ?? new LocalizationSettingsService();
        $this->appointmentModel = $appointmentModel ?? new AppointmentModel();
    }

    public function requireLogin(?string $message = null): ?array
    {
        if (session()->get('isLoggedIn')) {
            return null;
        }

        return [
            'type' => 'redirect',
            'to' => base_url('auth/login'),
            'flash' => $message !== null ? ['error' => $message] : [],
        ];
    }

    public function requireRole(array $roles, bool $notFoundOnFailure = false): ?array
    {
        if (has_role($roles)) {
            return null;
        }

        if ($notFoundOnFailure) {
            return [
                'type' => 'not_found',
                'message' => 'Access denied',
            ];
        }

        return [
            'type' => 'redirect_back',
            'withInput' => false,
            'flash' => ['error' => 'Access denied'],
        ];
    }

    public function requireAppointmentHash(?string $appointmentHash): ?array
    {
        if ($appointmentHash !== null && $appointmentHash !== '') {
            return null;
        }

        return [
            'type' => 'not_found',
            'message' => 'Appointment not found',
        ];
    }

    public function requireExistingAppointment(string $appointmentHash): ?array
    {
        if ($this->appointmentModel->findByHash($appointmentHash) !== null) {
            return null;
        }

        return [
            'type' => 'not_found',
            'message' => 'Appointment not found',
        ];
    }

    public function validateNotInPast(?string $appointmentDate, ?string $appointmentTime, string $errorMessage, bool $isAjax): ?array
    {
        if (!$appointmentDate || !$appointmentTime) {
            return null;
        }

        $appTimezone = $this->localizationSettingsService->getTimezone();
        $appointmentDateTime = new \DateTime($appointmentDate . ' ' . $appointmentTime, new \DateTimeZone($appTimezone));
        $now = new \DateTime('now', new \DateTimeZone($appTimezone));

        if ($appointmentDateTime >= $now) {
            return null;
        }

        if ($isAjax) {
            return [
                'type' => 'json',
                'statusCode' => 422,
                'payload' => [
                    'success' => false,
                    'message' => $errorMessage,
                ],
            ];
        }

        return [
            'type' => 'redirect_back',
            'withInput' => true,
            'flash' => ['error' => $errorMessage],
        ];
    }

    public function toResponse(array $result, $response)
    {
        return match ($result['type'] ?? 'not_found') {
            'redirect' => $this->redirectToTarget($result),
            'redirect_back' => $this->redirectBack($result),
            'json' => $response->setStatusCode((int) ($result['statusCode'] ?? 400))->setJSON($result['payload'] ?? []),
            default => throw new PageNotFoundException($result['message'] ?? 'Not found'),
        };
    }

    private function redirectToTarget(array $result)
    {
        $redirect = redirect()->to($result['to'] ?? base_url());

        foreach ($result['flash'] ?? [] as $key => $value) {
            $redirect = $redirect->with($key, $value);
        }

        return $redirect;
    }

    private function redirectBack(array $result)
    {
        $redirect = redirect()->back();

        if (!empty($result['withInput'])) {
            $redirect = $redirect->withInput();
        }

        foreach ($result['flash'] ?? [] as $key => $value) {
            $redirect = $redirect->with($key, $value);
        }

        return $redirect;
    }
}