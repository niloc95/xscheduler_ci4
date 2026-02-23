<?php

/**
 * =============================================================================
 * PUBLIC BOOKING SERVICE
 * =============================================================================
 * 
 * @file        app/Services/PublicBookingService.php
 * @description Comprehensive service for public-facing appointment booking.
 *              Handles the entire booking flow from widget to confirmation.
 * 
 * PURPOSE:
 * -----------------------------------------------------------------------------
 * Powers the public booking interface with:
 * - Provider/service listing
 * - Availability calendar generation
 * - Time slot availability checking
 * - Booking form validation
 * - Appointment creation
 * - Customer management
 * - Notification triggering
 * 
 * KEY METHODS:
 * -----------------------------------------------------------------------------
 * buildViewContext()
 *   Build initial data for booking widget
 *   Returns: providers, services, initial availability
 * 
 * listProviders()
 *   Get active providers for public booking
 * 
 * listServices($providerId)
 *   Get services available for booking (optionally filtered by provider)
 * 
 * getCalendarAvailability($providerId, $month, $year)
 *   Get month view of available dates
 * 
 * getAvailableSlots($providerId, $serviceId, $date)
 *   Get available time slots for specific date
 * 
 * createBooking($data)
 *   Create appointment with full validation
 *   Handles customer creation, slot checking, notifications
 * 
 * BOOKING FLOW:
 * -----------------------------------------------------------------------------
 * 1. User selects provider (optional)
 * 2. User selects service
 * 3. Calendar shows available dates
 * 4. User selects date
 * 5. Time slots shown for selected date
 * 6. User selects time slot
 * 7. User fills customer details
 * 8. Booking submitted and validated
 * 9. Appointment created
 * 10. Notifications sent
 * 
 * DEPENDENCY INJECTION:
 * -----------------------------------------------------------------------------
 * Supports dependency injection for testing:
 * - BookingSettingsService
 * - AvailabilityService
 * - AppointmentModel
 * - CustomerModel
 * - etc.
 * 
 * @see         app/Controllers/AppFlow.php for booking routes
 * @see         app/Views/booking/*.php for UI
 * @package     App\Services
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

namespace App\Services;

use App\Exceptions\PublicBookingException;
use App\Models\AppointmentModel;
use App\Models\CustomerModel;
use App\Models\LocationModel;
use App\Models\ServiceModel;
use App\Models\SettingModel;
use App\Models\UserModel;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

class PublicBookingService
{
    private BookingSettingsService $bookingSettings;
    private AvailabilityService $availability;
    private AppointmentModel $appointments;
    private CustomerModel $customers;
    private ServiceModel $services;
    private UserModel $users;
    private LocalizationSettingsService $localization;
    private SettingModel $settings;
    private LocationModel $locations;

    public function __construct(
        ?BookingSettingsService $bookingSettings = null,
        ?AvailabilityService $availability = null,
        ?AppointmentModel $appointments = null,
        ?CustomerModel $customers = null,
        ?ServiceModel $services = null,
        ?UserModel $users = null,
        ?LocalizationSettingsService $localization = null,
        ?SettingModel $settings = null,
        ?LocationModel $locations = null,
    ) {
        $this->bookingSettings = $bookingSettings ?? new BookingSettingsService();
        $this->availability = $availability ?? new AvailabilityService();
        $this->appointments = $appointments ?? new AppointmentModel();
        $this->customers = $customers ?? new CustomerModel();
        $this->services = $services ?? new ServiceModel();
        $this->users = $users ?? new UserModel();
        $this->localization = $localization ?? new LocalizationSettingsService();
        $this->settings = $settings ?? new SettingModel();
        $this->locations = $locations ?? new LocationModel();
    }

    public function buildViewContext(): array
    {
        $providers = $this->listProviders();
        $services = $this->listServices();
        $initialAvailability = null;
        $initialCalendar = null;

        if (!empty($providers) && !empty($services)) {
            $initialCalendar = $this->availability->getCalendarAvailability(
                (int) $providers[0]['id'],
                (int) $services[0]['id'],
                null,
                60,
                $this->localization->getTimezone(),
                null
            );

            $firstDate = $initialCalendar['availableDates'][0] ?? null;
            if ($firstDate) {
                $initialAvailability = [
                    'date' => $firstDate,
                    'provider_id' => (int) $providers[0]['id'],
                    'service_id' => (int) $services[0]['id'],
                    'slots' => $initialCalendar['slotsByDate'][$firstDate] ?? [],
                ];
            }
        }

        return [
            'providers' => $providers,
            'services' => $services,
            'fieldConfig' => $this->bookingSettings->getFieldConfiguration(),
            'customFieldConfig' => $this->bookingSettings->getCustomFieldConfiguration(),
            'timezone' => $this->localization->getTimezone(),
            'timeFormat' => $this->localization->getTimeFormat(),
            'currency' => $this->localization->getCurrency(),
            'currencySymbol' => $this->localization->getCurrencySymbol(),
            'reschedulePolicy' => $this->getReschedulePolicy(),
            'initialAvailability' => $initialAvailability,
            'initialCalendar' => $initialCalendar,
            'bookingBaseUrl' => rtrim(base_url('booking'), '/'),
            'logoUrl' => function_exists('setting_url') ? setting_url('general.company_logo', 'assets/settings/default-logo.svg') : base_url('assets/settings/default-logo.svg'),
            'businessName' => function_exists('setting') ? (setting('general.company_name', 'WebSchedulr') ?: 'WebSchedulr') : 'WebSchedulr',
        ];
    }

    public function getAvailableSlots(int $providerId, int $serviceId, string $date, ?int $locationId = null): array
    {
        $provider = $this->resolveProvider($providerId);
        $service = $this->resolveService($serviceId);
        $timezone = $this->localization->getTimezone();
        $buffer = $this->availability->getBufferTime($provider['id']);

        $slots = $this->availability->getAvailableSlots(
            $provider['id'],
            $date,
            $service['id'],
            $buffer,
            $timezone,
            null,
            $locationId
        );

        return array_map(function (array $slot) use ($timezone) {
            return [
                'start' => $slot['start']->format(DATE_ATOM),
                'end' => $slot['end']->format(DATE_ATOM),
                'label' => sprintf('%s – %s',
                    $this->localization->formatTimeForDisplay($slot['start']->format('H:i:s')),
                    $this->localization->formatTimeForDisplay($slot['end']->format('H:i:s'))
                ),
                'timezone' => $timezone,
            ];
        }, $slots);
    }

    public function getAvailabilityCalendar(
        int $providerId,
        int $serviceId,
        ?string $startDate = null,
        int $days = 60,
        ?int $locationId = null
    ): array {
        return $this->availability->getCalendarAvailability(
            $providerId,
            $serviceId,
            $startDate,
            $days,
            $this->localization->getTimezone(),
            null,
            $locationId
        );
    }

    public function createBooking(array $payload): array
    {
        $provider = $this->resolveProvider((int) ($payload['provider_id'] ?? 0));
        $service = $this->resolveService((int) ($payload['service_id'] ?? 0));
        $slot = $this->normalizeSlotPayload($payload, $service['duration_min']);
        $locationId = !empty($payload['location_id']) ? (int) $payload['location_id'] : null;
        $this->assertSlotAvailable($provider['id'], $slot['start'], $slot['end'], null, $locationId);

        $customerId = $this->storeCustomer($payload);
        $token = $this->generateToken();

        // Get location snapshot if location_id provided
        $locationSnapshot = [
            'location_id' => null,
            'location_name' => null,
            'location_address' => null,
            'location_contact' => null,
        ];
        
        if (!empty($payload['location_id'])) {
            $locationSnapshot = $this->locations->getLocationSnapshot((int) $payload['location_id']);
        }

        $appointmentPayload = [
            'customer_id' => $customerId,
            'provider_id' => $provider['id'],
            'service_id' => $service['id'],
            'start_time' => $slot['start']->format('Y-m-d H:i:s'),
            'end_time' => $slot['end']->format('Y-m-d H:i:s'),
            'status' => 'pending',
            'notes' => $this->sanitizeString($payload['notes'] ?? null, 1000),
            'public_token' => $token,
            'public_token_expires_at' => null,
            // Location snapshot
            'location_id' => $locationSnapshot['location_id'],
            'location_name' => $locationSnapshot['location_name'],
            'location_address' => $locationSnapshot['location_address'],
            'location_contact' => $locationSnapshot['location_contact'],
        ];

        $appointmentId = $this->appointments->insert($appointmentPayload, true);
        if (!$appointmentId) {
            throw new PublicBookingException('Unable to create appointment at this time.');
        }

        $appointment = $this->appointments->find($appointmentId);
        return $this->formatPublicAppointment($appointment, $token);
    }

    public function lookupAppointment(string $token, ?string $email = null, ?string $phone = null): array
    {
        $appointment = $this->fetchAppointmentByToken($token);
        $this->verifyContactAccess($appointment, $email, $phone);
        return $this->formatPublicAppointment($appointment, $token);
    }

    public function reschedule(string $token, array $payload): array
    {
        $appointment = $this->fetchAppointmentByToken($token);
        $this->verifyContactAccess($appointment, $payload['email'] ?? null, $payload['phone'] ?? null);
        $this->assertRescheduleWindow($appointment['start_time']);

        $providerId = (int) ($payload['provider_id'] ?? $appointment['provider_id']);
        $serviceId = (int) ($payload['service_id'] ?? $appointment['service_id']);

        $provider = $this->resolveProvider($providerId);
        $service = $this->resolveService($serviceId);
        $slot = $this->normalizeSlotPayload($payload, $service['duration_min']);
        $newLocationId = !empty($payload['location_id']) ? (int) $payload['location_id'] : null;
        $this->assertSlotAvailable($provider['id'], $slot['start'], $slot['end'], (int) $appointment['id'], $newLocationId);

        $customerId = $this->storeCustomer($payload, (int) $appointment['customer_id']);
        $newToken = $this->generateToken();

        $updatePayload = [
            'provider_id' => $provider['id'],
            'service_id' => $service['id'],
            'start_time' => $slot['start']->format('Y-m-d H:i:s'),
            'end_time' => $slot['end']->format('Y-m-d H:i:s'),
            'notes' => $this->sanitizeString($payload['notes'] ?? $appointment['notes'] ?? null, 1000),
            'public_token' => $newToken,
            'public_token_expires_at' => null,
            'customer_id' => $customerId,
        ];

        // Re-resolve location snapshot for the new date (may differ from original)
        if ($newLocationId) {
            $locationSnapshot = $this->locations->getLocationSnapshot($newLocationId);
            if ($locationSnapshot['location_id'] !== null) {
                $updatePayload['location_id'] = $locationSnapshot['location_id'];
                $updatePayload['location_name'] = $locationSnapshot['location_name'];
                $updatePayload['location_address'] = $locationSnapshot['location_address'];
                $updatePayload['location_contact'] = $locationSnapshot['location_contact'];
            }
        }

        if (!$this->appointments->update((int) $appointment['id'], $updatePayload)) {
            throw new PublicBookingException('Unable to reschedule appointment. Please try again later.');
        }

        // Enqueue rescheduled notification
        try {
            $queue = new NotificationQueueService();
            $businessId = NotificationPhase1::BUSINESS_ID_DEFAULT;
            $queue->enqueueAppointmentEvent($businessId, 'email', 'appointment_rescheduled', (int) $appointment['id']);
            $queue->enqueueAppointmentEvent($businessId, 'whatsapp', 'appointment_rescheduled', (int) $appointment['id']);
        } catch (Throwable $e) {
            log_message('error', '[PublicBookingService::reschedule] Notification enqueue failed: ' . $e->getMessage());
        }

        $updated = $this->appointments->find((int) $appointment['id']);
        return $this->formatPublicAppointment($updated, $newToken);
    }

    private function listProviders(): array
    {
        $rows = $this->users->where('role', 'provider')->where('is_active', true)->orderBy('name', 'ASC')->findAll();
        return array_map(function (array $row): array {
            $providerId = (int) $row['id'];
            $locations = $this->locations->getProviderLocationsWithDays($providerId);
            
            // Check if provider has locations configured
            $hasLocations = !empty($locations);
            
            return [
                'id' => $providerId,
                'name' => $row['name'] ?? 'Provider',
                'color' => $row['color'] ?? '#3B82F6',
                'has_locations' => $hasLocations,
                'locations' => array_map(static function (array $loc): array {
                    return [
                        'id' => (int) $loc['id'],
                        'name' => $loc['name'],
                        // Address and contact only shown after selection
                        'address' => $loc['address'],
                        'contact_number' => $loc['contact_number'],
                        'is_primary' => (bool) ($loc['is_primary'] ?? false),
                        'days' => $loc['days'] ?? [],
                    ];
                }, $locations),
            ];
        }, $rows);
    }

    private function listServices(): array
    {
        $rows = $this->services->where('active', 1)->orderBy('name', 'ASC')->findAll();
        return array_map(function (array $row): array {
            $price = $row['price'] ?? 0;
            return [
                'id' => (int) $row['id'],
                'name' => $row['name'] ?? 'Service',
                'duration' => (int) ($row['duration_min'] ?? 30),
                'price' => (float) $price,
                'formattedPrice' => $this->localization->formatCurrency($price),
            ];
        }, $rows);
    }

    private function resolveProvider(int $providerId): array
    {
        if ($providerId <= 0) {
            throw new PublicBookingException('Provider selection is required.', 422, ['provider_id' => 'required']);
        }

        $provider = $this->users->where('id', $providerId)->where('role', 'provider')->where('is_active', true)->first();
        if (!$provider) {
            throw new PublicBookingException('Selected provider is unavailable.', 404);
        }

        return $provider;
    }

    private function resolveService(int $serviceId): array
    {
        if ($serviceId <= 0) {
            throw new PublicBookingException('Service selection is required.', 422, ['service_id' => 'required']);
        }

        $service = $this->services->where('id', $serviceId)->where('active', 1)->first();
        if (!$service) {
            throw new PublicBookingException('Selected service is unavailable.', 404);
        }

        return $service;
    }

    private function normalizeSlotPayload(array $payload, int $durationMinutes): array
    {
        $timezone = new DateTimeZone($this->localization->getTimezone());
        $rawStart = $payload['slot_start'] ?? $payload['start_time'] ?? null;

        if ($rawStart) {
            try {
                $start = new DateTimeImmutable($rawStart);
            } catch (Throwable $e) {
                throw new PublicBookingException('Invalid slot start time provided.', 422, ['slot_start' => 'invalid']);
            }
        } elseif (!empty($payload['date']) && !empty($payload['start'])) {
            $raw = sprintf('%s %s', $payload['date'], $payload['start']);
            try {
                $start = new DateTimeImmutable($raw, $timezone);
            } catch (Throwable $e) {
                throw new PublicBookingException('Invalid date or time supplied.', 422, ['date' => 'invalid']);
            }
        } else {
            throw new PublicBookingException('A valid appointment slot is required.', 422, ['slot_start' => 'required']);
        }

        $start = $start->setTimezone($timezone);
        $end = $start->add(new DateInterval('PT' . max(1, $durationMinutes) . 'M'));

        return [
            'start' => $start,
            'end' => $end,
            'date' => $start->format('Y-m-d'),
        ];
    }

    private function assertSlotAvailable(int $providerId, DateTimeImmutable $start, DateTimeImmutable $end, ?int $excludeAppointmentId, ?int $locationId = null): void
    {
        $result = $this->availability->isSlotAvailable(
            $providerId,
            $start->format('Y-m-d H:i:s'),
            $end->format('Y-m-d H:i:s'),
            $this->localization->getTimezone(),
            $excludeAppointmentId,
            $locationId
        );

        if (!$result['available']) {
            throw new PublicBookingException(
                $result['reason'] ?: 'Selected slot is no longer available.',
                409,
                ['slot' => 'unavailable']
            );
        }
    }

    private function storeCustomer(array $payload, ?int $existingId = null): int
    {
        $fieldConfig = $this->bookingSettings->getFieldConfiguration();
        $customConfig = $this->bookingSettings->getCustomFieldConfiguration();
        $data = $this->extractCustomerFields($payload, $fieldConfig, $customConfig);

        if ($existingId) {
            $this->customers->update($existingId, $data);
            return $existingId;
        }

        if (!empty($data['email'])) {
            $existing = $this->customers->where('email', $data['email'])->first();
            if ($existing) {
                $this->customers->update((int) $existing['id'], $data);
                return (int) $existing['id'];
            }
        }

        if (!empty($data['phone']) && empty($data['email'])) {
            $existing = $this->customers->where('phone', $data['phone'])->first();
            if ($existing) {
                $this->customers->update((int) $existing['id'], $data);
                return (int) $existing['id'];
            }
        }

        $insertId = $this->customers->insert($data, true);
        if (!$insertId) {
            throw new PublicBookingException('Unable to save customer information.');
        }

        return (int) $insertId;
    }

    private function extractCustomerFields(array $payload, array $fieldConfig, array $customConfig): array
    {
        $fields = [];
        $firstName = $this->sanitizeString($payload['first_name'] ?? null, 100);
        $lastName = $this->sanitizeString($payload['last_name'] ?? null, 100);
        $email = $this->sanitizeString($payload['email'] ?? null, 150);
        $phone = $this->normalizePhone($payload['phone'] ?? null);
        $address = $this->sanitizeString($payload['address'] ?? null, 255);
        $notes = $this->sanitizeString($payload['customer_notes'] ?? $payload['notes'] ?? null, 1000);

        if (($fieldConfig['first_name']['required'] ?? false) && $firstName === null) {
            throw new PublicBookingException('First name is required.', 422, ['first_name' => 'required']);
        }
        if (($fieldConfig['last_name']['required'] ?? false) && $lastName === null) {
            throw new PublicBookingException('Last name is required.', 422, ['last_name' => 'required']);
        }
        if (($fieldConfig['email']['required'] ?? false) && $email === null) {
            throw new PublicBookingException('Email is required.', 422, ['email' => 'required']);
        }
        if (($fieldConfig['phone']['required'] ?? false) && $phone === null) {
            throw new PublicBookingException('Phone number is required.', 422, ['phone' => 'required']);
        }
        if (($fieldConfig['address']['required'] ?? false) && $address === null) {
            throw new PublicBookingException('Address is required.', 422, ['address' => 'required']);
        }
        if (($fieldConfig['notes']['required'] ?? false) && $notes === null) {
            throw new PublicBookingException('Notes are required.', 422, ['notes' => 'required']);
        }

        if (($fieldConfig['first_name']['display'] ?? true) && $firstName !== null) {
            $fields['first_name'] = $firstName;
        }
        if (($fieldConfig['last_name']['display'] ?? true) && $lastName !== null) {
            $fields['last_name'] = $lastName;
        }
        if (($fieldConfig['email']['display'] ?? true) && $email !== null) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new PublicBookingException('Please provide a valid email address.', 422, ['email' => 'invalid']);
            }
            $fields['email'] = strtolower($email);
        }
        if (($fieldConfig['phone']['display'] ?? true) && $phone !== null) {
            $fields['phone'] = $phone;
        }
        if (($fieldConfig['address']['display'] ?? false) && $address !== null) {
            $fields['address'] = $address;
        }
        if (($fieldConfig['notes']['display'] ?? false) && $notes !== null) {
            $fields['notes'] = $notes;
        }

        $customPayload = [];
        foreach ($customConfig as $field => $config) {
            if (!array_key_exists($field, $payload)) {
                continue;
            }
            $value = $this->sanitizeCustomField($payload[$field], $config['type']);
            if ($value === null) {
                if ($config['required']) {
                    throw new PublicBookingException('Please complete all required custom fields.', 422, [$field => 'required']);
                }
                continue;
            }
            $customPayload[$field] = $value;
        }

        if (!empty($customPayload)) {
            $fields['custom_fields'] = json_encode($customPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        if (empty($fields['first_name']) && !empty($fields['last_name'])) {
            $fields['first_name'] = 'Guest';
        }
        if (empty($fields['first_name']) && empty($fields['last_name'])) {
            $fields['first_name'] = 'Guest';
        }

        if (empty($fields['email']) && empty($fields['phone'])) {
            throw new PublicBookingException('Please supply at least one contact method (email or phone).', 422, ['contact' => 'required']);
        }

        return $fields;
    }

    private function sanitizeString(?string $value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim(strip_tags($value));
        if ($trimmed === '') {
            return null;
        }
        return substr($trimmed, 0, $maxLength);
    }

    private function sanitizeCustomField($value, string $type): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            $value = implode(', ', array_map('strval', $value));
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if ($type === 'checkbox') {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true) ? '1' : '0';
        }

        return substr(strip_tags($value), 0, 255);
    }

    private function normalizePhone(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $digits = preg_replace('/[^0-9+]/', '', $value);
        return $digits !== '' ? substr($digits, 0, 20) : null;
    }

    private function formatPublicAppointment(?array $appointment, string $token): array
    {
        if (!$appointment) {
            throw new PublicBookingException('Appointment could not be loaded.');
        }

        $timezone = new DateTimeZone($this->localization->getTimezone());
        $start = new DateTimeImmutable($appointment['start_time'], $timezone);
        $end = new DateTimeImmutable($appointment['end_time'], $timezone);
        $provider = $this->users->find((int) $appointment['provider_id']);
        $service = $this->services->find((int) $appointment['service_id']);

        // Resolve full customer record for field population
        $customerData = [
            'first_name' => null,
            'last_name'  => null,
            'email'      => $appointment['customer_email'] ?? null,
            'phone'      => $appointment['customer_phone'] ?? null,
            'address'    => null,
        ];

        if (!empty($appointment['customer_id'])) {
            $customer = $this->customers->find((int) $appointment['customer_id']);
            if ($customer) {
                $customerData['first_name'] = $customer['first_name'] ?? null;
                $customerData['last_name']  = $customer['last_name'] ?? null;
                $customerData['email']      = $customerData['email'] ?? $customer['email'] ?? null;
                $customerData['phone']      = $customerData['phone'] ?? $customer['phone'] ?? null;
                $customerData['address']    = $customer['address'] ?? null;
            }
        }

        return [
            'token' => $token,
            'provider_id' => (int) $appointment['provider_id'],
            'service_id' => (int) $appointment['service_id'],
            'start' => $start->format(DATE_ATOM),
            'end' => $end->format(DATE_ATOM),
            'status' => $appointment['status'] ?? 'pending',
            'notes' => $appointment['notes'] ?? null,
            'timezone' => $timezone->getName(),
            'display_range' => $this->formatSlotRange($start, $end),
            'provider' => $provider ? [
                'id' => (int) $provider['id'],
                'name' => $provider['name'] ?? 'Provider',
                'color' => $provider['color'] ?? '#3B82F6',
            ] : null,
            'service' => $service ? [
                'id' => (int) $service['id'],
                'name' => $service['name'] ?? 'Service',
                'duration' => (int) ($service['duration_min'] ?? 30),
                'price' => (float) ($service['price'] ?? 0),
                'formattedPrice' => $this->localization->formatCurrency($service['price'] ?? 0),
            ] : null,
            'customer' => $customerData,
            // Location snapshot (may be null for legacy bookings)
            'location_id' => !empty($appointment['location_id']) ? (int) $appointment['location_id'] : null,
            'location_name' => $appointment['location_name'] ?? null,
            'location_address' => $appointment['location_address'] ?? null,
            'location_contact' => $appointment['location_contact'] ?? null,
        ];
    }

    private function formatSlotRange(DateTimeImmutable $start, DateTimeImmutable $end): string
    {
        $dateLabel  = $start->format('D, M j');
        $startLabel = $this->localization->formatTimeForDisplay($start->format('H:i:s'));
        $endLabel   = $this->localization->formatTimeForDisplay($end->format('H:i:s'));
        return sprintf('%s at %s – %s', $dateLabel, $startLabel, $endLabel);
    }

    private function fetchAppointmentByToken(string $token): array
    {
        if (trim($token) === '') {
            throw new PublicBookingException('Confirmation token is required.', 422, ['token' => 'required']);
        }

        $builder = $this->appointments->builder();
        $builder->select('xs_appointments.*, c.email as customer_email, c.phone as customer_phone, c.id as customer_id')
            ->join('xs_customers as c', 'c.id = xs_appointments.customer_id', 'left')
            ->where('xs_appointments.public_token', $token);

        $record = $builder->get()->getRowArray();
        if (!$record) {
            throw new PublicBookingException('We could not find a booking for that token.', 404);
        }

        return $record;
    }

    private function verifyContactAccess(array $appointment, ?string $email, ?string $phone): void
    {
        $expectedEmail = strtolower((string) ($appointment['customer_email'] ?? ''));
        $expectedPhone = $this->normalizePhone($appointment['customer_phone'] ?? null);

        if ($expectedEmail) {
            if (!$email || strtolower(trim($email)) !== $expectedEmail) {
                throw new PublicBookingException('Contact verification failed.', 403);
            }
            return;
        }

        if ($expectedPhone) {
            if (!$phone || $this->normalizePhone($phone) !== $expectedPhone) {
                throw new PublicBookingException('Contact verification failed.', 403);
            }
        }
    }

    private function assertRescheduleWindow(string $currentStart): void
    {
        $policy = $this->getReschedulePolicy();
        if (!$policy['enabled']) {
            throw new PublicBookingException('Rescheduling is not permitted for this booking.', 403);
        }

        $timezone = new DateTimeZone($this->localization->getTimezone());
        $start = new DateTimeImmutable($currentStart, $timezone);
        $now = new DateTimeImmutable('now', $timezone);

        if ($start <= $now) {
            throw new PublicBookingException('This appointment can no longer be rescheduled.', 403);
        }

        $cutoff = $now->add(new DateInterval('PT' . ($policy['hours'] ?? 0) . 'H'));
        if ($cutoff > $start) {
            throw new PublicBookingException('This appointment is too close to reschedule online.', 403);
        }
    }

    private function getReschedulePolicy(): array
    {
        $settings = $this->settings->getByKeys(['business.reschedule']);
        $value = $settings['business.reschedule'] ?? '24h';

        return match ($value) {
            'none' => ['enabled' => false, 'label' => 'Disabled'],
            '12h' => ['enabled' => true, 'hours' => 12, 'label' => '12 hours'],
            '48h' => ['enabled' => true, 'hours' => 48, 'label' => '48 hours'],
            default => ['enabled' => true, 'hours' => 24, 'label' => '24 hours'],
        };
    }

    private function generateToken(): string
    {
        $bytes = random_bytes(16);
        $hex = bin2hex($bytes);
        return sprintf('%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }

}
