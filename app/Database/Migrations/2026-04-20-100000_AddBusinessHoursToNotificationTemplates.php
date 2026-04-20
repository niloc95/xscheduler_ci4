<?php

/**
 * @file    2026-04-20-100000_AddBusinessHoursToNotificationTemplates.php
 * @brief   Add {business_hours} placeholder to all 5 customer-facing email templates.
 *
 * Upserts the 5 email template setting keys with bodies that include the new
 * {business_hours} block (displayed after enquiries contact line, before the
 * bottom separator).  SMS and WhatsApp bodies are unchanged.
 *
 * The {business_hours} placeholder is resolved at render time by
 * NotificationTemplateService::buildBusinessHoursText() from xs_business_hours
 * (global/default rows where provider_id = 0 OR NULL).
 *
 * @package App\Database\Migrations
 */

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class AddBusinessHoursToNotificationTemplates extends MigrationBase
{
    /** @var string[] keys updated by this migration */
    private const KEYS = [
        'notification_template.appointment_pending.email',
        'notification_template.appointment_confirmed.email',
        'notification_template.appointment_reminder.email',
        'notification_template.appointment_cancelled.email',
        'notification_template.appointment_rescheduled.email',
    ];

    private function templates(): array
    {
        $pendingEmailBody = "Hi {customer_first_name},\n\nWe've received your booking request! We will confirm your appointment shortly.\n\n── APPOINTMENT DETAILS ──────────────────\n📅 Date:      {appointment_date}\n🕐 Time:      {appointment_time}\n💼 Service:   {service_name}\n👤 Provider:  {provider_name}\n⏱  Duration: {service_duration} minutes\n📍 Location:  {location_name}\n              {location_address}\n   Maps: {google_maps_link} | Waze: {waze_link}\n☎ Enquiries: {business_email} | Tel: {business_phone}\n\n🕰 Business Hours:\n{business_hours}\n─────────────────────────────────────────\n\nBOOKING REFERENCE: #{booking_reference}\nName:    {customer_name}\nContact: {customer_phone} | {customer_email}\n\nWe will notify you as soon as your appointment is confirmed.\n\n{cancellation_policy}\n{rescheduling_policy}\n\n── MANAGE YOUR APPOINTMENT ──────────────\nOpen secure link: {reschedule_link}\nIf the link is not clickable, copy and paste this URL:\n{reschedule_link}\nAdd to Google Calendar: {calendar_link}\n\n{business_name}\n{terms_link} | {privacy_link}";

        $confirmedEmailBody = "Hi {customer_first_name},\n\nThank you for booking with {business_name}! Your appointment is confirmed ✓\n\n── APPOINTMENT DETAILS ──────────────────\n📅 Date:      {appointment_date}\n🕐 Time:      {appointment_time}\n💼 Service:   {service_name}\n👤 Provider:  {provider_name}\n⏱  Duration: {service_duration} minutes\n📍 Location:  {location_name}\n              {location_address}\n   Maps: {google_maps_link} | Waze: {waze_link}\n☎ Enquiries: {business_email} | Tel: {business_phone}\n\n🕰 Business Hours:\n{business_hours}\n─────────────────────────────────────────\n\nBOOKING REFERENCE: #{booking_reference}\nName:    {customer_name}\nContact: {customer_phone} | {customer_email}\n\nPlease arrive 5–10 minutes early. Bring any relevant documentation.\n\n{cancellation_policy}\n{rescheduling_policy}\n\n── MANAGE YOUR APPOINTMENT ──────────────\nOpen secure link: {reschedule_link}\nIf the link is not clickable, copy and paste this URL:\n{reschedule_link}\nAdd to Google Calendar: {calendar_link}\n\n{business_name}\n{terms_link} | {privacy_link}";

        $reminderEmailBody = "Hi {customer_first_name},\n\nDon't forget — you have an upcoming appointment!\n\n── APPOINTMENT DETAILS ──────────────────\n📅 Date:      {appointment_date}\n🕐 Time:      {appointment_time}\n💼 Service:   {service_name}\n👤 Provider:  {provider_name}\n⏱  Duration: {service_duration} minutes\n📍 Location:  {location_name}\n              {location_address}\n   Maps: {google_maps_link} | Waze: {waze_link}\n☎ Enquiries: {business_email} | Tel: {business_phone}\n\n🕰 Business Hours:\n{business_hours}\n─────────────────────────────────────────\n\nBOOKING REFERENCE: #{booking_reference}\nName:    {customer_name}\nContact: {customer_phone} | {customer_email}\n\nPlease arrive 5–10 minutes early. Contact us if your plans change.\n\n{rescheduling_policy}\n\n── MANAGE YOUR APPOINTMENT ──────────────\nOpen secure link: {reschedule_link}\nIf the link is not clickable, copy and paste this URL:\n{reschedule_link}\nAdd to Google Calendar: {calendar_link}\n\n{business_name}\n{terms_link} | {privacy_link}";

        $cancelledEmailBody = "Hi {customer_first_name},\n\nYour appointment has been cancelled.\n\n── APPOINTMENT DETAILS ──────────────────\n📅 Date:      {appointment_date}\n🕐 Time:      {appointment_time}\n💼 Service:   {service_name}\n👤 Provider:  {provider_name}\n☎ Enquiries: {business_email} | Tel: {business_phone}\n\n🕰 Business Hours:\n{business_hours}\n─────────────────────────────────────────\n\nBOOKING REFERENCE: #{booking_reference}\n\nWe hope to see you again soon!\nOpen booking page: {booking_url}\nIf the link is not clickable, copy and paste this URL:\n{booking_url}\n\n{business_name}\n{terms_link} | {privacy_link}";

        $rescheduledEmailBody = "Hi {customer_first_name},\n\nYour appointment has been moved to a new date and time.\n\n── NEW DATE & TIME ───────────────────────\n📅 Date:      {appointment_date}\n🕐 Time:      {appointment_time}\n💼 Service:   {service_name}\n👤 Provider:  {provider_name}\n⏱  Duration: {service_duration} minutes\n📍 Location:  {location_name}\n              {location_address}\n   Maps: {google_maps_link} | Waze: {waze_link}\n☎ Enquiries: {business_email} | Tel: {business_phone}\n\n🕰 Business Hours:\n{business_hours}\n─────────────────────────────────────────\n\nBOOKING REFERENCE: #{booking_reference}\nName:    {customer_name}\nContact: {customer_phone} | {customer_email}\n\n{rescheduling_policy}\n\n── MANAGE YOUR APPOINTMENT ──────────────\nOpen secure link: {reschedule_link}\nIf the link is not clickable, copy and paste this URL:\n{reschedule_link}\nAdd to Google Calendar: {calendar_link}\n\n{business_name}\n{terms_link} | {privacy_link}";

        return [
            'notification_template.appointment_pending.email' => [
                'subject' => 'Your Appointment Request is Received — {service_name}',
                'body'    => $pendingEmailBody,
            ],
            'notification_template.appointment_confirmed.email' => [
                'subject' => 'Your Appointment is Confirmed — {appointment_date} at {appointment_time}',
                'body'    => $confirmedEmailBody,
            ],
            'notification_template.appointment_reminder.email' => [
                'subject' => 'Reminder: Your Appointment — {appointment_date} at {appointment_time}',
                'body'    => $reminderEmailBody,
            ],
            'notification_template.appointment_cancelled.email' => [
                'subject' => 'Your Appointment Has Been Cancelled — {service_name} on {appointment_date}',
                'body'    => $cancelledEmailBody,
            ],
            'notification_template.appointment_rescheduled.email' => [
                'subject' => 'Your Appointment Has Been Rescheduled — {appointment_date} at {appointment_time}',
                'body'    => $rescheduledEmailBody,
            ],
        ];
    }

    public function up(): void
    {
        $db = \Config\Database::connect();

        foreach ($this->templates() as $key => $value) {
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $existing = $db->table('xs_settings')
                ->where('setting_key', $key)
                ->get()
                ->getRowArray();

            if ($existing) {
                $db->table('xs_settings')->where('setting_key', $key)->update([
                    'setting_value' => $encoded,
                    'setting_type'  => 'json',
                    'updated_at'    => date('Y-m-d H:i:s'),
                ]);
            } else {
                $db->table('xs_settings')->insert([
                    'setting_key'   => $key,
                    'setting_value' => $encoded,
                    'setting_type'  => 'json',
                    'created_at'    => date('Y-m-d H:i:s'),
                    'updated_at'    => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Intentionally left as no-op: rolling back template content is
        // destructive; admins would lose any custom edits made since migration.
    }
}
