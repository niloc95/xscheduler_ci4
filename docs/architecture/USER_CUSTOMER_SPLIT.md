# User and Customer Domain Split

## Overview

The system separates two distinct identity domains:

| Domain | Table | Who they are |
|---|---|---|
| **System users** | `xs_users` | Staff who log in: admin, provider, staff |
| **Booking customers** | `xs_customers` | External people who book appointments |

These domains share no table. An appointment links `provider_id → xs_users.id` and `customer_id → xs_customers.id`. Do not use the deprecated `appointments.user_id` column in any new logic.

---

## `xs_users` — System Accounts

Holds admin, provider, and staff login accounts. Roles are enforced via `xs_user_roles` (authoritative) with `xs_users.role` kept as the highest-privilege compatibility column.

`customer` is **not** a login role. External customers do not have `xs_users` rows.

For the full schema, role membership model, and session contract, see `docs/architecture/ROLE_BASED_SYSTEM.md` and `Agent_Context_v2.md §4` and `§12.3`.

---

## `xs_customers` — Booking Customers

| Column | Type | Notes |
|---|---|---|
| `id` | INT PK | Internal ID — never exposed in public URLs |
| `hash` | VARCHAR(64) | SHA-256 unique slug for all public-facing references |
| `first_name` | VARCHAR | |
| `last_name` | VARCHAR | |
| `email` | VARCHAR | Unique index as of migration `2026-04-30-120000_AddUniqueEmailToCustomers` |
| `phone` | VARCHAR | |
| `address` | TEXT | |
| `notes` | TEXT | |
| `custom_fields` | TEXT | JSON map of custom field key → stored value |
| `created_at` / `updated_at` | DATETIME | |

### `hash` — Public Identity Token

`CustomerModel` auto-generates `hash` on insert via `beforeInsert` callback:

```php
$hash = hash('sha256', uniqid('customer_', true) . $encryptionKey . time());
```

64 characters. Unique index present (`idx_customers_hash`). All public-facing URLs use the hash, never the numeric `id`.

### `custom_fields` — Customer-Level Custom Fields

JSON text column storing key-value pairs for business-defined custom fields (e.g. Medical Aid Number). A field marked `required` in booking settings is only enforced on initial booking when the customer does not already have a stored value. See `Agent_Context_v2.md` Custom Field Required Semantics section for the full contract.

`CustomerCustomFieldService::hasStoredValue($storedValues, $fieldKey)` is the canonical check for whether a customer already has a value for a given custom field.

---

## `xs_appointment_custom_fields` — Appointment-Level Custom Fields

Separate table (added migration `2026-04-30-140000_CreateAppointmentCustomFieldsTable.php`). Stores structured custom field values per appointment, distinct from the customer-level `xs_customers.custom_fields` JSON column.

| Column | Type |
|---|---|
| `id` | INT PK |
| `appointment_id` | INT FK → `xs_appointments.id` |
| `field_key` | VARCHAR |
| `value` | TEXT |
| `created_at` / `updated_at` | DATETIME |

Model: `app/Models/AppointmentCustomFieldModel.php` — table `xs_appointment_custom_fields`.

The distinction:
- `xs_customers.custom_fields` — the customer's stored values (persisted across all bookings)
- `xs_appointment_custom_fields` — structured field data attached to a specific appointment

---

## Canonical Relationships

```
xs_appointments.customer_id  →  xs_customers.id
xs_appointments.provider_id  →  xs_users.id
xs_appointments.service_id   →  xs_services.id
xs_appointments.location_id  →  xs_locations.id
```

`xs_appointments.user_id` is deprecated. Do not use it in new logic.

---

## Public URL Contract

Customer-facing URLs use the hash, never the numeric ID:

```
/my-appointments/{hash}           ← customer portal
/api/customers/by-hash/{hash}/appointments
/api/customers/by-hash/{hash}/autofill
```

Internal/authenticated routes use the numeric ID:

```
/api/customers/{id}/appointments/upcoming
/api/customers/{id}/appointments/history
```

See `Agent_Context_v2.md §10.3` for public security rules (no numeric customer IDs in public URLs).

---

## Model Layer

### `CustomerModel` (`app/Models/CustomerModel.php`)

| Method | Purpose |
|---|---|
| `findByHash(string $hash): ?array` | Primary public lookup — used by all hash-based routes |
| `resolveByIdentifier(string $identifier): ?array` | Resolves hash first, then numeric ID fallback (internal routes only) |
| `findByEmail(string $email): ?array` | Duplicate check on booking |
| `search(array $params): array` | Full-text search for customer management UI |

### `CustomerCustomFieldService` (`app/Services/CustomerCustomFieldService.php`)

Handles custom field validation and merge logic for booking forms and reschedule flows. See the Custom Field Required Semantics section in `Agent_Context_v2.md` for the full contract including initial-capture vs edit-time requirements.

---

## Scope Enforcement

The `CustomerManagement` controller enforces role-based customer access via `isCustomerAccessible()`:
- **Admin** — all customers
- **Provider** — customers with appointments assigned to them
- **Staff** — customers from their assigned providers' appointment history

`CustomerAppointmentService::resolveCustomerIdsForProvider()` and `resolveCustomerIdsForStaff()` are the canonical scope resolvers. See `Agent_Context_v2.md §9.3`.

---

## Related

- `Agent_Context_v2.md §9` — Customer vs User Domain Split contract
- `Agent_Context_v2.md §12.3` — Full table schema for `xs_customers` and `xs_users`
- `docs/architecture/ROLE_BASED_SYSTEM.md` — `xs_users` role model
- `app/Models/CustomerModel.php`
- `app/Models/UserModel.php`
- `app/Models/AppointmentCustomFieldModel.php`
- `app/Services/CustomerCustomFieldService.php`
- `app/Services/CustomerAppointmentService.php`
