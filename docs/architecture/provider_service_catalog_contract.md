# Provider Service Catalog Contract

**Last Updated:** March 25, 2026  
**Status:** Active

---

## Purpose

This document defines the booking-facing provider and service catalog behavior used by:

- internal appointment create/edit flows
- public booking provider/service selection
- provider-facing service lookup APIs

It exists to clarify the runtime contract around `xs_providers_services`, especially for legacy environments where the pivot table may still be empty.

---

## Source of Truth

The provider-to-service pivot remains the authoritative contract:

- `xs_providers_services` defines which providers can deliver which services
- `ServiceModel::getActiveByProvider(int $providerId)` is the canonical provider-scoped service lookup
- `UserModel::getProvidersWithActiveServices()` is the canonical booking-facing provider catalog query

When provider-service mappings exist, booking and appointment UIs must only expose valid linked combinations.

---

## Runtime Behavior

### Normal Mode

When `xs_providers_services` contains rows:

- providers without active linked services are excluded from booking-facing provider lists
- `GET /api/v1/providers/{id}/services` returns only active services linked to the selected provider
- public booking and internal appointment flows should not fall back to unrelated global services after provider selection

### Compatibility Fallback Mode

When `xs_providers_services` is globally empty:

- booking-facing provider lists may fall back to all active providers
- provider-scoped service lookups may fall back to all active services
- this fallback preserves usability for legacy datasets that have not yet populated provider-service assignments

This is a compatibility layer, not a replacement source of truth. As soon as provider-service mappings exist, runtime behavior returns to strict pivot-based filtering.

---

## Operational Guidance

- Populate `xs_providers_services` in seeded, migrated, and production environments whenever provider-specific service delivery matters.
- Do not build new business rules that depend on the fallback remaining available indefinitely.
- Treat the fallback as a guard against broken booking flows in legacy installs, not as the desired steady state.

---

## Affected Surfaces

- `app/Models/UserModel.php`
- `app/Models/ServiceModel.php`
- `app/Controllers/Services.php`
- `app/Controllers/Api/V1/Providers.php`
- `app/Controllers/Appointments.php`
- `app/Services/PublicBookingService.php`
- `resources/js/public-booking.js`

---

## Internal Management Contract

The internal service-management flows must preserve the same provider-to-service pivot contract used by booking surfaces:

- `Services::store()` persists the service first, then normalizes posted `provider_ids` and writes provider links through `ServiceModel::setProviders()`.
- `Services::update()` replaces existing provider links so stale `xs_providers_services` rows do not survive edits.
- `Services::delete()` removes pivot links before deleting the service record.
- Inline category creation during service creation is supported, but the resulting service must still store its `category_id` on the service row and provider assignment through the pivot table.

`app/Controllers/Services.php` now also supports optional constructor injection for the user, service, and category models so focused controller tests can replace collaborators directly.

---

## Regression Coverage

The current booking-facing provider/service contract is covered by:

- `tests/unit/PublicBookingServiceTest.php`
- `tests/integration/ProvidersServicesApiV1Test.php`
- `tests/unit/Controllers/ServicesControllerTest.php`
- `tests/integration/ServicesJourneyTest.php`
