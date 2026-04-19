<?php

/**
 * @file    2026-04-19-110000_UpdateCustomerNotificationTemplatesV3.php
 * @brief   Add {business_phone} to the "For enquiries" footer in all 5 customer email bodies.
 *
 * Before: For enquiries: {business_email}
 * After:  For enquiries: {business_email} | Tel: {business_phone}
 *
 * Requires migrations 2026-04-18-100000 and 2026-04-19-100000 to have run first.
 *
 * @package App\Database\Migrations
 */

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class UpdateCustomerNotificationTemplatesV3 extends MigrationBase
{
    private const EMAIL_KEYS = [
        'notification_template.appointment_pending.email',
        'notification_template.appointment_confirmed.email',
        'notification_template.appointment_reminder.email',
        'notification_template.appointment_cancelled.email',
        'notification_template.appointment_rescheduled.email',
    ];

    private function v3Templates(): array
    {
        $pendingEmailBody = "Hi {customer_first_name},\n\nWe've received your booking request! We will confirm your appointment shortly.\n\n── APPOINTMENT DETAILS ──────────────────\n📅 Date:      {appointment_date}\n🕐 Time:      {appointment_time}\n💼 Service:   {service_name}\n👤 Provider:  {provider_name}\n⏱  Duration: {service_duration} minutes\n📍 Location:  {location_name}\n              {location_address}\n   Maps: {google_maps_link} | Waze: {waze_link}\n─────────────────────────────────────────\n\nBOOKING REFERENCE: #{booking_reference}\nName:    {customer_name}\nContact: {customer_phone} | {customer_email}\n\nWe will notify you as soon as your appointment is confirmed.\n\n{cancellation_policy}\n{rescheduling_policy}\n\n── MANAGE YOUR APPOINTMENT ──────────────\nView details / Reschedule / Cancel: {reschedule_link}\nAdd to Google Calendar: {calendar_link}\n\nFor enquiries: {business_email} | Tel: {business_phone}\n{business_name}\n{terms_link} | {privacy_link}";

        $confirmedEmailBody = "Hi {customer_first_name},\n\nThank you for booking with {business_name}! Your appointment is confirmed ✓\n\n── APPOINTMENT DETAILS ──────────────────\n📅 Date:      {appointment_date}\n🕐 Time:      {appointment_time}\n💼 Service:   {service_name}\n👤 Provider:  {provider_name}\n⏱  Duration: {service_duration} minutes\n📍 Location:  {location_name}\n              {location_address}\n   Maps: {google_maps_link} | Waze: {waze_link}\n─────────────────────────────────────────\n\nBOOKING REFERENCE: #{booking_reference}\nName:    {customer_name}\nContact: {customer_phone} | {customer_email}\n\nPlease arrive 5–10 minutes early. Bring any relevant documentation.\n\n{cancellation_policy}\n{rescheduling_policy}\n\n── MANAGE YOUR APPOINTMENT ──────────────\nView details / Reschedule / Cancel: {reschedule_link}\nAdd to Google Calendar: {calendar_link}\n\nFor enquiries: {business_email} | Tel: {business_phone}\n{business_name}\n{terms_link} | {privacy_link}";

        $reminderEmailBody = "Hi {customer_first_name},\n\nDon't forget — you have an upcoming appointment!\n\n── APPOINTMENT DETAILS ──────────────────\n📅 Date:      {appointment_date}\n🕐 Time:      {appointment_time}\n💼 Service:   {service_name}\n👤 Provider:  {provider_name}\n⏱  Duration: {service_duration} minutes\n📍 Location:  {location_name}\n              {location_address}\n   Maps: {google_maps_link} | Waze: {waze_link}\n─────────────────────────────────────────\n\nBOOKING REFERENCE: #{booking_reference}\nName:    {customer_name}\nContact: {customer_phone} | {customer_email}\n\nPlease arrive 5–10 minutes early. Contact us if your plans change.\n\n{rescheduling_policy}\n\n── MANAGE YOUR APPOINTMENT ──────────────\nView details / Reschedule / Cancel: {reschedule_link}\nAdd to Google Calendar: {calendar_link}\n\nFor enquiries: {business_email} | Tel: {business_phone}\n{business_name}\n{terms_link} | {privacy_link}";

        $cancelledEmailBody = "Hi {customer_first_name},\n\nYour appointment has been cancelled.\n\n── APPOINTMENT DETAILS ──────────────────\n📅 Date:      {appointment_date}\n🕐 Time:      {appointment_time}\n💼 Service:   {service_name}\n👤 Provider:  {provider_name}\n─────────────────────────────────────────\n\nBOOKING REFERENCE: #{booking_reference}\n\nWe hope to see you again soon! Book a new appointment:\n{booking_url}\n\nFor enquiries: {business_email} | Tel: {business_phone}\n{business_name}\n{terms_link} | {privacy_link}";

        $rescheduledEmailBody = "Hi {customer_first_name},\n\nYour appointment has been moved to a new date and time.\n\n── NEW DATE & TIME ───────────────────────\n📅 Date:      {appointment_date}\n🕐 Time:      {appointment_time}\n💼 Service:   {service_name}\n👤 Provider:  {provider_name}\n⏱  Duration: {service_duration} minutes\n📍 Location:  {location_name}\n              {location_address}\n   Maps: {google_maps_link} | Waze: {waze_link}\n─────────────────────────────────────────\n\nBOOKING REFERENCE: #{booking_reference}\nName:    {customer_name}\nContact: {customer_phone} | {customer_email}\n\n{rescheduling_policy}\n\n── MANAGE YOUR APPOINTMENT ──────────────\nView details / Reschedule / Cancel: {reschedule_link}\nAdd to Google Calendar: {calendar_link}\n\nFor enquiries: {business_email} | Tel: {business_phone}\n{business_name}\n{terms_link} | {privacy_link}";

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

    /**
     * Update 5 email templates: add {business_phone} to the enquiries footer line.
     */
    public function up(): void
    {
        $builder = $this->db->table('settings');
        $now     = date('Y-m-d H:i:s');

        foreach ($this->v3Templates() as $key => $template) {
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
     * Revert to V2 bodies (without {business_phone} on the enquiries line).
     */
    public function down(): void
    {
        $builder = $this->db->table('settings');
        $now     = date('Y-m-d H:i:s');

        // V2 bodies had "For enquiries: {business_email}" without the phone
        $v2Bodies = [
            'notification_template.appointment_pending.email' => [
                'subject' => 'Your Appointment Request is Received — {service_name}',
                'body'    => str_replace(
                    "For enquiries: {business_email} | Tel: {business_phone}\n",
                    "For enquiries: {business_email}\n",
                    $this->v3Templates()['notification_template.appointment_pending.email']['body']
                ),
            ],
            'notification_template.appointment_confirmed.email' => [
                'subject' => 'Your Appointment is Confirmed — {appointment_date} at {appointment_time}',
                'body'    => str_replace(
                    "For enquiries: {business_email} | Tel: {business_phone}\n",
                    "For enquiries: {business_email}\n",
                    $this->v3Templates()['notification_template.appointment_confirmed.email']['body']
                ),
            ],
            'notification_template.appointment_reminder.email' => [
                'subject' => 'Reminder: Your Appointment — {appointment_date} at {appointment_time}',
                'body'    => str_replace(
                    "For enquiries: {business_email} | Tel: {business_phone}\n",
                    "For enquiries: {business_email}\n",
                    $this->v3Templates()['notification_template.appointment_reminder.email']['body']
                ),
            ],
            'notification_template.appointment_cancelled.email' => [
                'subject' => 'Your Appointment Has Been Cancelled — {service_name} on {appointment_date}',
                'body'    => str_replace(
                    "For enquiries: {business_email} | Tel: {business_phone}\n",
                    "For enquiries: {business_email}\n",
                    $this->v3Templates()['notification_template.appointment_cancelled.email']['body']
                ),
            ],
            'notification_template.appointment_rescheduled.email' => [
                'subject' => 'Your Appointment Has Been Rescheduled — {appointment_date} at {appointment_time}',
                'body'    => str_replace(
                    "For enquiries: {business_email} | Tel: {business_phone}\n",
                    "For enquiries: {business_email}\n",
                    $this->v3Templates()['notification_template.appointment_rescheduled.email']['body']
                ),
            ],
        ];

        foreach ($v2Bodies as $key => $template) {
            $value = json_encode($template, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $builder->where('setting_key', $key)->update([
                'setting_value' => $value,
                'setting_type'  => 'json',
                'updated_at'    => $now,
            ]);
        }
    }
}
