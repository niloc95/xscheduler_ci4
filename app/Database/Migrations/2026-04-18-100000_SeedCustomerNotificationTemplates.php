<?php

/**
 * @file    2026-04-18-100000_SeedCustomerNotificationTemplates.php
 * @brief   Seed rich customer-facing notification templates into xs_settings.
 *
 * Upserts all 15 customer template entries (5 events × 3 channels) using the
 * notification_template.{eventType}.{channel} key pattern.  Existing rows are
 * updated so admins who previously saved custom templates via the UI receive the
 * improved defaults.
 *
 * Template priority in NotificationTemplateService:
 *   xs_settings  >  xs_message_templates  >  DEFAULT_TEMPLATES (code constant)
 *
 * @package App\Database\Migrations
 */

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class SeedCustomerNotificationTemplates extends MigrationBase
{
    /** @var string[] setting keys written by this migration (used in down()) */
    private const KEYS = [
        'notification_template.appointment_pending.email',
        'notification_template.appointment_pending.sms',
        'notification_template.appointment_pending.whatsapp',
        'notification_template.appointment_confirmed.email',
        'notification_template.appointment_confirmed.sms',
        'notification_template.appointment_confirmed.whatsapp',
        'notification_template.appointment_reminder.email',
        'notification_template.appointment_reminder.sms',
        'notification_template.appointment_reminder.whatsapp',
        'notification_template.appointment_cancelled.email',
        'notification_template.appointment_cancelled.sms',
        'notification_template.appointment_cancelled.whatsapp',
        'notification_template.appointment_rescheduled.email',
        'notification_template.appointment_rescheduled.sms',
        'notification_template.appointment_rescheduled.whatsapp',
    ];

    /**
     * Rich customer-facing templates mirroring DEFAULT_TEMPLATES in
     * NotificationTemplateService.  Stored as JSON in xs_settings so they
     * take highest priority over the code-level fallback.
     */
    private function templates(): array
    {
        $pendingEmailBody = "Hi {customer_first_name},\n\nWe've received your booking request! We will confirm your appointment shortly.\n\n── APPOINTMENT DETAILS ──────────────────\n📅 Date:      {appointment_date}\n🕐 Time:      {appointment_time}\n💼 Service:   {service_name}\n👤 Provider:  {provider_name}\n⏱  Duration: {service_duration} minutes\n📍 Location:  {location_name}\n              {location_address}\n─────────────────────────────────────────\n\nBOOKING REFERENCE: #{booking_reference}\nName:    {customer_name}\nContact: {customer_phone} | {customer_email}\n\nWe will notify you as soon as your appointment is confirmed.\n\n{cancellation_policy}\n{rescheduling_policy}\n\n── MANAGE YOUR APPOINTMENT ──────────────\nView details / Reschedule / Cancel: {reschedule_link}\nAdd to Google Calendar: {calendar_link}\n\n{business_name}\n{terms_link} | {privacy_link}";

        $confirmedEmailBody = "Hi {customer_first_name},\n\nThank you for booking with {business_name}! Your appointment is confirmed ✓\n\n── APPOINTMENT DETAILS ──────────────────\n📅 Date:      {appointment_date}\n🕐 Time:      {appointment_time}\n💼 Service:   {service_name}\n👤 Provider:  {provider_name}\n⏱  Duration: {service_duration} minutes\n📍 Location:  {location_name}\n              {location_address}\n─────────────────────────────────────────\n\nBOOKING REFERENCE: #{booking_reference}\nName:    {customer_name}\nContact: {customer_phone} | {customer_email}\n\nPlease arrive 5–10 minutes early. Bring any relevant documentation.\n\n{cancellation_policy}\n{rescheduling_policy}\n\n── MANAGE YOUR APPOINTMENT ──────────────\nView details / Reschedule / Cancel: {reschedule_link}\nAdd to Google Calendar: {calendar_link}\n\n{business_name}\n{terms_link} | {privacy_link}";

        $reminderEmailBody = "Hi {customer_first_name},\n\nDon't forget — you have an upcoming appointment!\n\n── APPOINTMENT DETAILS ──────────────────\n📅 Date:      {appointment_date}\n🕐 Time:      {appointment_time}\n💼 Service:   {service_name}\n👤 Provider:  {provider_name}\n⏱  Duration: {service_duration} minutes\n📍 Location:  {location_name}\n              {location_address}\n─────────────────────────────────────────\n\nBOOKING REFERENCE: #{booking_reference}\nName:    {customer_name}\nContact: {customer_phone} | {customer_email}\n\nPlease arrive 5–10 minutes early. Contact us if your plans change.\n\n{rescheduling_policy}\n\n── MANAGE YOUR APPOINTMENT ──────────────\nView details / Reschedule / Cancel: {reschedule_link}\nAdd to Google Calendar: {calendar_link}\n\n{business_name}\n{terms_link} | {privacy_link}";

        $cancelledEmailBody = "Hi {customer_first_name},\n\nYour appointment has been cancelled.\n\n── APPOINTMENT DETAILS ──────────────────\n📅 Date:      {appointment_date}\n🕐 Time:      {appointment_time}\n💼 Service:   {service_name}\n👤 Provider:  {provider_name}\n─────────────────────────────────────────\n\nBOOKING REFERENCE: #{booking_reference}\n\nWe hope to see you again soon! Book a new appointment:\n{booking_url}\n\n{business_name}\n{terms_link} | {privacy_link}";

        $rescheduledEmailBody = "Hi {customer_first_name},\n\nYour appointment has been moved to a new date and time.\n\n── NEW DATE & TIME ───────────────────────\n📅 Date:      {appointment_date}\n🕐 Time:      {appointment_time}\n💼 Service:   {service_name}\n👤 Provider:  {provider_name}\n⏱  Duration: {service_duration} minutes\n📍 Location:  {location_name}\n              {location_address}\n─────────────────────────────────────────\n\nBOOKING REFERENCE: #{booking_reference}\nName:    {customer_name}\nContact: {customer_phone} | {customer_email}\n\n{rescheduling_policy}\n\n── MANAGE YOUR APPOINTMENT ──────────────\nView details / Reschedule / Cancel: {reschedule_link}\nAdd to Google Calendar: {calendar_link}\n\n{business_name}\n{terms_link} | {privacy_link}";

        return [
            // ── appointment_pending ─────────────────────────────────────────
            'notification_template.appointment_pending.email' => [
                'subject' => 'Your Appointment Request is Received — {service_name}',
                'body'    => $pendingEmailBody,
            ],
            'notification_template.appointment_pending.sms' => [
                'body' => "⏳ Booking received! {service_name} on {appointment_date} at {appointment_time}. Pending confirmation. Ref #{booking_reference}. Manage: {reschedule_link}",
            ],
            'notification_template.appointment_pending.whatsapp' => [
                'body' => "⏳ *Appointment Request Received*\n\nHi {customer_first_name}!\n\nWe've received your booking request and will confirm your appointment shortly.\n\n*📅 Date:* {appointment_date}\n*🕐 Time:* {appointment_time}\n*💼 Service:* {service_name}\n*👤 Provider:* {provider_name}\n*⏱ Duration:* {service_duration} minutes\n*📍 Location:* {location_name}, {location_address}\n\n*Booking Ref:* #{booking_reference}\n\n{cancellation_policy}\n\nView / Reschedule / Cancel: {reschedule_link}\nAdd to Calendar: {calendar_link}\n\nWe will notify you once confirmed.\n\n_{business_name}_\n{terms_link} | {privacy_link}",
            ],

            // ── appointment_confirmed ───────────────────────────────────────
            'notification_template.appointment_confirmed.email' => [
                'subject' => 'Your Appointment is Confirmed — {appointment_date} at {appointment_time}',
                'body'    => $confirmedEmailBody,
            ],
            'notification_template.appointment_confirmed.sms' => [
                'body' => "✅ Confirmed: {service_name} with {provider_name} on {appointment_date} at {appointment_time}. Ref #{booking_reference}. Manage: {reschedule_link}",
            ],
            'notification_template.appointment_confirmed.whatsapp' => [
                'body' => "✅ *Appointment Confirmed*\n\nHi {customer_first_name}!\n\nThank you for booking with {business_name}! Your appointment is confirmed ✓\n\n*📅 Date:* {appointment_date}\n*🕐 Time:* {appointment_time}\n*💼 Service:* {service_name}\n*👤 Provider:* {provider_name}\n*⏱ Duration:* {service_duration} minutes\n*📍 Location:* {location_name}, {location_address}\n\n*Booking Ref:* #{booking_reference}\n\n{cancellation_policy}\n\nView / Reschedule / Cancel: {reschedule_link}\nAdd to Calendar: {calendar_link}\n\n_{business_name}_\n{terms_link} | {privacy_link}",
            ],

            // ── appointment_reminder ────────────────────────────────────────
            'notification_template.appointment_reminder.email' => [
                'subject' => 'Reminder: Your Appointment — {appointment_date} at {appointment_time}',
                'body'    => $reminderEmailBody,
            ],
            'notification_template.appointment_reminder.sms' => [
                'body' => "⏰ Reminder: {service_name} with {provider_name} on {appointment_date} at {appointment_time}. {location_name}. Manage: {reschedule_link}",
            ],
            'notification_template.appointment_reminder.whatsapp' => [
                'body' => "⏰ *Appointment Reminder*\n\nHi {customer_first_name}!\n\nDon't forget — you have an upcoming appointment!\n\n*📅 Date:* {appointment_date}\n*🕐 Time:* {appointment_time}\n*💼 Service:* {service_name}\n*👤 Provider:* {provider_name}\n*⏱ Duration:* {service_duration} minutes\n*📍 Location:* {location_name}, {location_address}\n\n*Booking Ref:* #{booking_reference}\n\nPlease arrive 5–10 minutes early. Contact us if your plans change.\n\nView / Reschedule / Cancel: {reschedule_link}\nAdd to Calendar: {calendar_link}\n\n_{business_name}_",
            ],

            // ── appointment_cancelled ───────────────────────────────────────
            'notification_template.appointment_cancelled.email' => [
                'subject' => 'Your Appointment Has Been Cancelled — {service_name} on {appointment_date}',
                'body'    => $cancelledEmailBody,
            ],
            'notification_template.appointment_cancelled.sms' => [
                'body' => "❌ Cancelled: {service_name} on {appointment_date}. Rebook: {booking_url}",
            ],
            'notification_template.appointment_cancelled.whatsapp' => [
                'body' => "❌ *Appointment Cancelled*\n\nHi {customer_first_name},\n\nYour appointment has been cancelled.\n\n*📅 Date:* {appointment_date}\n*🕐 Time:* {appointment_time}\n*💼 Service:* {service_name}\n*👤 Provider:* {provider_name}\n\n*Booking Ref:* #{booking_reference}\n\nWe hope to see you again soon! Book a new appointment:\n{booking_url}\n\n_{business_name}_",
            ],

            // ── appointment_rescheduled ─────────────────────────────────────
            'notification_template.appointment_rescheduled.email' => [
                'subject' => 'Your Appointment Has Been Rescheduled — {appointment_date} at {appointment_time}',
                'body'    => $rescheduledEmailBody,
            ],
            'notification_template.appointment_rescheduled.sms' => [
                'body' => "📅 Rescheduled: {service_name} is now {appointment_date} at {appointment_time}. Ref #{booking_reference}. Manage: {reschedule_link}",
            ],
            'notification_template.appointment_rescheduled.whatsapp' => [
                'body' => "📅 *Appointment Rescheduled*\n\nHi {customer_first_name}!\n\nYour appointment has been moved to a new date and time.\n\n*New Date & Time*\n*📅 Date:* {appointment_date}\n*🕐 Time:* {appointment_time}\n*💼 Service:* {service_name}\n*👤 Provider:* {provider_name}\n*⏱ Duration:* {service_duration} minutes\n*📍 Location:* {location_name}, {location_address}\n\n*Booking Ref:* #{booking_reference}\n\nView / Reschedule / Cancel: {reschedule_link}\nAdd to Calendar: {calendar_link}\n\n_{business_name}_",
            ],
        ];
    }

    /**
     * Upsert all 15 customer notification templates into xs_settings.
     *
     * Existing rows are updated so environments where an admin has previously
     * saved templates via the UI also receive the improved content.
     */
    public function up(): void
    {
        $builder  = $this->db->table('settings');
        $now      = date('Y-m-d H:i:s');

        foreach ($this->templates() as $key => $template) {
            $value    = json_encode($template, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $existing = $builder->where('setting_key', $key)->get()->getRow();

            if ($existing) {
                $builder->where('setting_key', $key)->update([
                    'setting_value' => $value,
                    'setting_type'  => 'json',
                    'updated_at'    => $now,
                ]);
            } else {
                $builder->insert([
                    'setting_key'   => $key,
                    'setting_value' => $value,
                    'setting_type'  => 'json',
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]);
            }
        }
    }

    /**
     * Remove the seeded customer template rows so the system falls back to the
     * DEFAULT_TEMPLATES code constant.
     */
    public function down(): void
    {
        $this->db->table('settings')
            ->whereIn('setting_key', self::KEYS)
            ->delete();
    }
}
