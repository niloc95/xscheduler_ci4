<?php

/**
 * @file    2026-04-19-100000_UpdateCustomerNotificationTemplatesV2.php
 * @brief   Update customer-facing email templates: location fallback + map links + business email.
 *
 * Updates the 5 email bodies (appointment_pending, appointment_confirmed,
 * appointment_reminder, appointment_cancelled, appointment_rescheduled) in
 * xs_settings to include:
 *   - Google Maps and Waze navigation links after the location block
 *   - "For enquiries: {business_email}" in the footer (above {business_name})
 *
 * SMS and WhatsApp bodies are unchanged.
 *
 * Requires migration 2026-04-18-100000_SeedCustomerNotificationTemplates.php
 * to have run first (those rows must already exist).
 *
 * @package App\Database\Migrations
 */

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class UpdateCustomerNotificationTemplatesV2 extends MigrationBase
{
    /**
     * The 5 email keys updated by this migration.
     */
    private const EMAIL_KEYS = [
        'notification_template.appointment_pending.email',
        'notification_template.appointment_confirmed.email',
        'notification_template.appointment_reminder.email',
        'notification_template.appointment_cancelled.email',
        'notification_template.appointment_rescheduled.email',
    ];

    /**
     * V2 email bodies — add map links + business email enquiry line.
     */
    private function v2Templates(): array
    {
        $pendingEmailBody = "Hi {customer_first_name},\n\nWe've received your booking request! We will confirm your appointment shortly.\n\n── APPOINTMENT DETAILS ──────────────────\n📅 Date:      {appointment_date}\n🕐 Time:      {appointment_time}\n💼 Service:   {service_name}\n👤 Provider:  {provider_name}\n⏱  Duration: {service_duration} minutes\n📍 Location:  {location_name}\n              {location_address}\n   Maps: {google_maps_link} | Waze: {waze_link}\n─────────────────────────────────────────\n\nBOOKING REFERENCE: #{booking_reference}\nName:    {customer_name}\nContact: {customer_phone} | {customer_email}\n\nWe will notify you as soon as your appointment is confirmed.\n\n{cancellation_policy}\n{rescheduling_policy}\n\n── MANAGE YOUR APPOINTMENT ──────────────\nView details / Reschedule / Cancel: {reschedule_link}\nAdd to Google Calendar: {calendar_link}\n\nFor enquiries: {business_email}\n{business_name}\n{terms_link} | {privacy_link}";

        $confirmedEmailBody = "Hi {customer_first_name},\n\nThank you for booking with {business_name}! Your appointment is confirmed ✓\n\n── APPOINTMENT DETAILS ──────────────────\n📅 Date:      {appointment_date}\n🕐 Time:      {appointment_time}\n💼 Service:   {service_name}\n👤 Provider:  {provider_name}\n⏱  Duration: {service_duration} minutes\n📍 Location:  {location_name}\n              {location_address}\n   Maps: {google_maps_link} | Waze: {waze_link}\n─────────────────────────────────────────\n\nBOOKING REFERENCE: #{booking_reference}\nName:    {customer_name}\nContact: {customer_phone} | {customer_email}\n\nPlease arrive 5–10 minutes early. Bring any relevant documentation.\n\n{cancellation_policy}\n{rescheduling_policy}\n\n── MANAGE YOUR APPOINTMENT ──────────────\nView details / Reschedule / Cancel: {reschedule_link}\nAdd to Google Calendar: {calendar_link}\n\nFor enquiries: {business_email}\n{business_name}\n{terms_link} | {privacy_link}";

        $reminderEmailBody = "Hi {customer_first_name},\n\nDon't forget — you have an upcoming appointment!\n\n── APPOINTMENT DETAILS ──────────────────\n📅 Date:      {appointment_date}\n🕐 Time:      {appointment_time}\n💼 Service:   {service_name}\n👤 Provider:  {provider_name}\n⏱  Duration: {service_duration} minutes\n📍 Location:  {location_name}\n              {location_address}\n   Maps: {google_maps_link} | Waze: {waze_link}\n─────────────────────────────────────────\n\nBOOKING REFERENCE: #{booking_reference}\nName:    {customer_name}\nContact: {customer_phone} | {customer_email}\n\nPlease arrive 5–10 minutes early. Contact us if your plans change.\n\n{rescheduling_policy}\n\n── MANAGE YOUR APPOINTMENT ──────────────\nView details / Reschedule / Cancel: {reschedule_link}\nAdd to Google Calendar: {calendar_link}\n\nFor enquiries: {business_email}\n{business_name}\n{terms_link} | {privacy_link}";

        $cancelledEmailBody = "Hi {customer_first_name},\n\nYour appointment has been cancelled.\n\n── APPOINTMENT DETAILS ──────────────────\n📅 Date:      {appointment_date}\n🕐 Time:      {appointment_time}\n💼 Service:   {service_name}\n👤 Provider:  {provider_name}\n─────────────────────────────────────────\n\nBOOKING REFERENCE: #{booking_reference}\n\nWe hope to see you again soon! Book a new appointment:\n{booking_url}\n\nFor enquiries: {business_email}\n{business_name}\n{terms_link} | {privacy_link}";

        $rescheduledEmailBody = "Hi {customer_first_name},\n\nYour appointment has been moved to a new date and time.\n\n── NEW DATE & TIME ───────────────────────\n📅 Date:      {appointment_date}\n🕐 Time:      {appointment_time}\n💼 Service:   {service_name}\n👤 Provider:  {provider_name}\n⏱  Duration: {service_duration} minutes\n📍 Location:  {location_name}\n              {location_address}\n   Maps: {google_maps_link} | Waze: {waze_link}\n─────────────────────────────────────────\n\nBOOKING REFERENCE: #{booking_reference}\nName:    {customer_name}\nContact: {customer_phone} | {customer_email}\n\n{rescheduling_policy}\n\n── MANAGE YOUR APPOINTMENT ──────────────\nView details / Reschedule / Cancel: {reschedule_link}\nAdd to Google Calendar: {calendar_link}\n\nFor enquiries: {business_email}\n{business_name}\n{terms_link} | {privacy_link}";

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
     * V1 email bodies — restored by down() so a rollback removes map links and
     * the business-email enquiry line.
     */
    private function v1Templates(): array
    {
        $pendingEmailBody = "Hi {customer_first_name},\n\nWe've received your booking request! We will confirm your appointment shortly.\n\n── APPOINTMENT DETAILS ──────────────────\n📅 Date:      {appointment_date}\n🕐 Time:      {appointment_time}\n💼 Service:   {service_name}\n👤 Provider:  {provider_name}\n⏱  Duration: {service_duration} minutes\n📍 Location:  {location_name}\n              {location_address}\n─────────────────────────────────────────\n\nBOOKING REFERENCE: #{booking_reference}\nName:    {customer_name}\nContact: {customer_phone} | {customer_email}\n\nWe will notify you as soon as your appointment is confirmed.\n\n{cancellation_policy}\n{rescheduling_policy}\n\n── MANAGE YOUR APPOINTMENT ──────────────\nView details / Reschedule / Cancel: {reschedule_link}\nAdd to Google Calendar: {calendar_link}\n\n{business_name}\n{terms_link} | {privacy_link}";

        $confirmedEmailBody = "Hi {customer_first_name},\n\nThank you for booking with {business_name}! Your appointment is confirmed ✓\n\n── APPOINTMENT DETAILS ──────────────────\n📅 Date:      {appointment_date}\n🕐 Time:      {appointment_time}\n💼 Service:   {service_name}\n👤 Provider:  {provider_name}\n⏱  Duration: {service_duration} minutes\n📍 Location:  {location_name}\n              {location_address}\n─────────────────────────────────────────\n\nBOOKING REFERENCE: #{booking_reference}\nName:    {customer_name}\nContact: {customer_phone} | {customer_email}\n\nPlease arrive 5–10 minutes early. Bring any relevant documentation.\n\n{cancellation_policy}\n{rescheduling_policy}\n\n── MANAGE YOUR APPOINTMENT ──────────────\nView details / Reschedule / Cancel: {reschedule_link}\nAdd to Google Calendar: {calendar_link}\n\n{business_name}\n{terms_link} | {privacy_link}";

        $reminderEmailBody = "Hi {customer_first_name},\n\nDon't forget — you have an upcoming appointment!\n\n── APPOINTMENT DETAILS ──────────────────\n📅 Date:      {appointment_date}\n🕐 Time:      {appointment_time}\n💼 Service:   {service_name}\n👤 Provider:  {provider_name}\n⏱  Duration: {service_duration} minutes\n📍 Location:  {location_name}\n              {location_address}\n─────────────────────────────────────────\n\nBOOKING REFERENCE: #{booking_reference}\nName:    {customer_name}\nContact: {customer_phone} | {customer_email}\n\nPlease arrive 5–10 minutes early. Contact us if your plans change.\n\n{rescheduling_policy}\n\n── MANAGE YOUR APPOINTMENT ──────────────\nView details / Reschedule / Cancel: {reschedule_link}\nAdd to Google Calendar: {calendar_link}\n\n{business_name}\n{terms_link} | {privacy_link}";

        $cancelledEmailBody = "Hi {customer_first_name},\n\nYour appointment has been cancelled.\n\n── APPOINTMENT DETAILS ──────────────────\n📅 Date:      {appointment_date}\n🕐 Time:      {appointment_time}\n💼 Service:   {service_name}\n👤 Provider:  {provider_name}\n─────────────────────────────────────────\n\nBOOKING REFERENCE: #{booking_reference}\n\nWe hope to see you again soon! Book a new appointment:\n{booking_url}\n\n{business_name}\n{terms_link} | {privacy_link}";

        $rescheduledEmailBody = "Hi {customer_first_name},\n\nYour appointment has been moved to a new date and time.\n\n── NEW DATE & TIME ───────────────────────\n📅 Date:      {appointment_date}\n🕐 Time:      {appointment_time}\n💼 Service:   {service_name}\n👤 Provider:  {provider_name}\n⏱  Duration: {service_duration} minutes\n📍 Location:  {location_name}\n              {location_address}\n─────────────────────────────────────────\n\nBOOKING REFERENCE: #{booking_reference}\nName:    {customer_name}\nContact: {customer_phone} | {customer_email}\n\n{rescheduling_policy}\n\n── MANAGE YOUR APPOINTMENT ──────────────\nView details / Reschedule / Cancel: {reschedule_link}\nAdd to Google Calendar: {calendar_link}\n\n{business_name}\n{terms_link} | {privacy_link}";

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
     * Update 5 email templates to V2 content (map links + business email).
     */
    public function up(): void
    {
        $builder = $this->db->table('settings');
        $now     = date('Y-m-d H:i:s');

        foreach ($this->v2Templates() as $key => $template) {
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
     * Restore V1 email bodies (without map links / business email line).
     */
    public function down(): void
    {
        $builder = $this->db->table('settings');
        $now     = date('Y-m-d H:i:s');

        foreach ($this->v1Templates() as $key => $template) {
            $value = json_encode($template, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $builder->where('setting_key', $key)->update([
                'setting_value' => $value,
                'setting_type'  => 'json',
                'updated_at'    => $now,
            ]);
        }
    }
}
