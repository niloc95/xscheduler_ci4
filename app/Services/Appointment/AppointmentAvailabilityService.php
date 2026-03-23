<?php

namespace App\Services\Appointment;

use App\Models\LocationModel;
use App\Services\AvailabilityService;
use App\Services\LocalizationSettingsService;
use App\Services\TimezoneService;

class AppointmentAvailabilityService
{
    private AvailabilityService $availabilityService;
    private LocalizationSettingsService $localizationSettingsService;
    private LocationModel $locationModel;

    public function __construct(
        ?AvailabilityService $availabilityService = null,
        ?LocalizationSettingsService $localizationSettingsService = null,
        ?LocationModel $locationModel = null,
    ) {
        $this->availabilityService = $availabilityService ?? new AvailabilityService();
        $this->localizationSettingsService = $localizationSettingsService ?? new LocalizationSettingsService();
        $this->locationModel = $locationModel ?? new LocationModel();
    }

    public function checkFromPayload(array $payload, ?string $headerTimezone = null): array
    {
        $validation = $this->validatePayload($payload);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'statusCode' => 400,
                'message' => $validation['message'],
                'errors' => ['required' => ['provider_id', 'service_id', 'start_time']],
            ];
        }

        $providerId = $validation['provider_id'];
        $serviceId = $validation['service_id'];
        $startTime = $validation['start_time'];
        $appointmentId = isset($payload['appointment_id']) ? (int) $payload['appointment_id'] : null;
        $requestedLocationId = isset($payload['location_id']) && $payload['location_id'] !== ''
            ? (int) $payload['location_id']
            : null;

        $timezone = $headerTimezone ?: ($payload['timezone'] ?? null);
        if (!$timezone || !TimezoneService::isValidTimezone($timezone)) {
            $timezone = $this->localizationSettingsService->getTimezone();
        }

        $locationContext = $this->resolveProviderLocationContext($providerId, $requestedLocationId);
        if (!$locationContext['valid']) {
            return [
                'success' => false,
                'statusCode' => 422,
                'message' => $locationContext['reason'],
            ];
        }

        $db = \Config\Database::connect();
        $service = $db->table($db->prefixTable('services'))
            ->select('duration_min, name')
            ->where('id', $serviceId)
            ->where('active', 1)
            ->get()
            ->getRowArray();

        if (!$service) {
            return [
                'success' => false,
                'statusCode' => 404,
                'message' => 'Service not found or inactive',
                'errors' => ['service_id' => $serviceId],
            ];
        }

        try {
            $startDateTime = new \DateTime($startTime, new \DateTimeZone($timezone));
        } catch (\Exception $e) {
            log_message('error', 'Timezone conversion failed in availability check: ' . $e->getMessage());
            $timezone = 'UTC';
            $startDateTime = new \DateTime($startTime, new \DateTimeZone($timezone));
        }

        $endDateTime = clone $startDateTime;
        $endDateTime->modify('+' . ((int) ($service['duration_min'] ?? 0)) . ' minutes');

        $startLocal = $startDateTime->format('Y-m-d H:i:s');
        $endLocal = $endDateTime->format('Y-m-d H:i:s');

        $availabilityCheck = $this->availabilityService->isSlotAvailable(
            $providerId,
            $startLocal,
            $endLocal,
            $timezone,
            $appointmentId,
            $locationContext['location_id']
        );

        $result = [
            'available' => $availabilityCheck['available'],
            'requestedSlot' => [
                'provider_id' => $providerId,
                'service_id' => $serviceId,
                'service_name' => $service['name'] ?? '',
                'duration_min' => (int) ($service['duration_min'] ?? 0),
                'location_id' => $locationContext['location_id'],
                'start_time_local' => $startLocal,
                'end_time_local' => $endLocal,
                'start_time_utc' => TimezoneService::toUTC($startLocal, $timezone),
                'end_time_utc' => TimezoneService::toUTC($endLocal, $timezone),
                'timezone' => $timezone,
            ],
            'conflicts' => $availabilityCheck['conflicts'] ?? [],
            'reason' => $availabilityCheck['reason'] ?? null,
        ];

        if (!$availabilityCheck['available']) {
            $nextSlot = clone $startDateTime;
            $nextSlot->modify('+30 minutes');
            $result['suggestedNextSlot'] = [
                'local' => $nextSlot->format('Y-m-d H:i:s'),
                'utc' => TimezoneService::toUTC($nextSlot->format('Y-m-d H:i:s'), $timezone),
            ];
        }

        return [
            'success' => true,
            'data' => $result,
        ];
    }

    private function validatePayload(array $payload): array
    {
        $providerId = isset($payload['provider_id']) ? (int) $payload['provider_id'] : 0;
        $serviceId = isset($payload['service_id']) ? (int) $payload['service_id'] : 0;
        $startTime = isset($payload['start_time']) ? trim((string) $payload['start_time']) : '';

        if ($providerId <= 0 || $serviceId <= 0 || $startTime === '') {
            return ['valid' => false, 'message' => 'Missing required fields'];
        }

        return [
            'valid' => true,
            'message' => null,
            'provider_id' => $providerId,
            'service_id' => $serviceId,
            'start_time' => $startTime,
        ];
    }

    /**
     * Appointments API keeps stricter location semantics than the general
     * availability controller: providers with active locations must receive an
     * explicit location_id from the client.
     */
    private function resolveProviderLocationContext(int $providerId, ?int $requestedLocationId): array
    {
        $activeLocations = $this->locationModel->getProviderLocations($providerId, true);

        if (empty($activeLocations)) {
            return ['valid' => true, 'location_id' => null, 'reason' => null];
        }

        $activeLocationIds = array_map(static fn(array $location): int => (int) ($location['id'] ?? 0), $activeLocations);

        if ($requestedLocationId !== null) {
            if (!in_array($requestedLocationId, $activeLocationIds, true)) {
                return ['valid' => false, 'location_id' => null, 'reason' => 'Selected location is unavailable for this provider'];
            }

            return ['valid' => true, 'location_id' => $requestedLocationId, 'reason' => null];
        }

        return ['valid' => false, 'location_id' => null, 'reason' => 'location_id is required for providers with active locations'];
    }
}