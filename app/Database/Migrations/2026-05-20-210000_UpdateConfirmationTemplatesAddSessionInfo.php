<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

/**
 * Replace hardcoded location blocks in xs_message_templates with {session_info}.
 *
 * Targets all four event types that contained a location block in their
 * email and WhatsApp bodies:
 *   - appointment_pending
 *   - appointment_confirmed
 *   - appointment_reminder
 *   - appointment_rescheduled
 *
 * The {session_info} placeholder resolves to either a video meeting link
 * (online_zoom / online_jitsi) or the physical location (onsite) at send time.
 */
class Migration_UpdateConfirmationTemplatesAddSessionInfo extends MigrationBase
{
    public function up(): void
    {
        $eventTypes = [
            'appointment_pending',
            'appointment_confirmed',
            'appointment_reminder',
            'appointment_rescheduled',
        ];

        foreach ($eventTypes as $eventType) {
            // ── Email ─────────────────────────────────────────────────────────
            $emailRow = $this->db->table('xs_message_templates')
                ->where('event_type', $eventType)
                ->where('channel', 'email')
                ->get()
                ->getRowArray();

            if ($emailRow) {
                $emailBody = $emailRow['body'] ?? '';

                // Replace the old location block (two possible formats)
                $emailBody = str_replace(
                    "📍 Location:  {location_name}\n              {location_address}\n   Maps: {google_maps_link} | Waze: {waze_link}\n",
                    "{session_info}\n",
                    $emailBody
                );

                $this->db->table('xs_message_templates')
                    ->where('id', $emailRow['id'])
                    ->update(['body' => $emailBody]);
            }

            // ── WhatsApp ──────────────────────────────────────────────────────
            $waRow = $this->db->table('xs_message_templates')
                ->where('event_type', $eventType)
                ->where('channel', 'whatsapp')
                ->get()
                ->getRowArray();

            if ($waRow) {
                $waBody = $waRow['body'] ?? '';

                $waBody = str_replace(
                    '*📍 Location:* {location_name}, {location_address}',
                    '{session_info}',
                    $waBody
                );

                $this->db->table('xs_message_templates')
                    ->where('id', $waRow['id'])
                    ->update(['body' => $waBody]);
            }
        }
    }

    public function down(): void
    {
        // Reverting auto-formatted bodies would require knowing the original
        // content per business. This migration is intentionally not reversible.
    }
}
