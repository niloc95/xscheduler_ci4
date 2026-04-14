<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

/**
 * Seed default internal (provider-facing) email templates for all 5 appointment events.
 * These use recipient_class = 'internal' to distinguish them from customer-facing templates.
 */
class SeedInternalNotificationTemplates extends MigrationBase
{
    private const BUSINESS_ID = 1;

    private const TEMPLATES = [
        'appointment_pending' => [
            'subject' => 'New Appointment Request — {service_name} with {customer_name}',
            'body'    => "Hi {provider_name},\n\nA new appointment request has been received and is awaiting confirmation.\n\n📅 Date: {appointment_date}\n🕐 Time: {appointment_time}\n💼 Service: {service_name}\n👤 Customer: {customer_name}\n📧 Email: {customer_email}\n📱 Phone: {customer_phone}\n\nPlease log in to confirm or manage this appointment.\n\n{business_name}",
        ],
        'appointment_confirmed' => [
            'subject' => 'Appointment Confirmed — {service_name} with {customer_name}',
            'body'    => "Hi {provider_name},\n\nAn appointment has been confirmed.\n\n📅 Date: {appointment_date}\n🕐 Time: {appointment_time}\n💼 Service: {service_name}\n👤 Customer: {customer_name}\n📧 Email: {customer_email}\n📱 Phone: {customer_phone}\n\n{business_name}",
        ],
        'appointment_cancelled' => [
            'subject' => 'Appointment Cancelled — {service_name} with {customer_name}',
            'body'    => "Hi {provider_name},\n\nThe following appointment has been cancelled.\n\n📅 Date: {appointment_date}\n🕐 Time: {appointment_time}\n💼 Service: {service_name}\n👤 Customer: {customer_name}\n📧 Email: {customer_email}\n📱 Phone: {customer_phone}\n\n{business_name}",
        ],
        'appointment_rescheduled' => [
            'subject' => 'Appointment Rescheduled — {service_name} with {customer_name}',
            'body'    => "Hi {provider_name},\n\nAn appointment has been rescheduled to a new time.\n\n📅 New Date: {appointment_date}\n🕐 New Time: {appointment_time}\n💼 Service: {service_name}\n👤 Customer: {customer_name}\n📧 Email: {customer_email}\n📱 Phone: {customer_phone}\n\n{business_name}",
        ],
        'appointment_reminder' => [
            'subject' => 'Reminder: Upcoming Appointment — {service_name} with {customer_name}',
            'body'    => "Hi {provider_name},\n\nThis is a reminder about your upcoming appointment.\n\n📅 Date: {appointment_date}\n🕐 Time: {appointment_time}\n💼 Service: {service_name}\n👤 Customer: {customer_name}\n📧 Email: {customer_email}\n📱 Phone: {customer_phone}\n\n{business_name}",
        ],
    ];

    public function up(): void
    {
        $table = $this->db->prefixTable('message_templates');

        if (!$this->db->tableExists($table) || !$this->db->fieldExists('recipient_class', $table)) {
            return;
        }

        $now = date('Y-m-d H:i:s');

        foreach (self::TEMPLATES as $eventType => $tmpl) {
            // Skip if an internal email template already exists for this event
            $exists = $this->db->table('message_templates')
                ->where('business_id', self::BUSINESS_ID)
                ->where('event_type', $eventType)
                ->where('channel', 'email')
                ->where('recipient_class', 'internal')
                ->countAllResults();

            if ($exists > 0) {
                continue;
            }

            $this->db->table('message_templates')->insert([
                'business_id'      => self::BUSINESS_ID,
                'event_type'       => $eventType,
                'channel'          => 'email',
                'provider'         => 'smtp',
                'subject'          => $tmpl['subject'],
                'body'             => $tmpl['body'],
                'recipient_class'  => 'internal',
                'is_active'        => 1,
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);
        }
    }

    public function down(): void
    {
        $table = $this->db->prefixTable('message_templates');

        if (!$this->db->tableExists($table) || !$this->db->fieldExists('recipient_class', $table)) {
            return;
        }

        $this->db->table('message_templates')
            ->where('business_id', self::BUSINESS_ID)
            ->where('channel', 'email')
            ->where('recipient_class', 'internal')
            ->delete();
    }
}
