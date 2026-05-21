<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

/**
 * Replace explicit location blocks in stored notification templates with {session_info}.
 *
 * Two targets:
 *
 * A) `settings` table — customer-facing email templates (V4 format):
 *    Replaces the three-line location block:
 *      📍 Location:  {location_name}
 *                    {location_address}
 *         Maps: {google_maps_link} | Waze: {waze_link}
 *    with {session_info}, which now renders the full block conditionally:
 *    — in-person → same multi-line location + Maps/Waze output
 *    — online    → 🎥 Online Session ({provider})\n   Join URL:  {video_link}
 *
 * B) `xs_message_templates` — internal provider/staff email templates:
 *    Replaces the compact single-line:
 *      Location: {location_name} {location_address}
 *    with {session_info}.
 *
 * The 2026-05-20-210000 migration attempted B for xs_message_templates but targeted
 * the customer email format (📍 Location:…), which never matched the internal template
 * format. This migration corrects that gap.
 */
class Migration_UpdateNotificationTemplatesOnlineSessionV5 extends MigrationBase
{
    // V4 location block string — stored in xs_settings with CRLF line endings
    private const LOCATION_BLOCK_OLD = "📍 Location:  {location_name}\r\n              {location_address}\r\n   Maps: {google_maps_link} | Waze: {waze_link}\r\n";
    private const LOCATION_BLOCK_NEW = "{session_info}\r\n";

    // Internal template location line
    private const INTERNAL_LOCATION_OLD = "Location: {location_name} {location_address}\n";
    private const INTERNAL_LOCATION_NEW = "{session_info}\n";

    public function up(): void
    {
        // ── A) Update customer email templates in `settings` table ────────────
        $settingsKeys = [
            'notification_template.appointment_confirmed.email',
            'notification_template.appointment_pending.email',
            'notification_template.appointment_reminder.email',
            'notification_template.appointment_rescheduled.email',
        ];

        foreach ($settingsKeys as $key) {
            $row = $this->db->table('settings')
                ->where('setting_key', $key)
                ->get()
                ->getRowArray();

            if (!$row) {
                continue;
            }

            $decoded = json_decode($row['setting_value'], true);
            if (!is_array($decoded) || !isset($decoded['body'])) {
                continue;
            }

            $body = str_replace(self::LOCATION_BLOCK_OLD, self::LOCATION_BLOCK_NEW, $decoded['body']);

            if ($body === $decoded['body']) {
                continue; // nothing matched — already updated or different format
            }

            $decoded['body'] = $body;
            $this->db->table('settings')
                ->where('setting_key', $key)
                ->update([
                    'setting_value' => json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'updated_at'    => date('Y-m-d H:i:s'),
                ]);
        }

        // ── B) Update internal email templates in `xs_message_templates` ─────
        $internalEventTypes = [
            'appointment_pending',
            'appointment_confirmed',
            'appointment_cancelled',
            'appointment_rescheduled',
            'appointment_reminder',
        ];

        foreach ($internalEventTypes as $eventType) {
            $row = $this->db->table('xs_message_templates')
                ->where('event_type', $eventType)
                ->where('channel', 'email')
                ->where('recipient_class', 'internal')
                ->get()
                ->getRowArray();

            if (!$row) {
                // Fallback: match without recipient_class filter for older rows
                $row = $this->db->table('xs_message_templates')
                    ->where('event_type', $eventType)
                    ->where('channel', 'email')
                    ->get()
                    ->getRowArray();
            }

            if (!$row) {
                continue;
            }

            $body = str_replace(self::INTERNAL_LOCATION_OLD, self::INTERNAL_LOCATION_NEW, $row['body'] ?? '');

            if ($body === ($row['body'] ?? '')) {
                continue; // nothing matched
            }

            $this->db->table('xs_message_templates')
                ->where('id', $row['id'])
                ->update(['body' => $body]);
        }
    }

    public function down(): void
    {
        // ── A) Restore customer email templates ───────────────────────────────
        $settingsKeys = [
            'notification_template.appointment_confirmed.email',
            'notification_template.appointment_pending.email',
            'notification_template.appointment_reminder.email',
            'notification_template.appointment_rescheduled.email',
        ];

        foreach ($settingsKeys as $key) {
            $row = $this->db->table('settings')
                ->where('setting_key', $key)
                ->get()
                ->getRowArray();

            if (!$row) {
                continue;
            }

            $decoded = json_decode($row['setting_value'], true);
            if (!is_array($decoded) || !isset($decoded['body'])) {
                continue;
            }

            $body = str_replace(self::LOCATION_BLOCK_NEW, self::LOCATION_BLOCK_OLD, $decoded['body']);

            if ($body === $decoded['body']) {
                continue;
            }

            $decoded['body'] = $body;
            $this->db->table('settings')
                ->where('setting_key', $key)
                ->update([
                    'setting_value' => json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'updated_at'    => date('Y-m-d H:i:s'),
                ]);
        }

        // ── B) Restore internal email templates ───────────────────────────────
        $internalEventTypes = [
            'appointment_pending',
            'appointment_confirmed',
            'appointment_cancelled',
            'appointment_rescheduled',
            'appointment_reminder',
        ];

        foreach ($internalEventTypes as $eventType) {
            $row = $this->db->table('xs_message_templates')
                ->where('event_type', $eventType)
                ->where('channel', 'email')
                ->where('recipient_class', 'internal')
                ->get()
                ->getRowArray();

            if (!$row) {
                $row = $this->db->table('xs_message_templates')
                    ->where('event_type', $eventType)
                    ->where('channel', 'email')
                    ->get()
                    ->getRowArray();
            }

            if (!$row) {
                continue;
            }

            $body = str_replace(self::INTERNAL_LOCATION_NEW, self::INTERNAL_LOCATION_OLD, $row['body'] ?? '');

            if ($body === ($row['body'] ?? '')) {
                continue;
            }

            $this->db->table('xs_message_templates')
                ->where('id', $row['id'])
                ->update(['body' => $body]);
        }
    }
}
