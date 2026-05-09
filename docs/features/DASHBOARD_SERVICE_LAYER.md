# Dashboard Service Layer

The dashboard is served by four coordinated services. Each has a distinct responsibility.

---

## Service Map

| Service | File | Role |
|---|---|---|
| `DashboardService` | `app/Services/DashboardService.php` | Data aggregation: appointments, metrics, schedules, availability, alerts |
| `DashboardPageService` | `app/Services/DashboardPageService.php` | View controller helper: session validation, view data assembly, endpoint responses |
| `DashboardApiService` | `app/Services/DashboardApiService.php` | API controller helper: charts payload, analytics payload, status payload |
| `AppointmentDashboardContextService` | `app/Services/AppointmentDashboardContextService.php` | Role-based appointment scope resolution |

---

## `AppointmentDashboardContextService`

Resolves which appointments a user may see based on their role. Called early in the dashboard request flow to produce a `context` array used by downstream services.

### `build(?string $role, ?int $userId, ?array $user): array`

Returns:
```php
[
    'role'        => string,     // 'admin' | 'provider' | 'staff' | 'customer' | 'guest'
    'user_id'     => ?int,
    'provider_id' => int|int[]|null,  // null=admin (all), int=provider, int[]=staff provider IDs
    'customer_id' => ?int,
]
```

### Role scoping rules

| Role | `provider_id` value | Effect |
|---|---|---|
| `admin` | `null` | No filter — sees all appointments |
| `provider` | `$userId` (scalar) | Scoped to own appointments only |
| `staff` | `int[]` of assigned provider IDs from `xs_provider_staff_assignments` | Scoped to assigned providers; `[0]` when unassigned (no results) |
| `customer` | N/A — `customer_id = 0` | Not yet implemented; returns nothing |
| `guest` | N/A — `customer_id = 0` | No access |

Staff with no active assignments receive `provider_id = [0]` — this causes `whereIn('provider_id', [0])` to match nothing, preventing accidental full-table exposure.

---

## `DashboardService`

Core data aggregation service. All time boundaries are computed in the business timezone (from `LocalizationSettingsService::getTimezone()`) then converted to UTC for DB queries.

### Key methods

#### `getTodayMetrics(int|array|null $providerId): array`

Returns counts for the current business day. Builds a fresh query builder per metric to avoid accumulating `WHERE` clauses.

```php
[
    'total'     => int,  // all appointments today
    'upcoming'  => int,  // start_at within next 4 hours, status in AppointmentStatus::UPCOMING
    'pending'   => int,  // status = 'pending' today
    'cancelled' => int,  // status in ['cancelled', 'no_show'] today
    'confirmed' => int,  // status = 'confirmed' today
]
```

Returns all-zero array immediately when `$providerId = []` (staff with no assignments).

#### `getCachedMetrics(int|array|null $providerId): array`

Wraps `getTodayMetrics()` with a 5-minute CI4 cache. Cache keys:
- Admin: `dashboard_metrics_admin`
- Provider: `dashboard_metrics_{providerId}`
- Staff: `dashboard_metrics_staff_{sorted_ids_joined_by_underscore}`

#### `invalidateCache(?int $providerId): void`

Deletes `dashboard_metrics_{providerId}` and, if `$providerId` is not null, also `dashboard_metrics_admin`.

#### `getTodaySchedule(int|array|null $providerId): array`

Fetches today's appointments (status `pending|confirmed|completed`) with provider, customer, and service name. Converts `start_at`/`end_at` from UTC to local timezone via `TimezoneService::toDisplay()` before returning.

Returns an array keyed by provider name, each containing appointment rows:
```php
[
    'Provider Name' => [
        ['id', 'hash', 'start_at', 'end_at', 'customer_name', 'service_name', 'status'],
        ...
    ]
]
```

#### `getUpcomingAppointments(int|array|null $providerId): array`

Returns up to 10 upcoming appointments (status in `AppointmentStatus::UPCOMING`) over the next 7 days, sorted by `start_at` ASC. Times converted to local timezone for display.

#### `getAlerts(int|array|null $providerId): array`

Currently returns one alert type: pending confirmations count for today and future. Returns `[]` for staff with no assignments. Other alert types (missing hours, blocked periods, overbooking) are stubbed out.

#### `getProviderAvailability(int|array|null $providerId): array`

Returns one entry per active provider with their current status and next available slot. Used by dashboard provider cards.

Per-provider structure:
```php
[
    'id'               => int,
    'name'             => string,
    'status'           => 'working' | 'on_break' | 'off',
    'next_slot'        => array,   // has_slot, is_today, date, time, label
    'color'            => string,  // hex color
    'services'         => string[], // service names
    'service_options'  => array,   // [{id, name, duration_min}]
    'default_service_id' => ?int,
    'location_options' => array,   // [{id, name}]
    'slots_date'       => string,  // today's date
    'slots_for_date'   => array,   // time slots [{time, end_time, start_time, end_time_iso}]
]
```

Providers are resolved from `xs_user_roles` (authoritative, per §4.4). Default service is the shortest active service assigned via `xs_providers_services`.

#### `getProviderSlotsForDate(int $providerId, string $date, ?int $serviceId, ?int $locationId, ?int $limit): array`

Returns formatted time slots for a specific provider and date. Validates `$serviceId` against the provider's assigned services — slots are never returned for services not assigned to the provider. Delegates to `AvailabilityService::getAvailableSlots()`.

#### `getBookingStatus(): array`

Returns reminder automation health status for the dashboard status panel. Reads `xs_business_notification_rules`, `xs_business_integrations`, and `xs_notification_queue`. Consults `NotificationReminderHeartbeatService` to determine whether the cron dispatcher is running on time.

#### `getDashboardContext(int $userId, string $userRole, ?int $providerId): array`

Builds the view context object: business name, current date (localized), timezone, user info. Merges output from `AppointmentDashboardContextService::build()`.

#### `formatRecentActivities(array $activities): array`

Converts raw appointment activity rows into display-ready format with `user_name`, `activity` description, `status` CSS class, and `date`.

---

## `DashboardPageService`

Thin orchestrator used by `Controllers/Dashboard.php` to separate view-assembly logic from the controller.

### `resolveLandingSession(): array|RedirectResponse`

Validates the session is live and the user has dashboard access. Returns a `redirect()` to `/auth/login` if the session is invalid, or an array with:
```php
['currentUser', 'userRole', 'providerId', 'providerScope']
```

`providerScope` is `null` (admin), `int` (provider), or `int[]` (staff) — the shape passed to `DashboardService` methods.

### `buildLandingViewData(array $sessionData): array`

Assembles the full view data array passed to `app/Views/dashboard/`. Calls every `DashboardService` method and the `BookingMetricsService` canonical booking total.

The `detailed_stats.services.bookings` key is overridden by `BookingMetricsService::getTotalBookings()` — the `AppointmentModel::getStats()` value is not used for this field.

### `getFallbackLandingViewData(): array`

Returns a safe all-zeros structure used when `buildLandingViewData()` throws.

### `getMetricsEndpointResponse(): array`

Used by the AJAX metrics endpoint. Returns `{ statusCode, payload }`. Validates session and role before delegating to `DashboardService::getTodayMetrics()`.

### `getScheduleEndpointResponse(): array`

Used by the AJAX schedule fragment endpoint. Returns `{ statusCode, html }` where `html` is a rendered `dashboard/_schedule_fragment` partial for the `#dashboard-schedule-body` slot.

---

## `DashboardApiService`

Used by `Controllers/Api/Dashboard.php` to serve the charts and analytics API endpoints.

### `normalizePeriod(?string $period): string`

Accepts `'day' | 'week' | 'month' | 'year'`. Returns `'month'` for any unrecognized value.

### `getChartsPayload(?string $period): array`

Returns:
```php
[
    'appointmentGrowth'   => array,  // from AppointmentModel::getAppointmentGrowth()
    'servicesByProvider'  => array,  // from AppointmentModel::getProviderServicesByPeriod()
    'statusDistribution'  => array,  // chart-formatted status stats with colors
    'period'              => string,
]
```

### `getChartsFallbackPayload(string $message): array`

Safe empty payload with `'No Data'` labels for all three chart datasets.

### `getAnalyticsPayload(): array`

Comprehensive analytics snapshot: user stats + growth, appointment stats + weekly/monthly data + status distribution, service stats + popular services, revenue (today/week/month) from `AppointmentModel::getRealRevenue()`.

### `getStatusPayload(): array`

Simple health check: whether core tables exist and their row counts. Used by the status API endpoint.

---

## Data Flow

```
GET /dashboard
  └── Dashboard::index()
        ├── DashboardPageService::resolveLandingSession()
        │     └── AuthorizationService::getUserRole() / getProviderId() / getProviderScope()
        └── DashboardPageService::buildLandingViewData()
              ├── DashboardService::getDashboardContext()
              │     └── AppointmentDashboardContextService::build()
              ├── DashboardService::getCachedMetrics()          [5-min cache]
              ├── DashboardService::getTodaySchedule()
              ├── DashboardService::getAlerts()
              ├── DashboardService::getUpcomingAppointments()
              ├── DashboardService::getProviderAvailability()
              │     └── AvailabilityService::getAvailableSlots() [per provider]
              └── BookingMetricsService::getTotalBookings()
```

---

## Related

- `app/Controllers/Dashboard.php` — view controller
- `app/Controllers/Api/Dashboard.php` — API controller
- `app/Services/AuthorizationService.php` — role/scope resolution
- `app/Services/AvailabilityService.php` — slot generation for provider cards
- `app/Services/BookingMetricsService.php` — canonical booking total
- `app/Services/LocalizationSettingsService.php` — timezone and date format
- `resources/js/modules/dashboard/provider-cards.js` — frontend for provider availability cards
- `app/Views/dashboard/` — view templates
