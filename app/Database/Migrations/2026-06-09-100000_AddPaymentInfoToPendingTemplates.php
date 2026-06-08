<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

/**
 * Adds {payment_info} placeholder to the seeded appointment_pending templates
 * so customers are informed of an outstanding deposit when their booking is
 * awaiting payment confirmation.
 *
 * buildPaymentInfoBlock() now returns a "Deposit due" block for payment_status='pending'
 * (in addition to the existing "Deposit paid" block for payment_status='paid').
 * When no deposit applies, {payment_info} resolves to '' — templates are unaffected
 * for non-payment bookings.
 *
 * Template priority: xs_settings > xs_message_templates > DEFAULT_TEMPLATES (code)
 * This migration patches xs_settings rows so the change takes effect immediately.
 */
class AddPaymentInfoToPendingTemplates extends MigrationBase
{
    private const PENDING_EMAIL_KEY = 'notification_template.appointment_pending.email';
    private const PENDING_WA_KEY    = 'notification_template.appointment_pending.whatsapp';

    public function up(): void
    {
        $builder = $this->db->table('xs_settings');

        // ── 1. Patch appointment_pending email to include {payment_info} ─────────
        $row = $builder->where('setting_key', self::PENDING_EMAIL_KEY)->get()->getRow();
        if ($row) {
            $template = json_decode($row->setting_value ?? '{}', true) ?? [];
            $body = $template['body'] ?? '';
            if (strpos($body, '{payment_info}') === false) {
                // Insert after the booking reference line, before customer name
                $body = str_replace(
                    "\nBOOKING REFERENCE: #{booking_reference}\nName:",
                    "\nBOOKING REFERENCE: #{booking_reference}\n{payment_info}Name:",
                    $body
                );
                $template['body'] = $body;
                $builder->where('setting_key', self::PENDING_EMAIL_KEY)
                        ->update(['setting_value' => json_encode($template)]);
            }
        }

        // ── 2. Patch appointment_pending WhatsApp to include {payment_info} ─────
        $row = $builder->where('setting_key', self::PENDING_WA_KEY)->get()->getRow();
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
                $builder->where('setting_key', self::PENDING_WA_KEY)
                        ->update(['setting_value' => json_encode($template)]);
            }
        }
    }

    public function down(): void
    {
        // Non-destructive patch — {payment_info} resolves to '' for non-payment
        // bookings so templates degrade gracefully. No rollback needed.
    }
}
