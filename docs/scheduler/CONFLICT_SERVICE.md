# Conflict Service

**File:** `app/Services/ConflictService.php`  
**Primary consumer:** `AvailabilityService.php`

---

## Purpose

`ConflictService` is a single-responsibility service that detects scheduling conflicts. It was extracted from `AvailabilityService` to provide a clean, independently testable API for overlap queries.

It covers two conflict types:
- **Appointment conflicts** — existing confirmed/pending appointments that overlap a proposed time range
- **Blocked time conflicts** — provider-specific or global blocked periods that overlap a proposed time range

---

## Datetime Contract

All datetime parameters passed to this service must be in **UTC** (`'Y-m-d H:i:s'` format), matching how `start_at` and `end_at` are stored in `xs_appointments` and `xs_blocked_times`. Do not pass local/display-timezone strings.

---

## Methods

### `hasConflict()`

```php
public function hasConflict(
    int $providerId,
    string $startUtc,
    string $endUtc,
    ?int $excludeAppointmentId = null,
    ?int $locationId = null
): bool
```

Quick boolean check. Returns `true` if any non-cancelled appointment for the provider overlaps the given UTC range.

- `$excludeAppointmentId` — used during reschedule flows to exclude the appointment being rescheduled.
- `$locationId` — optional scope to a specific location. When `null`, location is not filtered.

Delegates to `getConflictingAppointments()` and returns `!empty()`.

---

### `getConflictingAppointments()`

```php
public function getConflictingAppointments(
    int $providerId,
    string $startUtc,
    string $endUtc,
    ?int $excludeAppointmentId = null,
    ?int $locationId = null
): array
```

Returns all appointment rows that overlap the given UTC range. Uses standard three-clause interval overlap logic:

| Clause | SQL condition |
|---|---|
| New starts during existing | `start_at <= $startUtc AND end_at > $startUtc` |
| New ends during existing | `start_at < $endUtc AND end_at >= $endUtc` |
| New contains existing | `start_at >= $startUtc AND end_at <= $endUtc` |

Only non-cancelled appointments are checked (`status != 'cancelled'`).

Returns raw appointment rows as arrays. An empty array means no conflicts.

---

### `getBlockedTimesForPeriod()`

```php
public function getBlockedTimesForPeriod(
    int $providerId,
    string $startUtc,
    string $endUtc
): array
```

Returns blocked time rows that overlap the given UTC range for the provider. Includes:
- Provider-specific blocks (`provider_id = $providerId`)
- Global blocks (`provider_id IS NULL`) — e.g. public holidays or system-wide closures

Uses the same three-clause overlap logic as `getConflictingAppointments()`.

Returns raw `xs_blocked_times` rows as arrays.

---

## Instantiation

`ConflictService` accepts optional injected models for testability:

```php
$service = new ConflictService();
// or with injected mocks:
$service = new ConflictService($mockAppointmentModel, $mockBlockedTimeModel);
```

---

## Overlap Logic Reference

For an interval `[A, B)` and a proposed interval `[X, Y)`, overlap exists when:

```
A < Y  AND  B > X
```

The three-clause SQL decomposition above is equivalent to this formula.

---

## Related

- `app/Services/AvailabilityService.php` — primary caller; uses `getConflictingAppointments()` and `getBlockedTimesForPeriod()` in slot generation pipeline
- `app/Models/AppointmentModel.php` — queried for conflict rows
- `app/Models/BlockedTimeModel.php` — queried for blocked periods
- `Agent_Context_v2.md §8.8` — full slot generation pipeline (step 3: remove blocked times; step 4: remove booked appointments)
