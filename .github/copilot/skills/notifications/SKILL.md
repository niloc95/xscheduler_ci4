---
name: webscheduler-notifications
description: WebScheduler notifications contract — canonical services, queue-first delivery, dispatch architecture, event/template rules, queue record contract, internal recipient resolution, reminder offsets behavior (independent processing, schedule fingerprint, stale cancellation), template loading and placeholders, MailerService unified email transport, and business-ID scoping. Use whenever you're touching notifications, email/SMS/WhatsApp delivery, the notification queue, reminders, templates, MailerService, or anything in `app/Services/Notification*` or `app/Services/Mailer*`. Triggers on phrases like "notification", "email", "SMS", "WhatsApp", "queue", "reminder", "template", "placeholder", "dispatch", "cron", "MailerService", "NotificationQueue", "appointment_reminder", "idempotency", "schedule fingerprint", "opt-out", "SMTP", "Mailpit".
---

# WebScheduler — Notifications Contract

## 1. Canonical Services

- `AppointmentEventService`
- `NotificationQueueService`
- `NotificationQueueDispatcher`
- `NotificationPolicyService`
- `NotificationEmailService`
- `NotificationSmsService`
- `NotificationWhatsAppService`
- `AppointmentNotificationService`

## 2. Delivery Contract

**Queue first, then dispatch**, with idempotency and delivery logging preserved.

SMTP transport for notifications is owned by `MailerService` — see §7 below.

Business-ID scoping for UI-facing notification methods is owned by `current_business_id()` in `permissions_helper`. See `architecture` skill (§8) for full resolution priority and service usage pattern. **Do not use `NotificationCatalog::BUSINESS_ID_DEFAULT` directly in UI-facing service methods.**

## 3. Dispatch Architecture Notes

- Booking-time notifications are queued **synchronously inline** during the appointment mutation request, but **dispatch is deferred** to the queue worker / cron (`php spark notifications:dispatch-queue`). **Do not perform SMTP or channel delivery inline in the HTTP request path.**
- New booking flows must derive event type from `AppointmentStatus::notificationEvent()` instead of hardcoding confirmation events.
- `PATCH /api/appointments/{id}/status` fires notifications server-side for each transition.
- Frontend events such as `appointments-updated` do not drive notification delivery; notifications are triggered by **backend** mutation flows.
- Manual resend flows use `POST /api/appointments/{id}/notify`. Email and SMS resends send immediately, while WhatsApp resends are queued.

### 3.1 HTTP Request Path Rule

- `AppointmentBookingService::queueNotifications()` must **enqueue only**. It must not instantiate `NotificationQueueDispatcher` for immediate delivery.
- Inline dispatch during booking/reschedule/cancel requests is a correctness and performance hazard: it blocks the HTTP response on SMTP/channel transport availability and turns transient transport failures into user-facing latency.
- **Correct behavior if SMTP/transport is down:** mutation succeeds, notification rows remain queued/failed for retry, and the cron/dispatcher handles recovery later.

## 4. Event and Template Rules

- `appointment_pending` is the canonical event for pending bookings.
- `appointment_confirmed` is the canonical event for confirmed bookings and completed appointments.
- `appointment_reminder` is **cron-only** and is not triggered by a status transition.
- `booking.default_appointment_status` is a notification control point because it determines whether new bookings emit `pending` or `confirmed` events.
- Customer and internal templates are split by `recipient_class`, and internal fallback templates exist for migration-drift safety.

See `scheduling` skill for the status → event mapping table.

## 5. Queue Record Contract

- **Customer-facing rows** are written through `AppointmentEventService` and `NotificationQueueService::enqueueAppointmentEvent()`.
- **Internal provider/staff rows** are written through `AppointmentBookingService::enqueueInternalNotifications()` and `NotificationQueueService::enqueueInternalEvent()`.
- Queue rows must preserve `attempts`/`max_attempts` retry semantics, `run_after` scheduling, and row-level locking through `locked_at` and `lock_token`.
- `idempotency_key` is the deduplication key; `correlation_id` is the tracing key shared with delivery logs.

### Key Queue Fields

- `recipient_type`
- `recipient_user_id`
- `attempts`
- `max_attempts`
- `run_after`
- `locked_at`
- `lock_token`
- `last_error`
- `sent_at`
- `idempotency_key`
- `correlation_id`
- `reminder_offset_minutes` (reminder rows only — offset that triggered this row; used for stale-reminder cancellation)
- `schedule_fingerprint` (SHA1 of `start_at|end_at|updated_at`; dispatcher cancels reminder rows whose fingerprint no longer matches the live appointment)

## 6. Architecture Flow

1. Appointment mutation determines event via `AppointmentStatus::notificationEvent()`
2. Queue rows written to `xs_notification_queue`
3. Dispatcher sends by channel
4. Attempt outcomes written to `xs_notification_delivery_logs`

## 7. Unified Email Transport Contract (Owner Section)

All email transport rules live here. Other sections contain reference-only reminders.

### 7.1 Single Transport Layer

`MailerService` is the **sole permitted email transport layer**. No other class may instantiate or configure a CI4 `Email` driver for production sends. Every outgoing email — password reset, booking notifications, and future system emails — flows through `MailerService::send()`.

### 7.2 Transport Resolution Priority (Strict)

1. **Active DB integration (all environments):** An `xs_business_integrations` row where `channel = 'email'` and `is_active = 1` exists for the business. Config is decrypted from `encrypted_config`. **This takes unconditional priority.**
2. **`.env` dev fallback (development only):** When `ENVIRONMENT === 'development'` and no active integration row is found, `MailerService` uses `Config\Email` (populated from `.env`). If `.env` does not specify `protocol = smtp`, the service falls back to a hardcoded Mailpit address (`127.0.0.1:1025`) provided a `fromEmail` is configured.
3. **`null` — cannot send:** If neither source yields a valid config, `resolveTransportConfig()` returns `null` and `send()` returns `['ok' => false, ...]`.

### 7.3 From-Address Ownership

`MailerService` resolves the from address using this priority:

1. `$fromEmailOverride` (caller-supplied, when non-empty)
2. `$config['from_email']` (from the resolved transport config)
3. Failure — an empty from address causes `send()` to return `['ok' => false]`.

**Callers must never set a from address on the email driver directly.**

### 7.4 `send()` Response Contract

```php
['ok' => bool, 'error' => ?string, 'transport' => string, 'messageId' => ?string]
```

- `transport` values: `'smtp'` (DB integration), `'dev-fallback'` (`.env`), `'unknown'` (error before transport resolved)
- `messageId` is always `null` (reserved for future SMTP header extraction)

### 7.5 `canSend()` — Queue Gate Capability Check

`MailerService::canSend(int $businessId)` returns `true` only when the resolved config has a non-empty `host` AND a non-empty `from_email`.

`NotificationEmailService::canUseDevelopmentFallbackSmtp()` is a thin delegate to `MailerService::canSend()`. This method is the **queue gate**; queue files (`NotificationQueueDispatcher`, `NotificationQueueService`) must not be changed to reference `MailerService` directly without updating this note.

### 7.6 `testConnection()` Isolation

`NotificationEmailService::sendTestEmail()` **bypasses `MailerService` intentionally**. It constructs a CI4 Email driver from a caller-supplied config object to test SMTP credentials in real time. Do not route `sendTestEmail()` through `MailerService` — the bypass is load-bearing for the settings integration wizard.

### 7.7 Auth Email Channel

Password reset emails use `NotificationCatalog::BUSINESS_ID_DEFAULT` (value `1`) as `$businessId`. `Auth::sendResetEmail()` renders the view, then calls `MailerService::send()`. Auth has no knowledge of SMTP configuration. See `auth-rbac` skill.

## 8. Internal Recipient Contract

Internal notifications:

- `recipient_type = internal`
- `recipient_user_id` stores `xs_users.id`
- Final contact resolution happens at dispatch time
- Eligibility follows `notify_on_appointments` preference
- Internal recipient selection is determined by `UserModel::getNotifiableUsersForProvider()`
- Both admin user-management and self-service profile surfaces write the `notify_on_appointments` preference

## 9. Timezone and Delivery Prerequisites

- Notification rendering must resolve display timezone from `xs_appointments.stored_timezone` first.
- If `stored_timezone` is unavailable, fall back to `TimezoneService::businessTimezone()`.
- Do not rely on implicit session timezone when rendering queued notifications.
- Production delivery requires enabled business notification rules and active business integrations with non-empty encrypted configuration.
- If required rule or integration configuration is missing, queue rows can be cancelled or failed during dispatch.

Dev-only SMTP fallback for email: see §7.2 for transport priority and §7.5 for the queue gate. The fallback covers both queue-enqueue checks and queue-dispatch checks transparently.

See `database` skill for the full Timezone Integrity contract.

## 10. Reminder Offsets Behavior

### 10.1 Independent Offset Processing

**Each reminder offset triggers independently. No offset blocks another.**

Configuration (Settings → Notifications → Customer Reminder Offsets):

- Primary offset: e.g., `4320` minutes (3 days before)
- Secondary offset: e.g., `60` minutes (1 hour before)
- Offsets are stored as an array in `xs_business_notification_rules.reminder_offsets_json`

`NotificationQueueService::enqueueDueReminders()` processes each offset separately:

```
For each appointment where start_at in (now, +30 days]:
  For each channel (email, sms, whatsapp):
    For each offset in that channel's offset list:
      dueAt = start_at - offset_minutes
      If now >= dueAt → enqueue a row with marker 'offset:N'
      Else → skip (not yet due)
```

There is no cross-offset dependency. An offset that is already past-due does not affect a future offset that has not yet arrived.

### 10.2 Concrete Example — Appointment Booked 1 Day in Advance

**Config:** `[4320 min (3 days), 60 min (1 hour)]`
**Booking time:** today 14:00 UTC. **Appointment:** tomorrow 14:00 UTC.

| Offset | dueAt | now >= dueAt | Result |
|--------|-------|-------------|--------|
| 4320 min (3 days) | 3 days ago | ✅ TRUE | Enqueued immediately (catch-up) |
| 60 min (1 hour) | tomorrow 13:00 | ❌ FALSE | Skipped — enqueued when tomorrow 13:00 arrives |

Both reminders will send. The first being past-due at booking time does not block the second.

### 10.3 Queue Row Identity

Each offset creates its own queue row with:

- `reminder_offset_minutes` — the specific offset value that triggered this row
- `idempotency_key` — includes marker `'offset:N'` so the same offset for the same appointment is never double-enqueued
- `schedule_fingerprint` — SHA1(`start_at|end_at|updated_at`); dispatcher cancels a reminder row if the fingerprint no longer matches the live appointment (i.e., appointment was rescheduled after enqueue)

### 10.4 `reminder_sent` Flag — Compatibility Only

After a **customer** reminder dispatches successfully, `reminder_sent = 1` is set on the appointment row via `NotificationQueueDispatcher::markReminderSentIfSupported()`.

**This flag is NOT checked as an enqueue-time filter.** `enqueueDueReminders()` does not skip appointments with `reminder_sent = 1`. The flag is for **display purposes only**.

> **Known hazard (fixed 2026-04-23, commit `e5bf2e7`):** Previously, the flag write used `model->update(['reminder_sent' => 1])`, which also updated `updated_at`. That changed the schedule fingerprint mid-dispatch, causing sibling reminder rows for the same appointment to be cancelled as stale. The fix writes `reminder_sent` via query builder `set()` without touching `updated_at`.

### 10.5 Debugging Reminder Offsets

```bash
# Force an appointment into a reminder-due window, then enqueue + dispatch
php spark notifications:test-reminder <appointmentId> [businessId] [minutesUntilStart]

# Example: set appointment to start 45 min from now
php spark notifications:test-reminder 96 1 45
```

Output shows per-offset queue rows with their `reminder_offset_minutes`, `status` (`sent` / `queued` / `cancelled`), and `schedule_fingerprint`. A healthy run shows one row per offset per recipient, all with `status=sent` and `Cancelled: 0`.

## 11. Template Contract

### 11.1 Recipient Classes

- **`customer`** — outbound to the person who booked. Resolved via settings-based custom templates, then `DEFAULT_TEMPLATES` in code.
- **`internal`** — outbound to providers/staff. Resolved via `xs_message_templates` rows seeded by migration, then `DEFAULT_INTERNAL_TEMPLATES` in code.

### 11.2 Template Loading Priority — Customer Class

1. `xs_settings` row with key `notification_template.{event_type}.{channel}` (JSON value `{"subject":"...","body":"..."}`)
2. `NotificationTemplateService::DEFAULT_TEMPLATES` (code-level fallback)

Settings-based templates are upserted by migrations and can be overridden at runtime without code deploys.

### 11.3 Template Loading Priority — Internal Class

1. `xs_message_templates` row where `recipient_class = 'internal'`, `is_active = 1`
2. `NotificationTemplateService::DEFAULT_INTERNAL_TEMPLATES` (code-level fallback)

### 11.4 Supported Placeholder Set (34 total)

**Customer info:** `{customer_name}`, `{customer_first_name}`, `{customer_email}`, `{customer_phone}`

**Appointment info:** `{service_name}`, `{service_duration}`, `{provider_name}`, `{appointment_date}`, `{appointment_time}`, `{appointment_datetime}`

**Business info:** `{business_name}`, `{business_email}`, `{business_phone}`, `{business_address}`

**Legal content:** `{cancellation_policy}`, `{rescheduling_policy}`, `{terms_link}`, `{privacy_link}`

**Links:** `{reschedule_link}`, `{booking_url}`, `{booking_id}`, `{internal_view_link}`, `{internal_edit_link}`, `{internal_contact_link}`, `{booked_via}`, `{booked_timestamp}`

**Location:** `{location_name}`, `{location_address}`, `{location_contact}`

**Navigation / calendar:** `{booking_reference}`, `{calendar_link}`, `{google_maps_link}`, `{waze_link}`

### 11.5 Business Contact Resolution

- `{business_email}` → `general.company_email` setting via `legalContent`
- `{business_phone}` → `general.telephone_number`, then `general.mobile_number`, with `general.company_phone` as legacy fallback via `legalContent`
- `{business_name}` → `general.business_name` setting via `legalContent`
- `{business_address}` → `general.business_address` setting via `legalContent`

### 11.6 Location and Map Link Resolution

- `{location_address}` — primary value is `xs_appointments.location_address`. If empty, falls back to `general.business_address` setting.
- `{google_maps_link}` and `{waze_link}` — generated from the resolved `{location_name} + {location_address}` string. Empty when no address is resolvable.
- `{calendar_link}` — Google Calendar add-event URL built from `start_datetime`, service duration, resolved location, and `{booking_reference}`.

### 11.7 Booking Reference Format

`WS-{year}-{id_zero_padded_4}` — e.g., `WS-2026-0042`. Sourced from `booking_id` or `appointment_id` in render data.

### 11.8 Customer Email Contact Placement (V4)

All 5 customer email bodies place the enquiries line inside the appointment details block:

```
☎ Enquiries: {business_email} | Tel: {business_phone}
```

The closing footer now ends with:

```
{business_name}
{terms_link} | {privacy_link}
```

**Do not alter this customer email layout without creating a new migration to upsert updated settings rows.**

### 11.9 Required Placeholders

`{reschedule_link}` is **required** in `email`, `sms`, and `whatsapp` bodies for `appointment_pending` and `appointment_confirmed`. `render()` auto-appends a fallback block if the placeholder is missing from a stored template.

## 12. Useful Spark Commands

```bash
php spark notifications:dispatch-queue              # enqueue due reminders + dispatch queue
php spark notifications:test-reminder <id> [businessId] [minutesUntilStart]
php spark notifications:backfill-templates          # backfill missing notification templates
php spark notifications:purge-delivery-logs         # purge old delivery log rows
php spark notifications:export-delivery-logs        # export delivery logs
php spark notifications:repair-business-id          # repair business_id on queue rows
php spark audit:reminder-pipeline                   # audit reminder scheduling pipeline
```

See `operations` skill for the full command catalog.

## 13. Cross-Skill References

- Status → event mapping → `scheduling` skill (owner)
- Schema fields for queue/logs/templates → `database` skill
- Business-ID resolver → `architecture` skill (owner)
- Auth-only password reset path → `auth-rbac` skill
