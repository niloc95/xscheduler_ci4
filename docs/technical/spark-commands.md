# Spark Commands Reference

All custom CLI commands live in `app/Commands/`. Run them from the project root with `php spark <command>`.

---

## Group: `notifications`

### `notifications:dispatch-queue`

**File:** `app/Commands/DispatchNotificationQueue.php`  
**Intended use:** Cron job. Primary production command for reminder delivery.

Runs two phases in sequence:

1. **Enqueue due reminders** — calls `NotificationQueueService::enqueueDueReminders($businessId)`. Scans upcoming appointments, writes `appointment_reminder` queue rows for offsets that have passed their `dueAt` threshold.
2. **Dispatch queue** — calls `NotificationQueueDispatcher::dispatch($businessId, $limit)`. Claims and sends queued rows across all channels.

```bash
php spark notifications:dispatch-queue [businessId] [limit]
```

| Argument | Default | Description |
|---|---|---|
| `businessId` | `1` | Business to process |
| `limit` | `100` | Max rows dispatched per run |

**Output fields:** `Scanned`, `Enqueued`, `Skipped` (enqueue phase); `Claimed`, `Sent`, `Cancelled`, `Failed`, `Skipped` (dispatch phase).

**Cron example:**
```
* * * * * cd /var/www/html && php spark notifications:dispatch-queue >> /var/log/webschedulr-notifications.log 2>&1
```

---

### `notifications:backfill-templates`

**File:** `app/Commands/BackfillNotificationTemplates.php`  
**Intended use:** One-off data repair after upgrading from V2/V3 to V4 email templates.

Compares stored `notification_template.*` settings rows against a hardcoded list of known legacy template bodies. Where a match is found, replaces the body with the current `NotificationTemplateService::DEFAULT_TEMPLATES` version.

Runs in **dry-run mode by default**. Pass `--apply` to write changes.

```bash
php spark notifications:backfill-templates           # dry-run
php spark notifications:backfill-templates --apply   # apply
```

**Output fields:** `Scanned`, `Matched legacy`, `Updated`, `Skipped`.

**When to use:** If the Settings → Notifications page shows V2-era template bodies without `{business_phone}`, `{google_maps_link}`, or `{waze_link}` placeholders.

---

### `notifications:export-delivery-logs`

**File:** `app/Commands/ExportNotificationDeliveryLogs.php`  
**Intended use:** Ops/audit — export delivery history to CSV.

Writes `xs_notification_delivery_logs` rows to a CSV file. Output path defaults to `writable/exports/notification_delivery_logs_<timestamp>.csv`.

```bash
php spark notifications:export-delivery-logs [businessId] [days] [outPath]
```

| Argument | Default | Description |
|---|---|---|
| `businessId` | `1` | Business to export |
| `days` | `30` | Lookback window |
| `outPath` | Auto-generated in `writable/exports/` | Absolute or relative output path |

**CSV columns:** `created_at`, `status`, `channel`, `event_type`, `attempt`, `recipient`, `provider`, `appointment_id`, `queue_id`, `correlation_id`, `error_message`.

---

### `notifications:purge-delivery-logs`

**File:** `app/Commands/PurgeNotificationDeliveryLogs.php`  
**Intended use:** Scheduled maintenance to control `xs_notification_delivery_logs` table size.

Deletes delivery log rows older than N days for the specified business.

```bash
php spark notifications:purge-delivery-logs [businessId] [days]
```

| Argument | Default | Description |
|---|---|---|
| `businessId` | `1` | Business to purge |
| `days` | `90` | Retention period. Rows older than this are deleted. |

No dry-run mode. Confirm retention policy before running in production.

---

### `notifications:repair-business-id`

**File:** `app/Commands/RepairNotificationBusinessId.php`  
**Intended use:** One-off data repair for environments where notification settings were accidentally saved under the wrong `business_id`.

Scans `xs_business_notification_rules` and `xs_business_integrations` for rows with foreign `business_id` values. For each foreign ID found, migrates rows to the canonical ID:

- **Rules**: If no target row exists → insert copy. If target row exists but is disabled and source is enabled → promote. Otherwise skip.
- **Integrations**: Same strategy — insert if absent, promote if inactive+source active, skip if target already configured. Copies `encrypted_config` fields where source has values and target does not.

Runs in **dry-run mode by default**.

```bash
php spark notifications:repair-business-id                      # dry-run, auto-detect
php spark notifications:repair-business-id --apply              # apply, auto-detect
php spark notifications:repair-business-id --from=101211 --to=1 --apply
```

| Option | Description |
|---|---|
| `--from=<id>` | Source (bad) business_id. Auto-detected from DB if omitted. |
| `--to=<id>` | Target (canonical) business_id. Defaults to `1`. |
| `--apply` | Execute changes. Without this flag, no writes occur. |

**After applying:** run `php spark notifications:dispatch-queue <toId>` to verify the pipeline works correctly.

---

### `notifications:test-reminder`

**File:** `app/Commands/TestAppointmentReminder.php`  
**Environment:** **Development only.** Refused in non-development environments.

Forces an existing appointment into a reminder-due window by rewriting its `start_at` and `end_at` to `now + N minutes`. Resets `reminder_sent = 0`. Then runs the full enqueue + dispatch cycle and shows per-row queue output.

```bash
php spark notifications:test-reminder <appointmentId> [businessId] [minutesUntilStart]
```

| Argument | Default | Description |
|---|---|---|
| `appointmentId` | Required | Appointment to test against |
| `businessId` | `1` | Business context |
| `minutesUntilStart` | `45` | How far in the future to set `start_at` |

**Example — set appointment 96 to start 45 minutes from now:**
```bash
php spark notifications:test-reminder 96 1 45
```

**Output includes:** each queue row with `channel`, `recipient_type`, `recipient_user_id`, `reminder_offset_minutes`, `status`, `attempts`, `run_after`, and first 12 chars of `schedule_fingerprint`.

**Healthy output:** one row per offset per recipient, all `status=sent`, `Cancelled: 0`. A `status=cancelled` row means the `schedule_fingerprint` no longer matched the live appointment at dispatch time — this is correct behavior for stale reminders.

Check Mailpit at `http://localhost:8025` for delivered email reminders.

---

## Group: `audit`

### `audit:provider-assignments`

**File:** `app/Commands/AuditProviderAssignments.php`  
**Intended use:** Pre-release gate check before shipping dashboard or provider-card changes (see `Agent_Context_v2.md §Rule #5`).

Queries `xs_user_roles` (authoritative provider roster), `xs_providers_services`, and `xs_locations` to produce a three-section report:

1. **Provider → Services / Locations** — per-provider count and names; flags providers with no services or no locations.
2. **Service → Providers** — per-service provider assignments; flags services with no assigned providers.
3. **Findings summary** — counts for providers missing services, providers missing locations, services without providers.

```bash
php spark audit:provider-assignments [serviceNameFilter]
```

| Argument | Default | Description |
|---|---|---|
| `serviceNameFilter` | (none) | Optional case-insensitive substring filter for the Service → Providers section |

**Release gate:** Any provider without services or any service without providers must be resolved or explicitly acknowledged before shipping provider-card changes.

---

### `audit:reminder-pipeline`

**File:** `app/Commands/AuditReminderPipeline.php`  
**Intended use:** Diagnose reminder automation health in any environment.

Reports:

1. **Rules** — all `appointment_reminder` rules for the business: channel, enabled state, `reminder_offset_minutes` (legacy single-value), `reminder_offsets_json` (current multi-offset array).
2. **Integrations** — channel integrations and their active state.
3. **Queue status** — count of queued reminder rows, count sent today (UTC midnight boundary).
4. **Live probe** — runs `enqueueDueReminders()` + `dispatch()` with a limit of 50 reminder rows and shows enqueue/dispatch stats.

```bash
php spark audit:reminder-pipeline [businessId]
```

| Argument | Default | Description |
|---|---|---|
| `businessId` | `1` | Business to audit |

**Note:** The live probe is not a dry-run — it will enqueue and dispatch real notifications. Run in development or staging, or during a low-traffic window.

---

### `audit:purge-logs`

**File:** `app/Commands/PurgeAuditLogs.php`  
**Intended use:** Scheduled maintenance to control `xs_audit_logs` table size.

Deletes audit log rows with `created_at` older than N days.

```bash
php spark audit:purge-logs [days] [--dry-run]
```

| Argument / Option | Default | Description |
|---|---|---|
| `days` | `365` | Retention period |
| `--dry-run` | Off | Count matching rows without deleting |

**Example — preview what would be deleted:**
```bash
php spark audit:purge-logs 180 --dry-run
```

---

## Summary Table

| Command | Group | Safe to run in prod? | Dry-run available? |
|---|---|---|---|
| `notifications:dispatch-queue` | notifications | Yes (cron) | No |
| `notifications:backfill-templates` | notifications | Yes | Yes (default) |
| `notifications:export-delivery-logs` | notifications | Yes | N/A (read-only) |
| `notifications:purge-delivery-logs` | notifications | Yes | No |
| `notifications:repair-business-id` | notifications | Yes | Yes (default) |
| `notifications:test-reminder` | notifications | **No** (dev only) | No |
| `audit:provider-assignments` | audit | Yes | N/A (read-only) |
| `audit:reminder-pipeline` | audit | Caution (live probe) | No |
| `audit:purge-logs` | audit | Yes | Yes |
