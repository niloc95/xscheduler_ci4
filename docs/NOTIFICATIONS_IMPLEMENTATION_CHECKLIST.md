# WebSchedulr Notifications – Implementation Checklist

This checklist is intentionally phased to minimize risk and deliver early value (South Africa + shared-hosting friendly).

## Phase 1 — Rules UI (No Sending)
- [x] Add Notification Settings tab in Settings
- [x] Define supported events (v1)
  - [x] `appointment_confirmed`
  - [x] `appointment_reminder`
  - [x] `appointment_cancelled`
- [x] Event → Channel matrix UI
  - [x] Email visible
  - [x] SMS visible
  - [x] WhatsApp visible
- [x] Reminder timing per event (offset minutes)
- [x] Default notification language setting
- [x] Read-only message previews (placeholders only)
- [x] DB schema (phase-ready)
  - [x] `business_notification_rules`
  - [x] `business_integrations`
  - [x] `message_templates` (dormant)

## Phase 2 — Email Channel (SMTP)
- [x] SMTP integration (config + validation)
- [x] Test email send endpoint/button
- [x] Health indicator (last_tested_at + status)
- [x] Enqueue + dispatch email notifications
  - [x] Confirmed
  - [x] Reminder
  - [x] Cancellation

## Phase 3 — SMS Channel
- [x] Provider selection (Clickatell primary, Twilio optional)
- [x] Credential storage (encrypted)
- [x] Sender ID / From validation
- [x] Test SMS
- [x] Enqueue + dispatch SMS reminders

## Phase 4 — WhatsApp Channel (Meta Cloud API)
- [x] Meta Cloud API integration + token handling
- [x] Template reference storage (provider_template_id)
- [x] Connection health checks
- [x] Template-only enforcement
- [x] Enqueue + dispatch WhatsApp
  - [x] Confirmed
  - [x] Reminder
  - [x] Cancellation

## Phase 5 — Queue & Dispatch Engine (Harden)
- [x] `notification_queue` table
- [x] Cron dispatch command
- [x] Retry/backoff
- [x] Rate limiting / provider throttling
- [x] Idempotency

## Phase 6 — Logs, Monitoring & Compliance
- [x] Delivery logs UI
- [x] Audit trail and correlation IDs
- [x] Opt-in enforcement hooks
- [x] Data retention + export

---

## Scheduling Note (Revisit After Phase 6)
- Phase 5 introduces a queue + dispatcher command: `php spark notifications:dispatch-queue`.
- For fully automatic sending/reminders/retries, this command must be run on a schedule.
  - VPS: cron (or systemd timer) is the standard approach.
  - Shared hosting: usually cPanel “Cron Jobs”; if unavailable, use an external scheduler pinging a secured endpoint.
- Phase 6 is complete; scheduling decision/implementation can proceed.
