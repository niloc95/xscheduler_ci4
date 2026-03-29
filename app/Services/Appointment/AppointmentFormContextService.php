<?php

namespace App\Services\Appointment;

use App\Models\AppointmentModel;
use App\Models\ServiceModel;
use App\Models\UserModel;
use App\Services\BookingSettingsService;
use App\Services\LocalizationSettingsService;
use App\Services\TimezoneService;
use CodeIgniter\Exceptions\PageNotFoundException;

class AppointmentFormContextService
{
    private BookingSettingsService $bookingSettingsService;
    private LocalizationSettingsService $localizationSettingsService;
    private AppointmentModel $appointmentModel;
    private UserModel $userModel;
    private ServiceModel $serviceModel;

    public function __construct(
        ?BookingSettingsService $bookingSettingsService = null,
        ?LocalizationSettingsService $localizationSettingsService = null,
        ?AppointmentModel $appointmentModel = null,
        ?UserModel $userModel = null,
        ?ServiceModel $serviceModel = null,
    ) {
        $this->bookingSettingsService = $bookingSettingsService ?? new BookingSettingsService();
        $this->localizationSettingsService = $localizationSettingsService ?? new LocalizationSettingsService();
        $this->appointmentModel = $appointmentModel ?? new AppointmentModel();
        $this->userModel = $userModel ?? new UserModel();
        $this->serviceModel = $serviceModel ?? new ServiceModel();
    }

    public function buildCreateViewData(string $userRole): array
    {
        return array_merge($this->buildBaseViewData($userRole), [
            'title' => 'Book Appointment',
        ]);
    }

    public function buildEditViewData(string $appointmentHash, string $userRole): array
    {
        $appointment = $this->loadAppointmentForEdit($appointmentHash);
        if ($appointment === null) {
            throw new PageNotFoundException('Appointment not found');
        }

        $timezone = $this->localizationSettingsService->getTimezone();
        $appointment = $this->mergeCustomerCustomFields($appointment);
        $appointment = $this->appendDisplayDateTime($appointment, $timezone);

        return array_merge($this->buildBaseViewData($userRole), [
            'title' => 'Edit Appointment',
            'appointment' => $appointment,
            'isPastAppointment' => $this->isPastAppointment($appointment),
        ]);
    }

    private function buildBaseViewData(string $userRole): array
    {
        $dropdowns = $this->getDropdownData();

        return [
            'current_page' => 'appointments',
            'services' => $dropdowns['services'],
            'providers' => $dropdowns['providers'],
            'user_role' => $userRole,
            'fieldConfig' => $this->bookingSettingsService->getFieldConfiguration(),
            'customFields' => $this->bookingSettingsService->getCustomFieldConfiguration(),
            'localization' => $this->localizationSettingsService->getContext(),
        ];
    }

    private function loadAppointmentForEdit(string $appointmentHash): ?array
    {
        $appointment = $this->appointmentModel
            ->select('xs_appointments.*, 
                     c.first_name as customer_first_name,
                     c.last_name as customer_last_name,
                     c.email as customer_email,
                     c.phone as customer_phone,
                     c.address as customer_address,
                     c.notes as customer_notes,
                     c.custom_fields as customer_custom_fields,
                     c.hash as customer_hash', false)
            ->join('xs_customers c', 'c.id = xs_appointments.customer_id', 'left')
            ->where('xs_appointments.hash', $appointmentHash)
            ->first();

        return $appointment ?: null;
    }

    private function mergeCustomerCustomFields(array $appointment): array
    {
        if (empty($appointment['customer_custom_fields'])) {
            return $appointment;
        }

        $customFields = json_decode((string) $appointment['customer_custom_fields'], true);
        if (!is_array($customFields)) {
            return $appointment;
        }

        foreach ($customFields as $key => $value) {
            $appointment[$key] = $value;
        }

        return $appointment;
    }

    private function appendDisplayDateTime(array $appointment, string $timezone): array
    {
        if (empty($appointment['start_at'])) {
            return $appointment;
        }

        $displayTime = TimezoneService::toDisplay((string) $appointment['start_at'], $timezone);
        $startDateTime = new \DateTime($displayTime, new \DateTimeZone($timezone));
        $appointment['date'] = $startDateTime->format('Y-m-d');
        $appointment['time'] = $startDateTime->format('H:i');

        return $appointment;
    }

    private function isPastAppointment(array $appointment): bool
    {
        if (empty($appointment['start_at'])) {
            return false;
        }

        $appointmentTime = new \DateTime((string) $appointment['start_at'], new \DateTimeZone('UTC'));
        $now = new \DateTime('now', new \DateTimeZone('UTC'));

        return $appointmentTime < $now;
    }

    private function getDropdownData(): array
    {
        $providers = $this->userModel->getProvidersWithActiveServices();
        $services = $this->serviceModel->where('active', 1)->findAll();

        return [
            'providers' => array_map([$this, 'formatProviderForDropdown'], $providers),
            'services' => array_map([$this, 'formatServiceForDropdown'], $services),
        ];
    }

    private function formatProviderForDropdown(array $provider): array
    {
        return [
            'id' => $provider['id'],
            'name' => $provider['name'],
            'speciality' => 'Provider',
        ];
    }

    private function formatServiceForDropdown(array $service): array
    {
        return [
            'id' => $service['id'],
            'name' => $service['name'],
            'duration' => $service['duration_min'],
            'price' => $service['price'],
        ];
    }
}