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

    /** @var array<string, bool> */
    private array $columnExistsCache = [];

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
        $this->bookingService = $bookingService ?? new AppointmentBookingService();
        $this->phoneNumberService = new PhoneNumberService($this->settings);
    }

    public function buildViewContext(): array
    {
        $providers = $this->listProviders();
        $initialProviderId = !empty($providers) ? (int) ($providers[0]['id'] ?? 0) : null;
        $services = $initialProviderId ? $this->listServices($initialProviderId) : [];

        return [
            'providers' => $this->sanitizeProvidersForPublic($providers),
            'services' => $services,
            'fieldConfig' => $this->bookingSettings->getFieldConfiguration(),
            'customFieldConfig' => $this->bookingSettings->getCustomFieldConfiguration(),
            'timezone' => $this->localization->getTimezone(),
            'timeFormat' => $this->localization->getTimeFormat(),
            'currency' => $this->localization->getCurrency(),
            'currencySymbol' => $this->localization->getCurrencySymbol(),
            'reschedulePolicy' => $this->getReschedulePolicy(),
            'cancelPolicy' => $this->getCancelPolicy(),
            'appBaseUrl' => rtrim(base_url(), '/'),
            'bookingBaseUrl' => rtrim(base_url('booking'), '/'),
            'logoUrl' => function_exists('setting_url') ? setting_url('general.company_logo', 'assets/settings/default-logo.svg') : base_url('assets/settings/default-logo.svg'),
            'businessName' => function_exists('setting') ? (setting('general.company_name', 'WebSchedulr') ?: 'WebSchedulr') : 'WebSchedulr',
            'defaultPhoneCountryCode' => $this->phoneNumberService->getDefaultCountryCode(),
            'seo' => $this->buildSeoContext(),
        ];
    }

    public function buildViewContextForProviderSlug(string $slug): array
    {
        $provider = $this->findProviderBySlug($slug);
        if (!$provider) {
            throw new PublicBookingException('Provider not found.', 404);
        }

        $providerId = (int) $provider['id'];
        $providers = $this->listProviders();
        usort($providers, static function (array $a, array $b) use ($providerId): int {
            if ((int) $a['id'] === $providerId) {
                return -1;
            }
            if ((int) $b['id'] === $providerId) {
                return 1;
            }
            return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

        $services = $this->listServices($providerId);
        $context = $this->buildViewContext();
        $context['providers'] = $this->sanitizeProvidersForPublic($providers);
        $context['services'] = $services;
        $context['initialFilter'] = [
            'providerSlug' => $slug,
        ];
        $context['seo'] = $this->buildSeoContextForProvider($provider);

        return $context;
    }

    public function buildViewContextForServiceSlug(string $serviceSlug, ?string $city = null): array
    {
        $service = $this->findServiceBySlug($serviceSlug);
        if (!$service) {
            throw new PublicBookingException('Service not found.', 404);
        }

        $serviceId = (int) $service['id'];
        $providers = $this->listProviders($serviceId, $city);
        $initialProviderId = !empty($providers) ? (int) $providers[0]['id'] : null;

        $services = [];
        if ($initialProviderId !== null) {
            $providerServices = $this->listServices($initialProviderId);
            foreach ($providerServices as $providerService) {
                if ((int) ($providerService['id'] ?? 0) === $serviceId) {
                    $services[] = $providerService;
                    break;
                }
            }
        }

        if ($services === []) {
            $services[] = [
                'id' => $serviceId,
                'name' => $service['name'] ?? 'Service',
                'slug' => $service['slug'] ?? null,
                'duration' => (int) ($service['duration_min'] ?? 30),
                'price' => (float) ($service['price'] ?? 0),
                'formattedPrice' => $this->localization->formatCurrency($service['price'] ?? 0),
            ];
        }

        $context = $this->buildViewContext();
        $context['providers'] = $this->sanitizeProvidersForPublic($providers);
        $context['services'] = $services;
        $context['initialFilter'] = [
            'serviceSlug' => $serviceSlug,
            'serviceId' => $serviceId,
            'city' => $city,
        ];
        $context['seo'] = $this->buildSeoContextForService($service, $providers, $city);

        return $context;
    }

    public function discoverByServiceAndLocation(string $serviceQuery, ?string $city = null, ?string $area = null): array
    {
        $service = $this->resolveServiceQuery($serviceQuery);
        if (!$service) {
            throw new PublicBookingException('No matching service found.', 404, ['service' => 'not_found']);
        }

        $serviceId = (int) ($service['id'] ?? 0);
        $serviceSlug = (string) ($service['slug'] ?? '');
        $locationNeedle = $this->normalizeLocationNeedle($city, $area);

        $providers = $this->listProviders($serviceId, $city, $area);

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
            $context['providers'] = $this->sanitizeProvidersForPublic($providers);
            $context['initialFilter'] = [
                'serviceSlug' => $serviceSlug,
                'serviceId' => $serviceId,
                'city' => $locationNeedle,
            ];
            $context['seo'] = $this->buildSeoContextForService($service, $providers, $locationNeedle);
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
            'providers' => $this->sanitizeProvidersForPublic($providers),
            'locations' => $flatLocations,
            'canonical' => $canonical,
            'context' => $context,
        ];
    }

    /**
     * Build sitemap URLs for public booking pages.
     *
     * @return array<int, array{loc: string, lastmod: string, changefreq: string, priority: string}>
     */
    public function getSitemapUrls(): array
    {
        $urls = [];
        $seen = [];

        $addUrl = static function (string $loc, ?string $lastmod = null, string $changefreq = 'weekly', string $priority = '0.7') use (&$urls, &$seen): void {
            $loc = trim($loc);
            if ($loc === '' || isset($seen[$loc])) {
                return;
            }

            $seen[$loc] = true;
            $urls[] = [
                'loc' => $loc,
                'lastmod' => $lastmod ?: gmdate('c'),
                'changefreq' => $changefreq,
                'priority' => $priority,
            ];
        };

        $addUrl(base_url('booking'), null, 'daily', '1.0');

        $providerBuilder = $this->users->where('role', 'provider');
        if ($this->hasUsersColumn('is_active')) {
            $providerBuilder->where('is_active', true);
        } elseif ($this->hasUsersColumn('status')) {
            $providerBuilder->where('status', 'active');
        }

        $providerRows = $providerBuilder->findAll();
        foreach ($providerRows as $provider) {
            $slug = trim((string) ($provider['slug'] ?? ''));
            if ($slug === '') {
                continue;
            }

            $updated = $this->normalizeIsoDate((string) ($provider['updated_at'] ?? ''));
            $addUrl(base_url('booking/p/' . rawurlencode($slug)), $updated, 'weekly', '0.8');
        }

        $serviceRows = $this->services->where('active', 1)->findAll();
        foreach ($serviceRows as $service) {
            $serviceId = (int) ($service['id'] ?? 0);
            $serviceSlug = trim((string) ($service['slug'] ?? ''));
            if ($serviceId <= 0 || $serviceSlug === '') {
                continue;
            }

            $updated = $this->normalizeIsoDate((string) ($service['updated_at'] ?? ''));
            $addUrl(base_url('booking/s/' . rawurlencode($serviceSlug)), $updated, 'weekly', '0.9');

            $providers = $this->listProviders($serviceId);
            foreach ($providers as $provider) {
                foreach (($provider['locations'] ?? []) as $location) {
                    $segment = $this->buildLocationSegment(
                        $location['city'] ?? null,
                        $location['area'] ?? null
                    );
                    if ($segment === null) {
                        continue;
                    }

                    $addUrl(base_url('booking/s/' . rawurlencode($serviceSlug) . '/' . rawurlencode($segment)), $updated, 'weekly', '0.7');
                }
            }
        }

        return $urls;
    }

    /**
     * Build SEO context for the public booking page.
     *
     * Sources all values from xs_settings (general.* keys) so that the
     * booking view can render server-side meta tags without any business logic.
     *
     * @return array{title: string, description: string, canonical: string, image: string, robots: string, schema: array}
     */
    public function buildSeoContext(): array
    {
        $keys = [
            'general.business_name',
            'general.company_name',
            'general.business_address',
            'general.city',
            'general.telephone_number',
            'general.mobile_number',
            'general.company_phone',
            'general.meta_description',
            'general.meta_image',
            'booking.indexable',
        ];

        $s = $this->settings->getByKeys($keys);

        $businessName = $s['general.business_name']
            ?? $s['general.company_name']
            ?? 'WebSchedulr';

        $address  = $s['general.business_address'] ?? '';
        $city     = $s['general.city'] ?? '';
        $phone    = $s['general.telephone_number']
            ?? $s['general.mobile_number']
            ?? $s['general.company_phone']
            ?? '';

        $metaDescription = $s['general.meta_description'] ?? '';
        if ($metaDescription === '') {
            $metaDescription = 'Book your appointment online at ' . $businessName . '.';
            if ($city !== '') {
                $metaDescription .= ' Located in ' . $city . '.';
            }
        }

        $metaImage  = $s['general.meta_image'] ?? '';
        if ($metaImage === '') {
            $metaImage = base_url('assets/og-default.png');
        }

        $indexable = isset($s['booking.indexable']) ? (bool) $s['booking.indexable'] : true;

        $businessUrl = base_url('booking');
        $businessId = rtrim($businessUrl, '/') . '#business';

        $businessSchema = [
            '@type'    => 'LocalBusiness',
            '@id'      => $businessId,
            'name'     => $businessName,
            'url'      => $businessUrl,
        ];

        if ($address !== '') {
            $businessSchema['address'] = [
                '@type'           => 'PostalAddress',
                'streetAddress'   => $address,
                'addressLocality' => $city,
            ];
        } elseif ($city !== '') {
            $businessSchema['address'] = [
                '@type'           => 'PostalAddress',
                'addressLocality' => $city,
            ];
        }

        if ($phone !== '') {
            $businessSchema['telephone'] = $phone;
        }

        if ($metaImage !== '' && strpos($metaImage, 'og-default.png') === false) {
            $businessSchema['image'] = $metaImage;
        }

        $providerNodes = [];
        $employeeRefs = [];
        foreach ($this->listProviders() as $provider) {
            $slug = trim((string) ($provider['slug'] ?? ''));
            if ($slug === '') {
                continue;
            }

            $providerUrl = base_url('booking/p/' . rawurlencode($slug));
            $providerId = rtrim($providerUrl, '/') . '#person';
            $providerNode = [
                '@type' => 'Person',
                '@id' => $providerId,
                'name' => $provider['name'] ?? 'Provider',
                'url' => $providerUrl,
                'worksFor' => [
                    '@id' => $businessId,
                ],
            ];

            if (!empty($provider['title'])) {
                $providerNode['jobTitle'] = $provider['title'];
            }
            if (!empty($provider['profile_image_url'])) {
                $providerNode['image'] = $provider['profile_image_url'];
            }

            $providerNodes[] = $providerNode;
            $employeeRefs[] = ['@id' => $providerId];
        }

        if ($employeeRefs !== []) {
            $businessSchema['employee'] = $employeeRefs;
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@graph' => array_merge([$businessSchema], $providerNodes),
        ];

        return [
            'title'       => 'Book at ' . $businessName,
            'description' => $metaDescription,
            'canonical'   => base_url('booking'),
            'image'       => $metaImage,
            'robots'      => $indexable ? 'index, follow' : 'noindex, nofollow',
            'schema'      => $schema,
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
        $provider = $this->resolveProviderFromPayload($payload);
        $service = $this->resolveService((int) ($payload['service_id'] ?? 0));
        $slot = $this->normalizeSlotPayload($payload, $service['duration_min']);
        $locationId = $this->resolveBookingLocation($provider['id'], $payload['location_id'] ?? null);

        $fieldConfig = $this->bookingSettings->getFieldConfiguration();
        $customConfig = $this->bookingSettings->getCustomFieldConfiguration();
        $customerFields = $this->extractCustomerFields($payload, $fieldConfig, $customConfig);

        $token = $this->generateToken();

        $bookingPayload = [
            'provider_id' => $provider['id'],
            'service_id' => $service['id'],
            'location_id' => $locationId,
            'appointment_date' => $slot['date'],
            'appointment_time' => $slot['start']->format('H:i'),
            'notes' => $this->sanitizeString($payload['notes'] ?? null, 1000),
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

        if (!empty($customerFields['custom_fields'])) {
            $decoded = json_decode($customerFields['custom_fields'], true) ?: [];
            foreach ($decoded as $key => $value) {
                $bookingPayload[$key] = $value;
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

        $appointment = $this->appointments->find((int) $result['appointmentId']);
        return $this->formatPublicAppointment($appointment, $token);
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
        $phone = $phone ? $this->normalizePhone($phone, $phoneCountryCode) : null;

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
            if ($phone && $this->normalizePhone($row['customer_phone'] ?? null, null) === $phone) {
                return true;
            }
            return false;
        }));

        return array_map(fn(array $row) => $this->formatAppointmentSummary($row), $records);
    }

    public function reschedule(string $token, array $payload): array
    {
        $appointment = $this->fetchAppointmentByReference($token);
        $this->verifyContactAccess($appointment, $payload['email'] ?? null, $payload['phone'] ?? null);
        $this->assertRescheduleWindow($appointment['start_at']);

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

        $updatePayload = [
            'provider_id' => $provider['id'],
            'service_id' => $service['id'],
            'appointment_date' => $slot['date'],
            'appointment_time' => $slot['start']->format('H:i'),
            'notes' => $this->sanitizeString($payload['notes'] ?? $appointment['notes'] ?? null, 1000),
            'public_token' => $newToken,
            'public_token_expires_at' => null,
            'customer_id' => $customerId,
            'location_id' => $newLocationId,
            'booking_channel' => 'public',
        ];

        $result = $this->bookingService->updateAppointment(
            (int) $appointment['id'],
            $updatePayload,
            $this->localization->getTimezone(),
            'appointment_rescheduled',
            ['email', 'whatsapp']
        );

        if (!$result['success']) {
            throw new PublicBookingException(
                $result['message'] ?? 'Unable to reschedule appointment. Please try again later.',
                $this->resolveBookingFailureStatus($result),
                $this->resolveBookingFailureErrors($result)
            );
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
        $this->assertCancellationWindow($appointment);

        $updatePayload = [
            'status' => AppointmentStatus::CANCELLED,
            'booking_channel' => 'public',
        ];

        if (array_key_exists('cancel_reason', $payload)) {
            $updatePayload['notes'] = $this->sanitizeString($payload['cancel_reason'], 1000);
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

    private function listProviders(?int $serviceId = null, ?string $city = null, ?string $area = null): array
    {
        $rows = $this->users->getProvidersWithActiveServices();
        $cityNeedle = $city ? strtolower(trim($city)) : null;
        $areaNeedle = $area ? strtolower(trim($area)) : null;

        $mapped = array_map(function (array $row) use ($serviceId, $cityNeedle, $areaNeedle): ?array {
            $providerId = (int) $row['id'];
            if ($serviceId !== null && !$this->providerOffersService($providerId, $serviceId)) {
                return null;
            }

            $locations = $this->locations->getProviderLocationsWithDays($providerId);

            if ($cityNeedle !== null || $areaNeedle !== null) {
                $locations = array_values(array_filter($locations, static function (array $loc) use ($cityNeedle, $areaNeedle): bool {
                    $cityText = strtolower(trim((string) ($loc['city'] ?? '')));
                    $areaText = strtolower(trim((string) ($loc['area'] ?? '')));
                    $addressText = strtolower(trim((string) ($loc['address'] ?? '')));

                    $cityMatch = true;
                    if ($cityNeedle !== null) {
                        $cityMatch = ($cityText !== '' && str_contains($cityText, $cityNeedle))
                            || ($addressText !== '' && str_contains($addressText, $cityNeedle));
                    }

                    $areaMatch = true;
                    if ($areaNeedle !== null) {
                        $areaMatch = ($areaText !== '' && str_contains($areaText, $areaNeedle))
                            || ($addressText !== '' && str_contains($addressText, $areaNeedle));
                    }

                    return $cityMatch && $areaMatch;
                }));
            }
            
            // Check if provider has locations configured
            $hasLocations = !empty($locations);

            if (($cityNeedle !== null || $areaNeedle !== null) && !$hasLocations) {
                return null;
            }
            
            return [
                'id' => $providerId,
                'name' => $row['name'] ?? 'Provider',
                'slug' => $row['slug'] ?? null,
                'title' => $this->nullableString($row['title'] ?? null),
                'bio' => $this->nullableString($row['bio'] ?? null),
                'education' => $this->nullableString($row['education'] ?? null),
                'qualifications' => $this->nullableString($row['qualifications'] ?? null),
                'profile_image_url' => $this->providerImageUrl($row['profile_image'] ?? null),
                'color' => $row['color'] ?? '#3B82F6',
                'has_locations' => $hasLocations,
                'locations' => array_map(static function (array $loc): array {
                    return [
                        'id' => (int) $loc['id'],
                        'name' => $loc['name'],
                        // Address and contact only shown after selection
                        'address' => $loc['address'],
                        'city' => $loc['city'] ?? null,
                        'area' => $loc['area'] ?? null,
                        'contact_number' => $loc['contact_number'],
                        'is_primary' => (bool) ($loc['is_primary'] ?? false),
                        'days' => $loc['days'] ?? [],
                    ];
                }, $locations),
            ];

        }, $rows);

        return array_values(array_filter($mapped, static fn($row) => $row !== null));
    }

    private function listServices(?int $providerId = null): array
    {
        $rows = $providerId ? $this->services->getActiveByProvider($providerId) : $this->services->where('active', 1)->orderBy('name', 'ASC')->findAll();
        return array_map(function (array $row): array {
            $price = $row['price'] ?? 0;
            return [
                'id' => (int) $row['id'],
                'name' => $row['name'] ?? 'Service',
                'slug' => $row['slug'] ?? null,
                'duration' => (int) ($row['duration_min'] ?? 30),
                'price' => (float) $price,
                'formattedPrice' => $this->localization->formatCurrency($price),
            ];
        }, $rows);
    }

    private function buildSeoContextForProvider(array $provider): array
    {
        $businessName = function_exists('setting') ? (setting('general.company_name', 'WebSchedulr') ?: 'WebSchedulr') : 'WebSchedulr';
        $providerName = trim((string) ($provider['name'] ?? 'Provider'));
        $titlePrefix = trim((string) ($provider['title'] ?? ''));
        $displayName = trim($titlePrefix . ' ' . $providerName);
        $description = $this->nullableString($provider['bio'] ?? null);
        if ($description === null) {
            $description = sprintf('Book an appointment with %s at %s.', $displayName, $businessName);
        }

        $slug = (string) ($provider['slug'] ?? '');
        $canonical = base_url('booking/p/' . rawurlencode($slug));
        $image = $this->providerImageUrl($provider['profile_image'] ?? null);

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Person',
            'name' => $displayName,
            'url' => $canonical,
            'worksFor' => [
                '@type' => 'Organization',
                'name' => $businessName,
            ],
        ];

        if (!empty($provider['title'])) {
            $schema['jobTitle'] = $provider['title'];
        }
        if (!empty($image)) {
            $schema['image'] = $image;
        }

        return [
            'title' => sprintf('%s | Book Online', $displayName),
            'description' => $description,
            'canonical' => $canonical,
            'image' => $image ?: base_url('assets/og-default.png'),
            'robots' => 'index, follow',
            'schema' => $schema,
        ];
    }

    private function buildSeoContextForService(array $service, array $providers, ?string $city = null): array
    {
        $serviceName = (string) ($service['name'] ?? 'Service');
        $citySuffix = $city ? (' in ' . $city) : '';
        $businessName = function_exists('setting') ? (setting('general.company_name', 'WebSchedulr') ?: 'WebSchedulr') : 'WebSchedulr';
        $providerCount = count($providers);
        $description = sprintf('Book %s%s at %s. Available with %d provider%s.', $serviceName, $citySuffix, $businessName, $providerCount, $providerCount === 1 ? '' : 's');

        $serviceSlug = (string) ($service['slug'] ?? '');
        $canonicalPath = $city
            ? 'booking/s/' . rawurlencode($serviceSlug) . '/' . rawurlencode(strtolower($city))
            : 'booking/s/' . rawurlencode($serviceSlug);
        $canonical = base_url($canonicalPath);

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Service',
            'name' => $serviceName,
            'url' => $canonical,
            'provider' => array_map(static function (array $provider): array {
                return [
                    '@type' => 'Person',
                    'name' => $provider['name'] ?? 'Provider',
                ];
            }, $providers),
        ];

        if ($city) {
            $schema['areaServed'] = [
                '@type' => 'City',
                'name' => $city,
            ];
        }

        return [
            'title' => sprintf('Book %s%s | %s', $serviceName, $citySuffix, $businessName),
            'description' => $description,
            'canonical' => $canonical,
            'image' => base_url('assets/og-default.png'),
            'robots' => 'index, follow',
            'schema' => $schema,
        ];
    }

    private function findProviderBySlug(string $slug): ?array
    {
        $slug = trim($slug);
        if ($slug === '') {
            return null;
        }

        $builder = $this->users->where('slug', $slug)->where('role', 'provider');
        if ($this->hasUsersColumn('is_active')) {
            $builder->where('is_active', true);
        } elseif ($this->hasUsersColumn('status')) {
            $builder->where('status', 'active');
        }

        return $builder->first();
    }

    private function findServiceBySlug(string $slug): ?array
    {
        $slug = trim($slug);
        if ($slug === '') {
            return null;
        }

        return $this->services->where('slug', $slug)->where('active', 1)->first();
    }

    private function resolveServiceQuery(string $query): ?array
    {
        $query = trim($query);
        if ($query === '') {
            return null;
        }

        $bySlug = $this->findServiceBySlug($query);
        if ($bySlug) {
            return $bySlug;
        }

        $needle = strtolower($query);
        $rows = $this->services->where('active', 1)->orderBy('name', 'ASC')->findAll();
        foreach ($rows as $row) {
            $name = strtolower(trim((string) ($row['name'] ?? '')));
            if ($name === $needle) {
                return $row;
            }
        }

        foreach ($rows as $row) {
            $name = strtolower(trim((string) ($row['name'] ?? '')));
            if ($name !== '' && str_contains($name, $needle)) {
                return $row;
            }
        }

        return null;
    }

    private function normalizeLocationNeedle(?string $city, ?string $area): ?string
    {
        $city = trim((string) $city);
        $area = trim((string) $area);

        if ($city === '' && $area === '') {
            return null;
        }

        if ($area !== '') {
            return $area;
        }

        return $city;
    }

    private function buildLocationSegment(?string $city, ?string $area): ?string
    {
        $city = trim((string) $city);
        $area = trim((string) $area);

        $source = $area !== '' ? $area : $city;
        if ($source === '') {
            return null;
        }

        $segment = strtolower($source);
        $segment = preg_replace('/[^a-z0-9]+/', '-', $segment) ?? '';
        $segment = trim($segment, '-');

        return $segment !== '' ? $segment : null;
    }

    private function normalizeIsoDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        try {
            return (new DateTimeImmutable($value))->format('c');
        } catch (Throwable) {
            return null;
        }
    }

    private function providerOffersService(int $providerId, int $serviceId): bool
    {
        $serviceRows = $this->services->getActiveByProvider($providerId);
        foreach ($serviceRows as $row) {
            if ((int) ($row['id'] ?? 0) === $serviceId) {
                return true;
            }
        }

        return false;
    }

    private function providerImageUrl(?string $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        if (str_starts_with($path, '/')) {
            return base_url(ltrim($path, '/'));
        }

        if (str_starts_with($path, 'assets/profile/') || str_starts_with($path, 'assets/providers/')) {
            return base_url($path);
        }

        if (str_starts_with($path, 'uploads/providers/')) {
            return base_url('assets/p/' . basename($path));
        }

        if (str_starts_with($path, 'uploads/')) {
            return base_url('writable/' . $path);
        }

        return base_url('assets/providers/' . ltrim($path, '/'));
    }

    private function nullableString($value): ?string
    {
        $text = trim((string) $value);
        return $text === '' ? null : $text;
    }

    /**
     * Remove internal identifiers from provider payloads emitted to public pages.
     * Public booking flows should use slug identifiers end-to-end.
     *
     * @param array<int, array<string, mixed>> $providers
     * @return array<int, array<string, mixed>>
     */
    private function sanitizeProvidersForPublic(array $providers): array
    {
        return array_map(static function (array $provider): array {
            unset($provider['id']);
            return $provider;
        }, $providers);
    }

    /**
     * Resolve public provider identity by slug first (preferred), with id fallback
     * for backward compatibility during migration.
     */
    public function resolvePublicProviderId(?string $providerSlug = null, ?int $providerId = null): int
    {
        $slug = trim((string) $providerSlug);
        if ($slug !== '') {
            $provider = $this->findProviderBySlug($slug);
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
            $provider = $this->findProviderBySlug($slug);
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

        $builder = $this->users->where('id', $providerId)->where('role', 'provider');
        if ($this->hasUsersColumn('is_active')) {
            $builder->where('is_active', true);
        } elseif ($this->hasUsersColumn('status')) {
            $builder->where('status', 'active');
        }

        $provider = $builder->first();
        if (!$provider) {
            throw new PublicBookingException('Selected provider is unavailable.', 404);
        }

        return $provider;
    }

    private function hasUsersColumn(string $column): bool
    {
        $key = 'users.' . $column;

        if (array_key_exists($key, $this->columnExistsCache)) {
            return $this->columnExistsCache[$key];
        }

        $db = \Config\Database::connect();
        $exists = false;

        try {
            if (!method_exists($db, 'getFieldData')) {
                $this->columnExistsCache[$key] = true;
                return true;
            }

            $candidates = [];
            if (method_exists($db, 'prefixTable')) {
                $candidates[] = (string) $db->prefixTable('users');
            }
            $candidates[] = 'users';

            foreach (array_values(array_unique($candidates)) as $candidate) {
                try {
                    $fields = $db->getFieldData($candidate);
                    foreach ($fields as $field) {
                        if ((string) ($field->name ?? '') === $column) {
                            $exists = true;
                            break 2;
                        }
                    }
                } catch (Throwable $inner) {
                    // Try the next candidate table name.
                }
            }
        } catch (Throwable $e) {
            $exists = false;
        }

        $this->columnExistsCache[$key] = $exists;

        return $exists;
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
        $phone = $this->normalizePhone($payload['phone'] ?? null, $payload['phone_country_code'] ?? null);
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

    private function normalizePhone(?string $value, ?string $countryCode = null): ?string
    {
        return $this->phoneNumberService->normalize($value, $countryCode);
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
            // Reschedule eligibility — computed server-side so the SPA can gate the form
            'can_reschedule' => $this->canReschedule($appointment['start_at']),
            // Cancellation eligibility — computed server-side so the SPA can gate cancel action
            'can_cancel' => $this->canCancel($appointment),
        ];
    }

    private function formatSlotRange(DateTimeImmutable $start, DateTimeImmutable $end): string
    {
        $dateLabel  = $start->format('D, M j');
        $startLabel = $this->localization->formatTimeForDisplay($start->format('H:i:s'));
        $endLabel   = $this->localization->formatTimeForDisplay($end->format('H:i:s'));
        return sprintf('%s at %s – %s', $dateLabel, $startLabel, $endLabel);
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
        $expectedEmail = strtolower((string) ($appointment['customer_email'] ?? ''));
        $expectedPhone = $this->normalizePhone($appointment['customer_phone'] ?? null, null);

        if ($expectedEmail) {
            if (!$email || strtolower(trim($email)) !== $expectedEmail) {
                throw new PublicBookingException('Contact verification failed.', 403);
            }
            return;
        }

        if ($expectedPhone) {
            if (!$phone || $this->normalizePhone($phone, $phoneCountryCode) !== $expectedPhone) {
                throw new PublicBookingException('Contact verification failed.', 403);
            }
        }
    }

    /**
     * Check whether an appointment's current start time is outside the reschedule policy window.
     * Returns false when rescheduling is disabled OR the appointment is within the cutoff.
     */
    private function canReschedule(string $startAt): bool
    {
        $policy = $this->getReschedulePolicy();
        return $this->isOutsidePolicyWindow($startAt, $policy);
    }

    private function assertRescheduleWindow(string $currentStart): void
    {
        $policy = $this->getReschedulePolicy();
        if (!$policy['enabled']) {
            throw new PublicBookingException('Rescheduling is not permitted for this booking.', 403);
        }

        // DB stores UTC — compare both datetimes in UTC
        $start = new DateTimeImmutable($currentStart, new DateTimeZone('UTC'));
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        if ($start <= $now) {
            throw new PublicBookingException('This appointment can no longer be rescheduled.', 403);
        }

        $cutoff = $now->add(new DateInterval('PT' . $policy['hours'] . 'H'));
        if ($cutoff > $start) {
            throw new PublicBookingException('This appointment is too close to reschedule online.', 403);
        }
    }

    /**
     * Check whether the appointment can be cancelled online based on status and policy window.
     */
    private function canCancel(array $appointment): bool
    {
        $status = AppointmentStatus::normalize((string) ($appointment['status'] ?? ''));
        if (!in_array($status, AppointmentStatus::UPCOMING, true)) {
            return false;
        }

        $policy = $this->getCancelPolicy();
        return $this->isOutsidePolicyWindow((string) ($appointment['start_at'] ?? ''), $policy);
    }

    private function assertCancellationWindow(array $appointment): void
    {
        $status = AppointmentStatus::normalize((string) ($appointment['status'] ?? ''));

        if ($status === AppointmentStatus::CANCELLED) {
            throw new PublicBookingException('This appointment has already been cancelled.', 409);
        }

        if (!in_array($status, AppointmentStatus::UPCOMING, true)) {
            throw new PublicBookingException('This appointment can no longer be cancelled online.', 403);
        }

        $policy = $this->getCancelPolicy();
        if (!$policy['enabled']) {
            throw new PublicBookingException('Online cancellations are not permitted for this booking.', 403);
        }

        $start = new DateTimeImmutable((string) $appointment['start_at'], new DateTimeZone('UTC'));
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        if ($start <= $now) {
            throw new PublicBookingException('This appointment can no longer be cancelled online.', 403);
        }

        $cutoff = $now->add(new DateInterval('PT' . $policy['hours'] . 'H'));
        if ($cutoff > $start) {
            throw new PublicBookingException('This appointment is too close to cancel online.', 403);
        }
    }

    private function getReschedulePolicy(): array
    {
        $settings = $this->settings->getByKeys(['business.reschedule']);
        $value = $settings['business.reschedule'] ?? '24h';

        return match ($value) {
            'none' => ['enabled' => false, 'hours' => 0, 'label' => 'Disabled'],
            '12h' => ['enabled' => true, 'hours' => 12, 'label' => '12 hours'],
            default => ['enabled' => true, 'hours' => 24, 'label' => '24 hours'],
        };
    }

    private function getCancelPolicy(): array
    {
        $settings = $this->settings->getByKeys(['business.cancel']);
        $value = $settings['business.cancel'] ?? '24h';

        return match ($value) {
            'none' => ['enabled' => false, 'hours' => 0, 'label' => 'Disabled'],
            '12h' => ['enabled' => true, 'hours' => 12, 'label' => '12 hours'],
            default => ['enabled' => true, 'hours' => 24, 'label' => '24 hours'],
        };
    }

    private function isOutsidePolicyWindow(string $startAt, array $policy): bool
    {
        if (!$policy['enabled']) {
            return false;
        }

        if (trim($startAt) === '') {
            return false;
        }

        $start = new DateTimeImmutable($startAt, new DateTimeZone('UTC'));
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        if ($start <= $now) {
            return false;
        }

        $cutoff = $now->add(new DateInterval('PT' . $policy['hours'] . 'H'));
        return $cutoff <= $start;
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
