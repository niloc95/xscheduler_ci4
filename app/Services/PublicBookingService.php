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
use App\Services\Appointment\AppointmentStatus;
use App\Models\CustomerModel;
use App\Models\LocationModel;
use App\Models\ServiceModel;
use App\Models\SettingModel;
use App\Models\UserModel;
use App\Services\PublicBooking\PublicBookingDirectory;
use App\Services\PublicBooking\PublicBookingInputSanitizer;
use App\Services\PublicBooking\PublicBookingPolicy;
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
    private AppointmentBookingService $bookingService;
    private PhoneNumberService $phoneNumberService;
    private CustomerService $customerService;
    private CustomerCustomFieldService $customerCustomFieldService;
    private PublicBookingDirectory $directory;
    private PublicBookingPolicy $policy;
    private PublicBookingInputSanitizer $inputSanitizer;

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
        ?AppointmentBookingService $bookingService = null,
        ?CustomerService $customerService = null,
        ?CustomerCustomFieldService $customerCustomFieldService = null,
    ) {
        helper('app');

        $this->bookingSettings = $bookingSettings ?? new BookingSettingsService();
        $this->availability = $availability ?? new AvailabilityService();
        $this->appointments = $appointments ?? new AppointmentModel();
        $this->customers = $customers ?? new CustomerModel();
        $this->services = $services ?? new ServiceModel();
        $this->users = $users ?? new UserModel();
        $this->localization = $localization ?? new LocalizationSettingsService();
        $this->settings = $settings ?? new SettingModel();
        $this->locations = $locations ?? new LocationModel();
        $this->bookingService = $bookingService ?? new AppointmentBookingService();
        $this->phoneNumberService = new PhoneNumberService($this->settings);
        $this->customerService = $customerService ?? new CustomerService($this->customers, $this->phoneNumberService);
        $this->customerCustomFieldService = $customerCustomFieldService ?? new CustomerCustomFieldService($this->customers);
        $this->directory = new PublicBookingDirectory($this->users, $this->services, $this->locations, $this->settings, $this->localization);
        $this->policy = new PublicBookingPolicy($this->settings);
        $this->inputSanitizer = new PublicBookingInputSanitizer($this->phoneNumberService, $this->customerCustomFieldService);
    }

    public function buildViewContext(): array
    {
        $providers = $this->directory->listProviders();
        $initialProviderId = !empty($providers) ? (int) ($providers[0]['id'] ?? 0) : null;
        $services = $initialProviderId ? $this->directory->listServices($initialProviderId) : [];

        return [
            'providers' => $this->directory->sanitizeProvidersForPublic($providers),
            'services' => $services,
            'fieldConfig' => $this->bookingSettings->getFieldConfiguration(),
            'customFieldConfig' => $this->bookingSettings->getCustomFieldConfiguration(),
            'timezone' => $this->localization->getTimezone(),
            'timeFormat' => $this->localization->getTimeFormat(),
            'currency' => $this->localization->getCurrency(),
            'currencySymbol' => $this->localization->getCurrencySymbol(),
            'reschedulePolicy' => $this->policy->getReschedulePolicy(),
            'cancelPolicy' => $this->policy->getCancelPolicy(),
            'futureLimitDays' => $this->policy->getFutureLimitDays(),
            'legalPolicies' => $this->policy->getLegalPolicyContext(),
            'appBaseUrl' => rtrim(base_url(), '/'),
            'bookingBaseUrl' => rtrim(base_url('booking'), '/'),
            'logoUrl' => function_exists('setting_url') ? setting_url('general.company_logo', 'assets/settings/default-logo.svg') : base_url('assets/settings/default-logo.svg'),
            'businessName' => function_exists('setting') ? (setting('general.company_name', 'WebSchedulr') ?: 'WebSchedulr') : 'WebSchedulr',
            'defaultPhoneCountryCode' => $this->phoneNumberService->getDefaultCountryCode(),
            'seo' => $this->directory->buildSeoContext(),
            'zoomConnected'  => (new \App\Services\ZoomIntegrationService())->getPublicIntegration(\App\Services\NotificationCatalog::BUSINESS_ID_DEFAULT)['is_active'] ?? false,
            'jitsiConnected' => (new \App\Services\JitsiIntegrationService())->getPublicIntegration(\App\Services\NotificationCatalog::BUSINESS_ID_DEFAULT)['is_active'] ?? false,
        ];
    }

    public function buildViewContextForProviderSlug(string $slug): array
    {
        $provider = $this->directory->findProviderBySlug($slug);
        if (!$provider) {
            throw new PublicBookingException('Provider not found.', 404);
        }

        $providerId = (int) $provider['id'];
        $providers = $this->directory->listProviders();
        usort($providers, static function (array $a, array $b) use ($providerId): int {
            if ((int) $a['id'] === $providerId) {
                return -1;
            }
            if ((int) $b['id'] === $providerId) {
                return 1;
            }
            return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

        $services = $this->directory->listServices($providerId);
        $context = $this->buildViewContext();
        $context['providers'] = $this->directory->sanitizeProvidersForPublic($providers);
        $context['services'] = $services;
        $context['initialFilter'] = [
            'providerSlug' => $slug,
        ];
        $context['seo'] = $this->directory->buildSeoContextForProvider($provider);

        return $context;
    }

    public function buildViewContextForServiceSlug(string $serviceSlug, ?string $city = null): array
    {
        $service = $this->directory->findServiceBySlug($serviceSlug);
        if (!$service) {
            throw new PublicBookingException('Service not found.', 404);
        }

        $serviceId = (int) $service['id'];
        $providers = $this->directory->listProviders($serviceId, $city);
        $initialProviderId = !empty($providers) ? (int) $providers[0]['id'] : null;

        $services = [];
        if ($initialProviderId !== null) {
            $providerServices = $this->directory->listServices($initialProviderId);
            foreach ($providerServices as $providerService) {
                if ((int) ($providerService['id'] ?? 0) === $serviceId) {
                    $services[] = $providerService;
                    break;
                }
            }
        }

        if ($services === []) {
            helper('currency');
            $gateways   = (new \App\Services\Payment\PaymentService())->getGatewayAvailability(1);
            $price      = (float) ($service['price'] ?? 0);
            $depositPct = isset($service['deposit_percentage']) && $service['deposit_percentage'] !== null
                ? (float) $service['deposit_percentage'] : null;
            $depositAmt = ($service['payment_enabled'] ?? false) && $depositPct
                ? calculate_deposit_amount($price, $depositPct) : null;
            $services[] = [
                'id'                 => $serviceId,
                'name'               => $service['name'] ?? 'Service',
                'slug'               => $service['slug'] ?? null,
                'duration'           => (int) ($service['duration_min'] ?? 30),
                'price'              => $price,
                'formattedPrice'     => $this->localization->formatCurrency($price),
                'payment_enabled'    => (bool) ($service['payment_enabled']  ?? false),
                'payfast_enabled'    => (bool) ($service['payfast_enabled']  ?? false),
                'stripe_enabled'     => (bool) ($service['stripe_enabled']   ?? false),
                'payfastAvailable'   => (bool) ($service['payfast_enabled']  ?? false) && $gateways['payfast'],
                'stripeAvailable'    => (bool) ($service['stripe_enabled']   ?? false) && $gateways['stripe'],
                'deposit_percentage' => $depositPct,
                'deposit_amount'     => $depositAmt,
                'formattedDeposit'   => $depositAmt !== null ? $this->localization->formatCurrency($depositAmt) : null,
            ];
        }

        $context = $this->buildViewContext();
        $context['providers'] = $this->directory->sanitizeProvidersForPublic($providers);
        $context['services'] = $services;
        $context['initialFilter'] = [
            'serviceSlug' => $serviceSlug,
            'serviceId' => $serviceId,
            'city' => $city,
        ];
        $context['seo'] = $this->directory->buildSeoContextForService($service, $providers, $city);

        return $context;
    }

    public function discoverByServiceAndLocation(string $serviceQuery, ?string $city = null, ?string $area = null): array
    {
        $service = $this->directory->resolveServiceQuery($serviceQuery);
        if (!$service) {
            throw new PublicBookingException('No matching service found.', 404, ['service' => 'not_found']);
        }

        $serviceId = (int) ($service['id'] ?? 0);
        $serviceSlug = (string) ($service['slug'] ?? '');
        $locationNeedle = $this->directory->normalizeLocationNeedle($city, $area);

        $providers = $this->directory->listProviders($serviceId, $city, $area);

        $flatLocations = [];
        foreach ($providers as $provider) {
            foreach (($provider['locations'] ?? []) as $loc) {
                $flatLocations[] = [
                    'provider_slug' => $provider['slug'] ?? null,
                    'provider_name' => $provider['name'] ?? 'Provider',
                    'location_id' => (int) ($loc['id'] ?? 0),
                    'location_name' => $loc['name'] ?? 'Location',
                    'city' => $loc['city'] ?? null,
                    'area' => $loc['area'] ?? null,
                    'address' => $loc['address'] ?? null,
                ];
            }
        }

        $canonical = base_url('booking/s/' . rawurlencode($serviceSlug));
        if ($locationNeedle !== null) {
            $canonical = base_url('booking/s/' . rawurlencode($serviceSlug) . '/' . rawurlencode(strtolower($locationNeedle)));
        }

        $context = $locationNeedle !== null
            ? $this->buildViewContextForServiceSlug($serviceSlug, $locationNeedle)
            : $this->buildViewContextForServiceSlug($serviceSlug, null);

        if ($city !== null && $area !== null) {
            $context = $this->buildViewContextForServiceSlug($serviceSlug, null);
            $context['providers'] = $this->directory->sanitizeProvidersForPublic($providers);
            $context['initialFilter'] = [
                'serviceSlug' => $serviceSlug,
                'serviceId' => $serviceId,
                'city' => $locationNeedle,
            ];
            $context['seo'] = $this->directory->buildSeoContextForService($service, $providers, $locationNeedle);
        }

        $context['discover'] = [
            'query' => [
                'service' => $serviceQuery,
                'city' => $city,
                'area' => $area,
            ],
            'providerCount' => count($providers),
            'locationCount' => count($flatLocations),
            'canonical' => $canonical,
        ];

        return [
            'service' => [
                'id' => $serviceId,
                'name' => $service['name'] ?? 'Service',
                'slug' => $serviceSlug,
            ],
            'query' => [
                'service' => $serviceQuery,
                'city' => $city,
                'area' => $area,
            ],
            'providers' => $this->directory->sanitizeProvidersForPublic($providers),
            'locations' => $flatLocations,
            'canonical' => $canonical,
            'context' => $context,
        ];
    }

    /**
     * Absolute URLs for the public sitemap (booking home, providers, services, locations).
     */
    public function getSitemapUrls(): array
    {
        return $this->directory->getSitemapUrls();
    }

    /**
     * How many days ahead customers may book (booking horizon).
     */
    public function getFutureLimitDays(): int
    {
        return $this->policy->getFutureLimitDays();
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
        // Honeypot: bots typically fill every visible field including hidden traps.
        // The 'website' field is rendered as display:none in the public form; any
        // non-empty value indicates automated submission.
        if (!empty($payload['website'])) {
            log_structured('warning', 'public_booking.honeypot_triggered', [
                'honeypot_value_length' => strlen((string) ($payload['website'])),
            ]);
            // Return a fake success to confuse bots without revealing detection.
            throw new PublicBookingException('Unable to complete booking at this time.', 422, []);
        }

        $provider = $this->resolveProviderFromPayload($payload);
        $service = $this->resolveService((int) ($payload['service_id'] ?? 0));

        // Defense-in-depth: confirm the provider actually offers this service before
        // proceeding to slot/customer resolution or delegating to AppointmentBookingService.
        if (!$this->directory->providerOffersService((int) $provider['id'], (int) $service['id'])) {
            log_structured('warning', 'public_booking.provider_service_mismatch', [
                'provider_id' => (int) $provider['id'],
                'service_id'  => (int) $service['id'],
            ]);
            throw new PublicBookingException(
                'Selected provider does not offer this service.',
                422,
                ['service_id' => 'provider_mismatch']
            );
        }

        $slot = $this->normalizeSlotPayload($payload, $service['duration_min']);
        $locationId = $this->resolveBookingLocation($provider['id'], $payload['location_id'] ?? null);

        $fieldConfig = $this->bookingSettings->getFieldConfiguration();
        $customConfig = $this->bookingSettings->getCustomFieldConfiguration();
        $customerFields = $this->inputSanitizer->extractCustomerFields($payload, $fieldConfig, $customConfig);
        $customFieldValues = $this->inputSanitizer->extractAppointmentCustomFieldValues($payload, $customConfig, true);

        $token = $this->generateToken();

        $bookingPayload = [
            'provider_id' => $provider['id'],
            'service_id' => $service['id'],
            'location_id' => $locationId,
            'appointment_date' => $slot['date'],
            'appointment_time' => $slot['start']->format('H:i'),
            'notes' => $this->inputSanitizer->sanitizeString($payload['notes'] ?? null, 1000),
            'delivery_mode' => in_array($payload['delivery_mode'] ?? '', \App\Services\VideoSessionService::VALID_MODES, true)
                ? $payload['delivery_mode']
                : 'onsite',
            'public_token' => $token,
            'public_token_expires_at' => null,
            'notification_types' => ['email', 'whatsapp'],
            'customer_first_name' => $customerFields['first_name'] ?? '',
            'customer_last_name' => $customerFields['last_name'] ?? '',
            'customer_email' => $customerFields['email'] ?? '',
            'customer_phone' => $customerFields['phone'] ?? '',
            'customer_address' => $customerFields['address'] ?? '',
            'customer_notes' => $customerFields['notes'] ?? '',
            'booking_channel' => 'public',
        ];

        // Payment-gate: force 'pending' regardless of admin default when deposit is due.
        // Appointment only transitions pending→confirmed when PayFast/Stripe ITN is received.
        if ((bool) ($service['payment_enabled'] ?? false)) {
            $pmeta = (new \App\Services\Payment\PaymentService())->servicePaymentMeta($service, 1);
            if (($pmeta['payment_required'] ?? false)
                && (($pmeta['payfast_available'] ?? false) || ($pmeta['stripe_available'] ?? false))) {
                $bookingPayload['status'] = \App\Services\Appointment\AppointmentStatus::PENDING;
            }
        }

        $result = $this->bookingService->createAppointment($bookingPayload, $this->localization->getTimezone());
        if (!$result['success']) {
            throw new PublicBookingException(
                $result['message'] ?? 'Unable to create appointment at this time.',
                $this->resolveBookingFailureStatus($result),
                $this->resolveBookingFailureErrors($result)
            );
        }

        $appointmentId = (int) ($result['appointmentId'] ?? 0);
        $appointment = $this->appointments->find($appointmentId);

        if (is_array($appointment)) {
            $customerId = (int) ($appointment['customer_id'] ?? 0);
            if ($customerId > 0 && $customFieldValues !== []) {
                $this->customerCustomFieldService->mergeForCustomer($customerId, $customFieldValues, []);
            }
        }

        return array_merge(
            ['appointment_id' => $appointmentId],
            $this->formatPublicAppointment($appointment, $token)
        );
    }

    public function lookupAppointment(string $token, ?string $email = null, ?string $phone = null, ?string $phoneCountryCode = null): array
    {
        $appointment = $this->fetchAppointmentByReference($token);
        $this->verifyContactAccess($appointment, $email, $phone, $phoneCountryCode);
        return $this->formatPublicAppointment($appointment, $token);
    }

    /**
     * Look up all non-cancelled appointments for a customer identified by email or phone.
     * Returns a lightweight summary list for selection. Contact is verified via PHP-side normalization.
     */
    public function lookupAppointmentsByContact(?string $email, ?string $phone, ?string $phoneCountryCode = null): array
    {
        $email = $email ? strtolower(trim($email)) : null;
        $phone = $phone ? $this->inputSanitizer->normalizePhone($phone, $phoneCountryCode) : null;

        if (!$email && !$phone) {
            throw new PublicBookingException(
                'Please provide your email or phone number.',
                422,
                ['contact' => 'required']
            );
        }

        $builder = $this->appointments->builder();
        $builder->select('xs_appointments.*, c.email as customer_email, c.phone as customer_phone, c.id as customer_id', false)
            ->join('xs_customers as c', 'c.id = xs_appointments.customer_id', 'left')
            ->whereIn('xs_appointments.status', AppointmentStatus::UPCOMING);

        // SQL pre-filter (broad): narrow result set before PHP-level normalization pass
        $builder->groupStart();
        if ($email) {
            $builder->where('LOWER(c.email)', $email);
        }
        if ($email && $phone) {
            $builder->orWhere('c.phone', $phone);
        } elseif ($phone) {
            $builder->where('c.phone', $phone);
        }
        $builder->groupEnd();

        $builder->orderBy('xs_appointments.start_at', 'ASC');
        $records = $builder->get()->getResultArray();

        // PHP post-filter: precise normalized match (handles phone formatting differences)
        $records = array_values(array_filter($records, function (array $row) use ($email, $phone): bool {
            if ($email && strtolower((string) ($row['customer_email'] ?? '')) === $email) {
                return true;
            }
            if ($phone && $this->inputSanitizer->normalizePhone($row['customer_phone'] ?? null, null) === $phone) {
                return true;
            }
            return false;
        }));

        return array_map(fn(array $row) => $this->formatAppointmentSummary($row), $records);
    }

    public function reschedule(string $token, array $payload): array
    {
        $appointment = $this->fetchAppointmentByReference($token);
        $this->verifyContactAccess(
            $appointment,
            $payload['email'] ?? null,
            $payload['phone'] ?? null,
            $payload['phone_country_code'] ?? null
        );
        $this->policy->assertRescheduleWindow($appointment['start_at']);

        $providerId = (int) ($payload['provider_id'] ?? $appointment['provider_id']);
        $serviceId = (int) ($payload['service_id'] ?? $appointment['service_id']);

        $providerPayload = [
            'provider_id' => $providerId,
            'provider_slug' => $payload['provider_slug'] ?? null,
        ];
        $provider = $this->resolveProviderFromPayload($providerPayload);
        $service = $this->resolveService($serviceId);
        $slot = $this->normalizeSlotPayload($payload, $service['duration_min']);
        $newLocationId = $this->resolveBookingLocation($provider['id'], $payload['location_id'] ?? ($appointment['location_id'] ?? null));

        $customerId = $this->storeCustomer($payload, (int) $appointment['customer_id']);
        $newToken = $this->generateToken();
        $customConfig = $this->bookingSettings->getCustomFieldConfiguration();
        $existingCustomFieldValues = $this->customerCustomFieldService->getForCustomer($customerId);
        $customFieldValues = $this->inputSanitizer->extractAppointmentCustomFieldValues($payload, $customConfig, true, $existingCustomFieldValues);
        $clearFlags = $this->inputSanitizer->extractAppointmentCustomFieldClearFlags($payload, $customConfig);

        $updatePayload = [
            'provider_id' => $provider['id'],
            'service_id' => $service['id'],
            'appointment_date' => $slot['date'],
            'appointment_time' => $slot['start']->format('H:i'),
            'notes' => $this->inputSanitizer->sanitizeString($payload['notes'] ?? $appointment['notes'] ?? null, 1000),
            'public_token' => $newToken,
            'public_token_expires_at' => null,
            'customer_id' => $customerId,
            'location_id' => $newLocationId,
            'booking_channel' => 'public',
        ];

        $db = \Config\Database::connect();
        $db->transStart();

        $result = $this->bookingService->updateAppointment(
            (int) $appointment['id'],
            $updatePayload,
            $this->localization->getTimezone(),
            'appointment_rescheduled',
            ['email', 'whatsapp']
        );

        if (!$result['success']) {
            $db->transRollback();
            throw new PublicBookingException(
                $result['message'] ?? 'Unable to reschedule appointment. Please try again later.',
                $this->resolveBookingFailureStatus($result),
                $this->resolveBookingFailureErrors($result)
            );
        }

        $this->customerCustomFieldService->mergeForCustomer($customerId, $customFieldValues, $clearFlags);

        $db->transComplete();
        if (!$db->transStatus()) {
            throw new PublicBookingException('Unable to save appointment updates. Please try again later.', 422, ['appointment' => 'update_failed']);
        }

        $updated = $this->appointments->find((int) $appointment['id']);
        return $this->formatPublicAppointment($updated, $newToken);
    }

    public function cancel(string $token, array $payload): array
    {
        $appointment = $this->fetchAppointmentByReference($token);
        $this->verifyContactAccess(
            $appointment,
            $payload['email'] ?? null,
            $payload['phone'] ?? null,
            $payload['phone_country_code'] ?? null
        );
        $this->policy->assertCancellationWindow($appointment);

        $updatePayload = [
            'status' => AppointmentStatus::CANCELLED,
            'booking_channel' => 'public',
        ];

        if (array_key_exists('cancel_reason', $payload)) {
            $updatePayload['notes'] = $this->inputSanitizer->sanitizeString($payload['cancel_reason'], 1000);
        }

        $result = $this->bookingService->updateAppointment(
            (int) $appointment['id'],
            $updatePayload,
            $this->localization->getTimezone(),
            'appointment_cancelled',
            ['email', 'whatsapp']
        );

        if (!$result['success']) {
            throw new PublicBookingException(
                $result['message'] ?? 'Unable to cancel appointment. Please try again later.',
                $this->resolveBookingFailureStatus($result),
                $this->resolveBookingFailureErrors($result)
            );
        }

        $updated = $this->appointments->find((int) $appointment['id']);
        return $this->formatPublicAppointment($updated, $token);
    }

    /**
     * Resolve public provider identity by slug first (preferred), with id fallback
     * for backward compatibility during migration.
     */
    public function resolvePublicProviderId(?string $providerSlug = null, ?int $providerId = null): int
    {
        $slug = trim((string) $providerSlug);
        if ($slug !== '') {
            $provider = $this->directory->findProviderBySlug($slug);
            if (!$provider) {
                throw new PublicBookingException('Selected provider is unavailable.', 404, ['provider_slug' => 'invalid']);
            }

            return (int) ($provider['id'] ?? 0);
        }

        $resolved = $this->resolveProvider((int) $providerId);
        return (int) ($resolved['id'] ?? 0);
    }

    private function resolveProviderFromPayload(array $payload): array
    {
        $slug = trim((string) ($payload['provider_slug'] ?? ''));
        if ($slug !== '') {
            $provider = $this->directory->findProviderBySlug($slug);
            if (!$provider) {
                throw new PublicBookingException('Selected provider is unavailable.', 404, ['provider_slug' => 'invalid']);
            }

            return $provider;
        }

        return $this->resolveProvider((int) ($payload['provider_id'] ?? 0));
    }

    private function resolveProvider(int $providerId): array
    {
        if ($providerId <= 0) {
            throw new PublicBookingException('Provider selection is required.', 422, ['provider_id' => 'required']);
        }

        $builder = $this->users->where('id', $providerId)->whereHasRole('provider');
        UserModel::applyActiveFilter($builder);

        $provider = $builder->first();
        if (!$provider) {
            throw new PublicBookingException('Selected provider is unavailable.', 404);
        }

        return $provider;
    }

    private function resolveBookingLocation(int $providerId, $requestedLocationId): ?int
    {
        $activeLocations = $this->locations->getProviderLocations($providerId, true);
        if (empty($activeLocations)) {
            return null;
        }

        $activeLocationIds = array_map(static fn(array $loc): int => (int) ($loc['id'] ?? 0), $activeLocations);
        $selectedLocationId = !empty($requestedLocationId) ? (int) $requestedLocationId : null;

        if ($selectedLocationId !== null) {
            if (!in_array($selectedLocationId, $activeLocationIds, true)) {
                throw new PublicBookingException('Selected location is unavailable for this provider.', 422, ['location_id' => 'invalid']);
            }

            return $selectedLocationId;
        }

        throw new PublicBookingException('Please select a location for this provider.', 422, ['location_id' => 'required']);
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
        $rawStart = $payload['slot_start'] ?? $payload['start_time'] ?? $payload['start_at'] ?? null;

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

    private function resolveBookingFailureStatus(array $result): int
    {
        if (!empty($result['conflicts'])) {
            return 409;
        }

        if (!empty($result['errors']) || !empty($result['validationErrors'])) {
            return 422;
        }

        return 400;
    }

    private function resolveBookingFailureErrors(array $result): array
    {
        $errors = $result['errors'] ?? [];

        if (empty($errors) && !empty($result['validationErrors']) && is_array($result['validationErrors'])) {
            $errors = $result['validationErrors'];
        }

        if (!empty($result['conflicts']) && empty($errors['slot_start'])) {
            $errors['slot_start'] = 'unavailable';
        }

        return is_array($errors) ? $errors : [];
    }

    private function storeCustomer(array $payload, ?int $existingId = null): int
    {
        $fieldConfig = $this->bookingSettings->getFieldConfiguration();
        $customConfig = $this->bookingSettings->getCustomFieldConfiguration();
        $data = $this->inputSanitizer->extractCustomerFields($payload, $fieldConfig, $customConfig);

        try {
            // preserveNames: a public booking with a shared email but a
            // different name (parent booking for their child) must not rename
            // the stored customer record.
            $upsert = $this->customerService->upsertCustomer(array_merge($data, [
                'customer_id' => $existingId,
                'phone_country_code' => $payload['phone_country_code'] ?? null,
            ]), ['preserveNames' => true]);
        } catch (\InvalidArgumentException $e) {
            throw new PublicBookingException($e->getMessage(), 422, ['phone' => 'invalid']);
        } catch (\Throwable $e) {
            throw new PublicBookingException('Unable to save customer information.');
        }

        return (int) ($upsert['id'] ?? 0);
    }

    /**
     * Lightweight appointment summary used in search results (pre-selection list).
     * Does not include full customer data — only what is needed for the selection UI.
     */
    private function formatAppointmentSummary(array $appointment): array
    {
        $timezone = new DateTimeZone($this->localization->getTimezone());
        $utcTz    = new DateTimeZone('UTC');
        $start    = (new DateTimeImmutable($appointment['start_at'], $utcTz))->setTimezone($timezone);
        $end      = (new DateTimeImmutable($appointment['end_at'],   $utcTz))->setTimezone($timezone);
        $provider = $this->users->find((int) $appointment['provider_id']);
        $service  = $this->services->find((int) $appointment['service_id']);

        return [
            'token'         => $appointment['hash'],
            'start'         => $start->format(DATE_ATOM),
            'end'           => $end->format(DATE_ATOM),
            'status'        => $appointment['status'] ?? 'pending',
            'display_range' => $this->formatSlotRange($start, $end),
            'provider'      => $provider ? [
                'id'   => (int) $provider['id'],
                'slug' => $provider['slug'] ?? null,
                'name' => $provider['name'] ?? 'Provider',
            ] : null,
            'service'       => $service ? [
                'id'   => (int) $service['id'],
                'name' => $service['name'] ?? 'Service',
            ] : null,
        ];
    }

    private function formatPublicAppointment(?array $appointment, string $reference): array
    {
        if (!$appointment) {
            throw new PublicBookingException('Appointment could not be loaded.');
        }

        $timezone = new DateTimeZone($this->localization->getTimezone());
        // DB stores UTC — parse as UTC, convert to local for display
        $utcTz = new DateTimeZone('UTC');
        $start = (new DateTimeImmutable($appointment['start_at'], $utcTz))->setTimezone($timezone);
        $end = (new DateTimeImmutable($appointment['end_at'], $utcTz))->setTimezone($timezone);
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
            'reference' => $reference,
            'provider_id' => (int) $appointment['provider_id'],
            'provider_slug' => $provider['slug'] ?? null,
            'service_id' => (int) $appointment['service_id'],
            'start' => $start->format(DATE_ATOM),
            'end' => $end->format(DATE_ATOM),
            'status' => $appointment['status'] ?? 'pending',
            'notes' => $appointment['notes'] ?? null,
            'timezone' => $timezone->getName(),
            'display_range' => $this->formatSlotRange($start, $end),
            'provider' => $provider ? [
                'id' => (int) $provider['id'],
                'slug' => $provider['slug'] ?? null,
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
            'custom_fields' => $this->buildPublicCustomFieldPayload($customer['custom_fields'] ?? null),
            // Reschedule eligibility — computed server-side so the SPA can gate the form
            'can_reschedule' => $this->policy->canReschedule($appointment['start_at']),
            // Cancellation eligibility — computed server-side so the SPA can gate cancel action
            'can_cancel'     => $this->policy->canCancel($appointment),
            'delivery_mode'  => (string) ($appointment['delivery_mode'] ?? 'onsite'),
            'video_link'     => (string) ($appointment['video_link'] ?? ''),
        ];
    }

    private function formatSlotRange(DateTimeImmutable $start, DateTimeImmutable $end): string
    {
        $dateLabel  = $start->format('D, M j');
        $startLabel = $this->localization->formatTimeForDisplay($start->format('H:i:s'));
        $endLabel   = $this->localization->formatTimeForDisplay($end->format('H:i:s'));
        return sprintf('%s at %s – %s', $dateLabel, $startLabel, $endLabel);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildPublicCustomFieldPayload(array|string|null $storedValues): array
    {
        $config = $this->bookingSettings->getCustomFieldConfiguration();
        return $this->customerCustomFieldService->buildPublicPayload($config, $storedValues);
    }

    private function fetchAppointmentByReference(string $reference): array
    {
        if (trim($reference) === '') {
            throw new PublicBookingException('Booking reference is required.', 422, ['token' => 'required']);
        }

        $builder = $this->appointments->builder();
        $builder->select('xs_appointments.*, c.email as customer_email, c.phone as customer_phone, c.id as customer_id', false)
            ->join('xs_customers as c', 'c.id = xs_appointments.customer_id', 'left')
            ->groupStart()
                ->where('xs_appointments.public_token', $reference)
                ->orWhere('xs_appointments.hash', $reference)
            ->groupEnd();

        $record = $builder->get()->getRowArray();
        if (!$record) {
            throw new PublicBookingException('We could not find a booking for that reference.', 404);
        }

        return $record;
    }

    private function verifyContactAccess(array $appointment, ?string $email, ?string $phone, ?string $phoneCountryCode = null): void
    {
        helper('logging');

        $expectedEmail = strtolower(trim((string) ($appointment['customer_email'] ?? '')));
        $expectedPhone = $this->inputSanitizer->normalizePhone($appointment['customer_phone'] ?? null, null);
        $providedEmail = $email ? strtolower(trim($email)) : null;
        $providedPhone = $this->inputSanitizer->normalizePhone($phone, $phoneCountryCode);

        $tokenPrefix = substr((string) ($appointment['public_token'] ?? $appointment['hash'] ?? ''), 0, 8);

        if ($expectedEmail === '' || $expectedPhone === null) {
            log_structured('warning', 'public_booking.verification_mismatch', [
                'token_prefix' => $tokenPrefix,
                'reason' => 'stored_contact_incomplete',
            ]);
            throw new PublicBookingException('Contact verification failed.', 403, ['contact' => 'mismatch']);
        }

        if ($providedEmail === null || $providedPhone === null) {
            log_structured('warning', 'public_booking.verification_mismatch', [
                'token_prefix' => $tokenPrefix,
                'reason' => 'missing_contact_input',
                'provided_email' => $providedEmail !== null,
                'provided_phone' => $providedPhone !== null,
            ]);
            throw new PublicBookingException('Contact verification failed.', 403, ['contact' => 'mismatch']);
        }

        if ($providedEmail !== $expectedEmail || $providedPhone !== $expectedPhone) {
            log_structured('warning', 'public_booking.verification_mismatch', [
                'token_prefix' => $tokenPrefix,
                'reason' => 'email_or_phone_mismatch',
                'email_match' => $providedEmail === $expectedEmail,
                'phone_match' => $providedPhone === $expectedPhone,
            ]);
            throw new PublicBookingException('Contact verification failed.', 403, ['contact' => 'mismatch']);
        }

        log_structured('info', 'public_booking.verification_success', [
            'token_prefix' => $tokenPrefix,
        ]);
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
