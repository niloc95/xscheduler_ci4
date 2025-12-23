# WebSchedulr Notifications – Implementation Checklist

This checklist is intentionally phased to minimize risk and deliver early value (South Africa + shared-hosting friendly).

## Phase 1 — Rules UI (No Sending)
- [ ] Add Notification Settings tab in Settings
- [ ] Define supported events (v1)
  - [ ] `appointment_confirmed`
  - [ ] `appointment_reminder`
  - [ ] `appointment_cancelled`
- [ ] Event → Channel matrix UI
  - [ ] Email visible
  - [ ] SMS visible
  - [ ] WhatsApp visible but disabled (Coming Soon)
- [ ] Reminder timing per event (offset minutes) 
- [ ] Default notification language setting
- [ ] Read-only message previews (placeholders only)
- [ ] DB schema (phase-ready)
  - [ ] `business_notification_rules`
  - [ ] `business_integrations`
  - [ ] `message_templates` (dormant)

## Phase 2 — Email Channel (SMTP)
- [ ] SMTP integration (config + validation)
- [ ] Test email send endpoint/button
- [ ] Health indicator (last_tested_at + status)
- [ ] Enqueue + dispatch email notifications
  - [ ] Confirmed
  - [ ] Reminder
  - [ ] Cancellation

## Phase 3 — SMS Channel
- [ ] Provider selection (Clickatell primary, Twilio optional)
- [ ] Credential storage (encrypted)
- [ ] Sender ID / From validation
- [ ] Test SMS
- [ ] Enqueue + dispatch SMS reminders

## Phase 4 — WhatsApp Channel (Meta Cloud API)
- [ ] Meta Cloud API integration + token handling
- [ ] Template reference storage (provider_template_id)
- [ ] Connection health checks
- [ ] Template-only enforcement
- [ ] Enqueue + dispatch WhatsApp
  - [ ] Confirmed
  - [ ] Reminder
  - [ ] Cancellation

## Phase 5 — Queue & Dispatch Engine (Harden)
- [ ] `notification_queue` table
- [ ] Cron dispatch command
- [ ] Retry/backoff
- [ ] Rate limiting / provider throttling
- [ ] Idempotency

## Phase 6 — Logs, Monitoring & Compliance
- [ ] Delivery logs UI
- [ ] Audit trail and correlation IDs
- [ ] Opt-in enforcement hooks
- [ ] Data retention + export
