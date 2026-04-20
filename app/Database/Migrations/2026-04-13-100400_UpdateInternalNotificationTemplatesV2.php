<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class UpdateInternalNotificationTemplatesV2 extends MigrationBase
{
    private const BUSINESS_ID = 1;

    private const TEMPLATES = [
        'appointment_pending' => [
            'subject' => 'New Appointment: {customer_name} with {provider_name} - {appointment_date} at {appointment_time}',
            'body' => "Appointment Confirmed\n"
                . "Booking ID: #{booking_id}\n\n"
                . "Appointment Details\n"
                . "Date: {appointment_date}\n"
                . "Time: {appointment_time}\n"
                . "Duration: {service_duration} minutes\n\n"
                . "Customer Information\n"
                . "Name: {customer_name}\n"
                . "Phone: {customer_phone}\n"
                . "Email: {customer_email}\n\n"
                . "Service Details\n"
                . "Service: {service_name}\n"
                . "Provider: {provider_name}\n"
                . "Location: {location_name} {location_address}\n\n"
                . "Internal Actions Required\n"
                . "- Confirm provider availability\n"
                . "- Prepare patient file\n"
                . "- Send customer confirmation\n\n"
                . "Quick Links\n"
                . "View full booking details: {internal_view_link}\n"
                . "Edit appointment: {internal_edit_link}\n"
                . "Contact customer: {internal_contact_link}\n\n"
                . "Booked via: {booked_via}\n"
                . "Timestamp: {booked_timestamp}",
        ],
        'appointment_confirmed' => [
            'subject' => 'New Appointment: {customer_name} with {provider_name} - {appointment_date} at {appointment_time}',
            'body' => "Appointment Confirmed\n"
                . "Booking ID: #{booking_id}\n\n"
                . "Appointment Details\n"
                . "Date: {appointment_date}\n"
                . "Time: {appointment_time}\n"
                . "Duration: {service_duration} minutes\n\n"
                . "Customer Information\n"
                . "Name: {customer_name}\n"
                . "Phone: {customer_phone}\n"
                . "Email: {customer_email}\n\n"
                . "Service Details\n"
                . "Service: {service_name}\n"
                . "Provider: {provider_name}\n"
                . "Location: {location_name} {location_address}\n\n"
                . "Internal Actions Required\n"
                . "- Confirm provider availability\n"
                . "- Prepare patient file\n"
                . "- Send customer confirmation\n\n"
                . "Quick Links\n"
                . "View full booking details: {internal_view_link}\n"
                . "Edit appointment: {internal_edit_link}\n"
                . "Contact customer: {internal_contact_link}\n\n"
                . "Booked via: {booked_via}\n"
                . "Timestamp: {booked_timestamp}",
        ],
        'appointment_cancelled' => [
            'subject' => 'Appointment Cancelled: {customer_name} with {provider_name} - {appointment_date} at {appointment_time}',
            'body' => "Appointment Cancelled\n"
                . "Booking ID: #{booking_id}\n\n"
                . "Appointment Details\n"
                . "Date: {appointment_date}\n"
                . "Time: {appointment_time}\n"
                . "Duration: {service_duration} minutes\n\n"
                . "Customer Information\n"
                . "Name: {customer_name}\n"
                . "Phone: {customer_phone}\n"
                . "Email: {customer_email}\n\n"
                . "Service Details\n"
                . "Service: {service_name}\n"
                . "Provider: {provider_name}\n"
                . "Location: {location_name} {location_address}\n\n"
                . "Quick Links\n"
                . "View full booking details: {internal_view_link}\n"
                . "Edit appointment: {internal_edit_link}\n"
                . "Contact customer: {internal_contact_link}\n\n"
                . "Booked via: {booked_via}\n"
                . "Timestamp: {booked_timestamp}",
        ],
        'appointment_rescheduled' => [
            'subject' => 'Appointment Rescheduled: {customer_name} with {provider_name} - {appointment_date} at {appointment_time}',
            'body' => "Appointment Rescheduled\n"
                . "Booking ID: #{booking_id}\n\n"
                . "Appointment Details\n"
                . "Date: {appointment_date}\n"
                . "Time: {appointment_time}\n"
                . "Duration: {service_duration} minutes\n\n"
                . "Customer Information\n"
                . "Name: {customer_name}\n"
                . "Phone: {customer_phone}\n"
                . "Email: {customer_email}\n\n"
                . "Service Details\n"
                . "Service: {service_name}\n"
                . "Provider: {provider_name}\n"
                . "Location: {location_name} {location_address}\n\n"
                . "Quick Links\n"
                . "View full booking details: {internal_view_link}\n"
                . "Edit appointment: {internal_edit_link}\n"
                . "Contact customer: {internal_contact_link}\n\n"
                . "Booked via: {booked_via}\n"
                . "Timestamp: {booked_timestamp}",
        ],
        'appointment_reminder' => [
            'subject' => 'Upcoming Appointment: {customer_name} with {provider_name} - {appointment_date} at {appointment_time}',
            'body' => "Upcoming Appointment Reminder\n"
                . "Booking ID: #{booking_id}\n\n"
                . "Appointment Details\n"
                . "Date: {appointment_date}\n"
                . "Time: {appointment_time}\n"
                . "Duration: {service_duration} minutes\n\n"
                . "Customer Information\n"
                . "Name: {customer_name}\n"
                . "Phone: {customer_phone}\n"
                . "Email: {customer_email}\n\n"
                . "Service Details\n"
                . "Service: {service_name}\n"
                . "Provider: {provider_name}\n"
                . "Location: {location_name} {location_address}\n\n"
                . "Quick Links\n"
                . "View full booking details: {internal_view_link}\n"
                . "Edit appointment: {internal_edit_link}\n"
                . "Contact customer: {internal_contact_link}\n\n"
                . "Booked via: {booked_via}\n"
                . "Timestamp: {booked_timestamp}",
        ],
    ];

    public function up(): void
    {
        $table = $this->db->prefixTable('message_templates');

        if (!$this->db->tableExists($table) || !$this->db->fieldExists('recipient_class', $table)) {
            return;
        }

        $now = date('Y-m-d H:i:s');

        foreach (self::TEMPLATES as $eventType => $template) {
            $existing = $this->db->table('message_templates')
                ->where('business_id', self::BUSINESS_ID)
                ->where('event_type', $eventType)
                ->where('channel', 'email')
                ->where('recipient_class', 'internal')
                ->get()
                ->getFirstRow('array');

            if ($existing) {
                $this->db->table('message_templates')
                    ->where('id', (int) $existing['id'])
                    ->update([
                        'subject' => $template['subject'],
                        'body' => $template['body'],
                        'is_active' => 1,
                        'updated_at' => $now,
                    ]);
                continue;
            }

            $this->db->table('message_templates')->insert([
                'business_id' => self::BUSINESS_ID,
                'event_type' => $eventType,
                'channel' => 'email',
                'provider' => 'smtp',
                'subject' => $template['subject'],
                'body' => $template['body'],
                'recipient_class' => 'internal',
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $table = $this->db->prefixTable('message_templates');

        if (!$this->db->tableExists($table) || !$this->db->fieldExists('recipient_class', $table)) {
            return;
        }

        $subjects = array_map(static fn(array $tmpl): string => $tmpl['subject'], self::TEMPLATES);

        $builder = $this->db->table('message_templates')
            ->where('business_id', self::BUSINESS_ID)
            ->where('channel', 'email')
            ->where('recipient_class', 'internal')
            ->whereIn('subject', $subjects);

        $builder->delete();
    }
}
