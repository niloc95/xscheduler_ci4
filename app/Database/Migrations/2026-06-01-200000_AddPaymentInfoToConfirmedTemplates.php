<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

/**
 * Adds {payment_info} placeholder to the seeded appointment_confirmed templates
 * so customers see their deposit amount and outstanding balance in the confirmation
 * email/WhatsApp when a deposit was required.
 *
 * Also seeds minimal payment_confirmed templates (customer + internal) used
 * when the payment webhook fires a dedicated receipt event.
 *
 * Template priority: xs_settings > xs_message_templates > DEFAULT_TEMPLATES (code)
 * This migration patches xs_settings so it takes effect immediately without
 * requiring users to reset their custom templates.
 */
class AddPaymentInfoToConfirmedTemplates extends MigrationBase
{
    private const CONFIRMED_EMAIL_KEY    = 'notification_template.appointment_confirmed.email';
    private const CONFIRMED_WA_KEY       = 'notification_template.appointment_confirmed.whatsapp';
    private const PAYMENT_EMAIL_KEY      = 'notification_template.payment_confirmed.email';
    private const PAYMENT_WA_KEY         = 'notification_template.payment_confirmed.whatsapp';

    public function up(): void
    {
        $builder = $this->db->table('xs_settings');

        // ── 1. Patch appointment_confirmed email to include {payment_info} ──────
        $row = $builder->where('setting_key', self::CONFIRMED_EMAIL_KEY)->get()->getRow();
        if ($row) {
            $template = json_decode($row->setting_value ?? '{}', true) ?? [];
            $body = $template['body'] ?? '';
            // Only inject if not already present
            if (strpos($body, '{payment_info}') === false) {
                // Insert before "Please arrive" line
                $body = str_replace(
                    "\nPlease arrive 5–10 minutes early.",
                    "\n{payment_info}\nPlease arrive 5–10 minutes early.",
                    $body
                );
                $template['body'] = $body;
                $builder->where('setting_key', self::CONFIRMED_EMAIL_KEY)
                        ->update(['setting_value' => json_encode($template)]);
            }
        }

        // ── 2. Patch appointment_confirmed WhatsApp to include {payment_info} ──
        $row = $builder->where('setting_key', self::CONFIRMED_WA_KEY)->get()->getRow();
        if ($row) {
            $template = json_decode($row->setting_value ?? '{}', true) ?? [];
            $body = $template['body'] ?? '';
            if (strpos($body, '{payment_info}') === false) {
                $body = str_replace(
                    "\n*Booking Ref:* #{booking_reference}\n",
                    "\n*Booking Ref:* #{booking_reference}\n{payment_info}\n",
                    $body
                );
                $template['body'] = $body;
                $builder->where('setting_key', self::CONFIRMED_WA_KEY)
                        ->update(['setting_value' => json_encode($template)]);
            }
        }

        // ── 3. Seed payment_confirmed customer email template ─────────────────
        $this->upsertSetting(
            self::PAYMENT_EMAIL_KEY,
            json_encode([
                'subject' => 'Payment Received — Deposit Confirmed for {appointment_date}',
                'body'    => "Hi {customer_first_name},\n\nWe've received your deposit payment. Your booking is now confirmed ✓\n\n── PAYMENT SUMMARY ──────────────────────\n{payment_info}\n── APPOINTMENT DETAILS ──────────────────\n📅 Date:      {appointment_date}\n🕐 Time:      {appointment_time}\n💼 Service:   {service_name}\n👤 Provider:  {provider_name}\n⏱  Duration: {service_duration} minutes\n{session_info}\n─────────────────────────────────────────\n\nBOOKING REFERENCE: #{booking_reference}\n\nPlease arrive 5–10 minutes early.\n\n── MANAGE YOUR APPOINTMENT ──────────────\nOpen secure link: {reschedule_link}\nAdd to Google Calendar: {calendar_link}\n\n{business_name}\n{terms_link} | {privacy_link}",
            ])
        );

        // ── 4. Seed payment_confirmed WhatsApp template ───────────────────────
        $this->upsertSetting(
            self::PAYMENT_WA_KEY,
            json_encode([
                'body' => "💳 *Payment Received*\n\nHi {customer_first_name}!\n\nYour deposit has been received and your booking is confirmed ✓\n\n{payment_info}\n*📅 Date:* {appointment_date}\n*🕐 Time:* {appointment_time}\n*💼 Service:* {service_name}\n*👤 Provider:* {provider_name}\n\n*Booking Ref:* #{booking_reference}\n\nView / Reschedule / Cancel: {reschedule_link}\n\n_{business_name}_",
            ])
        );
    }

    public function down(): void
    {
        $builder = $this->db->table('xs_settings');

        // Remove payment_confirmed templates
        $builder->whereIn('setting_key', [self::PAYMENT_EMAIL_KEY, self::PAYMENT_WA_KEY])->delete();

        // Note: we do NOT attempt to reverse the {payment_info} injection into
        // appointment_confirmed templates — the patch is non-destructive and
        // templates with unknown placeholders degrade gracefully (placeholder
        // resolves to empty string for non-payment bookings).
    }

    private function upsertSetting(string $key, string $value): void
    {
        $builder  = $this->db->table('xs_settings');
        $existing = $builder->where('setting_key', $key)->get()->getRow();
        if ($existing) {
            $builder->where('setting_key', $key)->update(['setting_value' => $value]);
        } else {
            $builder->insert([
                'setting_key'   => $key,
                'setting_value' => $value,
                'setting_type'  => 'string',
            ]);
        }
    }
}
