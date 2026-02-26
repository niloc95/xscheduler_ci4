<?php

/**
 * =============================================================================
 * APPOINTMENT FORMATTER SERVICE
 * =============================================================================
 *
 * @file        app/Services/Appointment/AppointmentFormatterService.php
 * @description Transforms raw appointment DB rows into normalized API response
 *              shapes for calendar and list clients.
 *
 * This service is a pure transformer — no DB access, no business logic.
 * It only converts data from one format to another.
 *
 * Audit fix: RISK-01 (fat controller) — inline array_map transformation extracted here.
 *
 * KEY METHODS:
 * ─────────────────────────────────────────────────────────────────
 * - formatForCalendar(row)         → Single appointment for calendar renderer
 * - formatManyForCalendar(rows)    → Array of appointments for calendar
 * - formatForDetail(row)           → Full detail view (includes all fields)
 *
 * CANONICAL OUTPUT SHAPE (formatForCalendar):
 * ─────────────────────────────────────────────────────────────────
 * {
 *   id, hash, title, start (ISO), end (ISO),
 *   provider_id, service_id, customer_id, status,
 *   name, service_name, provider_name, provider_color,
 *   service_duration, service_price, buffer_before, buffer_after,
 *   email, phone, notes,
 *   location_id, location_name, location_address, location_contact
 * }
 *
 * @package     App\Services\Appointment
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

namespace App\Services\Appointment;

class AppointmentFormatterService
{
    private const DEFAULT_PROVIDER_COLOR = '#3B82F6';

    // ─────────────────────────────────────────────────────────────────
    // PUBLIC API
    // ─────────────────────────────────────────────────────────────────

    /**
     * Format an array of raw DB rows for the calendar renderer.
     */
    public function formatManyForCalendar(array $rows): array
    {
        return array_map([$this, 'formatForCalendar'], $rows);
    }

    /**
     * Format a single raw DB row into the canonical calendar event shape.
     *
     * Fields follow snake_case convention (API canonical format).
     * ISO 8601 datetimes are returned for start/end.
     */
    public function formatForCalendar(array $row): array
    {
        return [
            'id'              => (int) $row['id'],
            'hash'            => $row['hash'] ?? null,
            'title'           => $row['customer_name'] ?? ('Appointment #' . $row['id']),
            'start'           => $this->toIso($row['start_time'] ?? null),
            'end'             => $this->toIso($row['end_time']   ?? null),
            // Core foreign keys
            'provider_id'     => (int) $row['provider_id'],
            'service_id'      => (int) $row['service_id'],
            'customer_id'     => isset($row['customer_id']) ? (int) $row['customer_id'] : null,
            'status'          => $row['status'] ?? 'pending',
            // Display names
            'name'            => $row['customer_name'] ?? null,
            'service_name'    => $row['service_name']  ?? null,
            'provider_name'   => $row['provider_name'] ?? null,
            'provider_color'  => $row['provider_color'] ?? self::DEFAULT_PROVIDER_COLOR,
            // Service config
            'service_duration'  => isset($row['service_duration']) ? (int) $row['service_duration'] : null,
            'service_price'     => isset($row['service_price']) ? (float) $row['service_price'] : null,
            'buffer_before'     => isset($row['service_buffer_before']) ? (int) $row['service_buffer_before'] : null,
            'buffer_after'      => isset($row['service_buffer_after'])  ? (int) $row['service_buffer_after']  : null,
            // Customer contact
            'email'  => $row['customer_email'] ?? null,
            'phone'  => $row['customer_phone'] ?? null,
            'notes'  => $row['notes'] ?? null,
            // Location snapshot
            'location_id'      => isset($row['location_id'])      ? (int) $row['location_id'] : null,
            'location_name'    => $row['location_name']    ?? null,
            'location_address' => $row['location_address'] ?? null,
            'location_contact' => $row['location_contact'] ?? null,
        ];
    }

    /**
     * Format for full detail API response (includes audit-style fields).
     */
    public function formatForDetail(array $row): array
    {
        $base = $this->formatForCalendar($row);
        $base['created_at']   = $row['created_at']   ?? null;
        $base['updated_at']   = $row['updated_at']   ?? null;
        $base['reminder_sent'] = (bool) ($row['reminder_sent'] ?? false);
        $base['public_token'] = $row['public_token'] ?? null;
        return $base;
    }

    // ─────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────

    /**
     * Convert a MySQL datetime string to ISO 8601.
     * Returns null if input is null/empty.
     */
    private function toIso(?string $datetime): ?string
    {
        if (!$datetime) {
            return null;
        }
        try {
            $dt = new \DateTime($datetime);
            return $dt->format('Y-m-d\TH:i:s');
        } catch (\Throwable $e) {
            return $datetime;
        }
    }
}
