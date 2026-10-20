<?php

/**
 * Public booking catalogue: bookable providers and services, slug lookup,
 * service/location discovery, SEO metadata and sitemap URLs.
 *
 * Collaborator of App\Services\PublicBookingService (the public booking facade).
 */

namespace App\Services\PublicBooking;

use App\Models\LocationModel;
use App\Models\ServiceModel;
use App\Models\SettingModel;
use App\Models\UserModel;
use App\Services\LocalizationSettingsService;
use DateTimeImmutable;
use Throwable;

class PublicBookingDirectory
{
    private UserModel $users;
    private ServiceModel $services;
    private LocationModel $locations;
    private SettingModel $settings;
    private LocalizationSettingsService $localization;

    public function __construct(
        UserModel $users,
        ServiceModel $services,
        LocationModel $locations,
        SettingModel $settings,
        LocalizationSettingsService $localization
    ) {
        $this->users = $users;
        $this->services = $services;
        $this->locations = $locations;
        $this->settings = $settings;
        $this->localization = $localization;
    }

    public function listProviders(?int $serviceId = null, ?string $city = null, ?string $area = null): array
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

    public function listServices(?int $providerId = null): array
    {
        $rows = $providerId ? $this->services->getActiveByProvider($providerId) : $this->services->where('active', 1)->orderBy('name', 'ASC')->findAll();

        helper('currency');
        $gateways = (new \App\Services\Payment\PaymentService())->getGatewayAvailability(1);

        return array_map(function (array $row) use ($gateways): array {
            $price      = (float) ($row['price'] ?? 0);
            $depositPct = isset($row['deposit_percentage']) ? (float) $row['deposit_percentage'] : null;
            $depositAmt = ($row['payment_enabled'] ?? 0) && $depositPct
                ? calculate_deposit_amount($price, $depositPct) : null;
            return [
                'id'                 => (int) $row['id'],
                'name'               => $row['name'] ?? 'Service',
                'slug'               => $row['slug'] ?? null,
                'duration'           => (int) ($row['duration_min'] ?? 30),
                'price'              => $price,
                'formattedPrice'     => $this->localization->formatCurrency($price),
                'deliveryModes'      => json_decode($row['delivery_modes'] ?? '["onsite"]', true) ?: ['onsite'],
                'payment_enabled'    => (bool) ($row['payment_enabled']  ?? false),
                'payfast_enabled'    => (bool) ($row['payfast_enabled']  ?? false),
                'stripe_enabled'     => (bool) ($row['stripe_enabled']   ?? false),
                'payfastAvailable'   => (bool) ($row['payfast_enabled']  ?? false) && $gateways['payfast'],
                'stripeAvailable'    => (bool) ($row['stripe_enabled']   ?? false) && $gateways['stripe'],
                'deposit_percentage' => $depositPct,
                'deposit_amount'     => $depositAmt,
                'formattedDeposit'   => $depositAmt !== null ? $this->localization->formatCurrency($depositAmt) : null,
            ];
        }, $rows);
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

    public function buildSeoContextForProvider(array $provider): array
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

    public function buildSeoContextForService(array $service, array $providers, ?string $city = null): array
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

        $providerBuilder = $this->users->whereHasRole('provider');
        UserModel::applyActiveFilter($providerBuilder);

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

    public function findProviderBySlug(string $slug): ?array
    {
        $slug = trim($slug);
        if ($slug === '') {
            return null;
        }

        $builder = $this->users->where('slug', $slug)->whereHasRole('provider');
        UserModel::applyActiveFilter($builder);

        return $builder->first();
    }

    public function findServiceBySlug(string $slug): ?array
    {
        $slug = trim($slug);
        if ($slug === '') {
            return null;
        }

        return $this->services->where('slug', $slug)->where('active', 1)->first();
    }

    public function resolveServiceQuery(string $query): ?array
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

    public function normalizeLocationNeedle(?string $city, ?string $area): ?string
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

    public function buildLocationSegment(?string $city, ?string $area): ?string
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

    public function normalizeIsoDate(string $value): ?string
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

    public function providerOffersService(int $providerId, int $serviceId): bool
    {
        $serviceRows = $this->services->getActiveByProvider($providerId);
        foreach ($serviceRows as $row) {
            if ((int) ($row['id'] ?? 0) === $serviceId) {
                return true;
            }
        }

        return false;
    }

    public function providerImageUrl(?string $path): ?string
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

    public function nullableString($value): ?string
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
    public function sanitizeProvidersForPublic(array $providers): array
    {
        return array_map(static function (array $provider): array {
            unset($provider['id']);
            return $provider;
        }, $providers);
    }
}
