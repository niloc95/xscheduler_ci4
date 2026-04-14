<?php

/**
 * =============================================================================
 * NOTIFICATION TEMPLATE SERVICE
 * =============================================================================
 * 
 * @file        app/Services/NotificationTemplateService.php
 * @description Handles loading and rendering of notification message templates
 *              with placeholder substitution and legal content integration.
 * 
 * PURPOSE:
 * -----------------------------------------------------------------------------
 * Provides dynamic message generation for all notification types:
 * - Load templates from database or defaults
 * - Replace placeholders with appointment data
 * - Include legal content (policies, terms)
 * - Support multiple channels (email, SMS, WhatsApp)
 * 
 * SUPPORTED PLACEHOLDERS:
 * -----------------------------------------------------------------------------
 * Customer Info:
 * - {customer_name}       : Full customer name
 * - {customer_first_name} : First name only
 * - {customer_email}      : Customer email
 * - {customer_phone}      : Customer phone number
 * 
 * Appointment Info:
 * - {service_name}        : Service booked
 * - {service_duration}    : Duration in minutes
 * - {provider_name}       : Provider/staff name
 * - {appointment_date}    : Formatted date
 * - {appointment_time}    : Formatted time
 * - {appointment_datetime}: Full datetime
 * 
 * Business Info:
 * - {business_name}       : Business name
 * - {business_email}      : Contact email
 * - {business_phone}      : Contact phone
 * - {business_address}    : Business address
 * 
 * Legal Content:
 * - {cancellation_policy} : Cancellation policy text
 * - {rescheduling_policy} : Rescheduling policy text
 * - {terms_link}          : Terms & Conditions link
 * - {privacy_link}        : Privacy policy link
 * 
 * KEY METHODS:
 * -----------------------------------------------------------------------------
 * render($eventType, $channel, $appointmentData)
 *   Render template with placeholder substitution
 * 
 * getTemplate($eventType, $channel)
 *   Get template content (custom or default)
 * 
 * getPlaceholderData($appointmentId)
 *   Build placeholder data array from appointment
 * 
 * @see         app/Models/MessageTemplateModel.php
 * @package     App\Services
 * @author      Nilesh Nagin Cara
 * @copyright   2024-2026 Nilesh Nagin Cara
 * =============================================================================
 */

namespace App\Services;

use App\Models\SettingModel;
use App\Services\LocalizationSettingsService;

/**
 * NotificationTemplateService
 * 
 * Handles loading and rendering of notification message templates with
 * placeholder substitution and legal content integration.
 */
class NotificationTemplateService
{
    private SettingModel $settingModel;
    private array $legalContent = [];
    private array $templateCache = [];

    /**
     * Supported placeholders and their descriptions
     */
    public const PLACEHOLDERS = [
        '{customer_name}' => 'Customer full name',
        '{customer_first_name}' => 'Customer first name',
        '{customer_email}' => 'Customer email',
        '{customer_phone}' => 'Customer phone number',
        '{service_name}' => 'Service name',
        '{service_duration}' => 'Service duration in minutes',
        '{provider_name}' => 'Provider/staff name',
        '{appointment_date}' => 'Appointment date (formatted)',
        '{appointment_time}' => 'Appointment time (formatted)',
        '{appointment_datetime}' => 'Full date and time',
        '{business_name}' => 'Business name',
        '{business_email}' => 'Business contact email',
        '{business_phone}' => 'Business phone number',
        '{business_address}' => 'Business address',
        '{cancellation_policy}' => 'Cancellation policy text',
        '{rescheduling_policy}' => 'Rescheduling policy text',
        '{terms_link}' => 'Terms & Conditions link or text',
        '{privacy_link}' => 'Privacy policy link or text',
        '{reschedule_link}' => 'Secure reschedule link',
        '{booking_url}' => 'Online booking URL',
        '{booking_id}' => 'Booking/appointment ID',
        '{internal_view_link}' => 'Internal link to view appointment',
        '{internal_edit_link}' => 'Internal link to edit appointment',
        '{internal_contact_link}' => 'Internal link to contact customer',
        '{booked_via}' => 'Booking channel (web/app/admin)',
        '{booked_timestamp}' => 'Booking timestamp',
        // Location placeholders
        '{location_name}' => 'Appointment location name',
        '{location_address}' => 'Appointment location address',
        '{location_contact}' => 'Appointment location contact number',
    ];

    /**
     * Default templates for each event type and channel
     */
    private const DEFAULT_TEMPLATES = [
        'appointment_pending' => [
            'email' => [
                'subject' => 'Appointment Pending - {service_name}',
                'body' => "Hi {customer_name},\n\nYour appointment request has been received and is pending confirmation.\n\n📅 Date: {appointment_date}\n🕐 Time: {appointment_time}\n💼 Service: {service_name}\n👤 With: {provider_name}\n\nImportant Information:\n{cancellation_policy}\n{rescheduling_policy}\n\nManage booking: {reschedule_link}\n\nWe will notify you as soon as your booking is confirmed.\n\nThank you for booking with {business_name}!\n\nView our Terms & Conditions: {terms_link}\nPrivacy Policy: {privacy_link}"
            ],
            'sms' => [
                'body' => "⏳ Appt pending: {service_name} on {appointment_date} at {appointment_time} with {provider_name}. Manage: {reschedule_link}"
            ],
            'whatsapp' => [
                'body' => "⏳ *Appointment Pending*\n\nHi {customer_name}!\n\nYour appointment request has been received and is pending confirmation:\n\n📅 *Date:* {appointment_date}\n🕐 *Time:* {appointment_time}\n💼 *Service:* {service_name}\n👤 *With:* {provider_name}\n\n{cancellation_policy}\n\nManage booking: {reschedule_link}\nTerms: {terms_link}\nPrivacy: {privacy_link}\n\nWe will notify you as soon as your booking is confirmed.\n\nThank you for booking with {business_name}!"
            ]
        ],
        'appointment_confirmed' => [
            'email' => [
                'subject' => 'Appointment Confirmed - {service_name}',
                'body' => "Hi {customer_name},\n\nYour appointment has been confirmed!\n\n📅 Date: {appointment_date}\n🕐 Time: {appointment_time}\n💼 Service: {service_name}\n👤 With: {provider_name}\n\nImportant Information:\n{cancellation_policy}\n{rescheduling_policy}\n\nManage booking: {reschedule_link}\n\nThank you for booking with {business_name}!\n\nView our Terms & Conditions: {terms_link}\nPrivacy Policy: {privacy_link}"
            ],
            'sms' => [
                'body' => "✅ Appt confirmed: {service_name} on {appointment_date} at {appointment_time} with {provider_name}. Manage: {reschedule_link}"
            ],
            'whatsapp' => [
                'body' => "✅ *Appointment Confirmed*\n\nHi {customer_name}!\n\nYour appointment has been confirmed:\n\n📅 *Date:* {appointment_date}\n🕐 *Time:* {appointment_time}\n💼 *Service:* {service_name}\n👤 *With:* {provider_name}\n\n{cancellation_policy}\n\nManage booking: {reschedule_link}\nTerms: {terms_link}\nPrivacy: {privacy_link}\n\nThank you for booking with {business_name}!"
            ]
        ],
        'appointment_reminder' => [
            'email' => [
                'subject' => 'Reminder: Your Appointment Tomorrow - {service_name}',
                'body' => "Hi {customer_name},\n\nThis is a friendly reminder about your upcoming appointment:\n\n📅 Date: {appointment_date}\n🕐 Time: {appointment_time}\n💼 Service: {service_name}\n👤 With: {provider_name}\n\n{rescheduling_policy}\n\nWe look forward to seeing you!\n\n{business_name}"
            ],
            'sms' => [
                'body' => "⏰ Reminder: {service_name} on {appointment_date} at {appointment_time}. Manage: {reschedule_link}"
            ],
            'whatsapp' => [
                'body' => "⏰ *Appointment Reminder*\n\nHi {customer_name}!\n\nThis is a friendly reminder about your upcoming appointment:\n\n📅 *Date:* {appointment_date}\n🕐 *Time:* {appointment_time}\n💼 *Service:* {service_name}\n👤 *With:* {provider_name}\n\nManage booking: {reschedule_link}\n\nWe look forward to seeing you!\n\n_{business_name}_"
            ]
        ],
        'appointment_cancelled' => [
            'email' => [
                'subject' => 'Appointment Cancelled - {service_name}',
                'body' => "Hi {customer_name},\n\nYour appointment has been cancelled:\n\n📅 Date: {appointment_date}\n🕐 Time: {appointment_time}\n💼 Service: {service_name}\n\nWe hope to see you again soon! To reschedule, please contact us or book online.\n\n{business_name}"
            ],
            'sms' => [
                'body' => "❌ Appt cancelled: {service_name} on {appointment_date}. Rebook/manage: {reschedule_link}"
            ],
            'whatsapp' => [
                'body' => "❌ *Appointment Cancelled*\n\nHi {customer_name},\n\nYour appointment has been cancelled:\n\n📅 *Date:* {appointment_date}\n🕐 *Time:* {appointment_time}\n💼 *Service:* {service_name}\n\nBook/reschedule: {reschedule_link}\n\n_{business_name}_"
            ]
        ],
        'appointment_rescheduled' => [
            'email' => [
                'subject' => 'Appointment Rescheduled - {service_name}',
                'body' => "Hi {customer_name},\n\nYour appointment has been rescheduled to:\n\n📅 New Date: {appointment_date}\n🕐 New Time: {appointment_time}\n💼 Service: {service_name}\n👤 With: {provider_name}\n\n{rescheduling_policy}\n\nPlease let us know if this doesn't work for you.\n\n{business_name}"
            ],
            'sms' => [
                'body' => "📅 Appt rescheduled: {service_name} now {appointment_date} at {appointment_time}. Link: {reschedule_link}"
            ],
            'whatsapp' => [
                'body' => "📅 *Appointment Rescheduled*\n\nHi {customer_name}!\n\nYour appointment has been rescheduled to:\n\n📅 *New Date:* {appointment_date}\n🕐 *New Time:* {appointment_time}\n💼 *Service:* {service_name}\n👤 *With:* {provider_name}\n\nManage again: {reschedule_link}\n\n_{business_name}_"
            ]
        ],
    ];

    /**
     * Required placeholders by event and channel.
     *
     * These are enforced on template save and used for runtime diagnostics.
     */
    private const REQUIRED_PLACEHOLDERS = [
        'appointment_pending' => [
            'email' => ['{reschedule_link}'],
            'sms' => ['{reschedule_link}'],
            'whatsapp' => ['{reschedule_link}'],
        ],
        'appointment_confirmed' => [
            'email' => ['{reschedule_link}'],
            'sms' => ['{reschedule_link}'],
            'whatsapp' => ['{reschedule_link}'],
        ],
    ];

    /**
     * Default internal (provider/staff-facing) email templates for all 5 appointment events.
     * These mirror the content seeded by migration 2026-04-13-100400.
     * Used as a code-level fallback when xs_message_templates rows are not yet present
     * (e.g. migration not applied in production).
     */
    private const DEFAULT_INTERNAL_TEMPLATES = [
        'appointment_pending' => [
            'email' => [
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
                    . "Timestamp: {booked_timestamp}\n\n"
                    . "Manage booking:\n"
                    . "{reschedule_link}",
            ],
        ],
        'appointment_confirmed' => [
            'email' => [
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
                    . "Timestamp: {booked_timestamp}\n\n"
                    . "Manage booking:\n"
                    . "{reschedule_link}",
            ],
        ],
        'appointment_cancelled' => [
            'email' => [
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
                    . "Timestamp: {booked_timestamp}\n\n"
                    . "Manage booking:\n"
                    . "{reschedule_link}",
            ],
        ],
        'appointment_rescheduled' => [
            'email' => [
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
                    . "Timestamp: {booked_timestamp}\n\n"
                    . "Manage booking:\n"
                    . "{reschedule_link}",
            ],
        ],
        'appointment_reminder' => [
            'email' => [
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
                    . "Timestamp: {booked_timestamp}\n\n"
                    . "Manage booking:\n"
                    . "{reschedule_link}",
            ],
        ],
    ];

    public function __construct()
    {
        $this->settingModel = new SettingModel();
    }

    /**
     * Get default templates
     */
    public function getDefaultTemplates(): array
    {
        return self::DEFAULT_TEMPLATES;
    }

    /**
     * Load legal content settings
     */
    private function loadLegalContent(): void
    {
        if (!empty($this->legalContent)) {
            return;
        }

        $settings = $this->settingModel->getByKeys([
            'legal.terms',
            'legal.privacy',
            'legal.cancellation_policy',
            'legal.rescheduling_policy',
            'legal.terms_url',
            'legal.privacy_url',
            'general.business_name',
            'general.company_email',
            'general.company_phone',
            'general.business_address',
        ]);

        $this->legalContent = [
            'terms' => $settings['legal.terms'] ?? '',
            'privacy' => $settings['legal.privacy'] ?? '',
            'cancellation_policy' => $settings['legal.cancellation_policy'] ?? '',
            'rescheduling_policy' => $settings['legal.rescheduling_policy'] ?? '',
            'terms_url' => $settings['legal.terms_url'] ?? '',
            'privacy_url' => $settings['legal.privacy_url'] ?? '',
            'business_name' => $settings['general.business_name'] ?? '',
            'business_email' => $settings['general.company_email'] ?? '',
            'business_phone' => $settings['general.company_phone'] ?? '',
            'business_address' => $settings['general.business_address'] ?? '',
        ];
    }

    /**
     * Get template for a specific event type and channel
     *
     * @param string $eventType Event type (e.g., 'appointment_confirmed')
     * @param string $channel Channel (email, sms, whatsapp)
     * @return array Template with 'subject' (if applicable) and 'body'
     */
    public function getTemplate(string $eventType, string $channel, string $recipientClass = 'customer'): array
    {
        $cacheKey = "{$recipientClass}.{$eventType}.{$channel}";
        if (isset($this->templateCache[$cacheKey])) {
            return $this->templateCache[$cacheKey];
        }

        $template = null;

        // For internal recipients: look up the seeded row in xs_message_templates directly.
        if ($recipientClass === 'internal') {
            try {
                $row = (new \App\Models\MessageTemplateModel())
                    ->where('event_type', $eventType)
                    ->where('channel', $channel)
                    ->where('recipient_class', 'internal')
                    ->where('is_active', 1)
                    ->orderBy('id', 'DESC')
                    ->first();

                if ($row && !empty($row['body'])) {
                    $template = [
                        'subject' => $row['subject'] ?? '',
                        'body'    => $row['body'],
                    ];
                }
            } catch (\Throwable $e) {
                log_message('warning', '[NotificationTemplateService] Could not load internal template: ' . $e->getMessage());
            }
        }

        // For customer recipients (or fallback when no internal template found):
        // try the settings-based custom template, then fall back to hardcoded defaults.
        if ($template === null && $recipientClass === 'customer') {
            $settingKey = "notification_template.{$eventType}.{$channel}";
            $stored     = $this->settingModel->getByKeys([$settingKey]);

            if (!empty($stored[$settingKey])) {
                $decoded = is_string($stored[$settingKey])
                    ? json_decode($stored[$settingKey], true)
                    : $stored[$settingKey];
                if (is_array($decoded) && !empty($decoded['body'])) {
                    $template = $decoded;
                }
            }
        }

        if ($template === null) {
            // For internal recipients: use the internal code-level fallback before the
            // customer-facing DEFAULT_TEMPLATES.  This prevents provider/staff from
            // receiving a customer confirmation email when the migration hasn't been applied.
            if ($recipientClass === 'internal') {
                $template = self::DEFAULT_INTERNAL_TEMPLATES[$eventType][$channel] ?? null;
            }
        }

        if ($template === null) {
            $template = self::DEFAULT_TEMPLATES[$eventType][$channel] ?? [
                'subject' => '',
                'body'    => '',
            ];
        }

        $this->templateCache[$cacheKey] = $template;
        return $template;
    }

    /**
     * Render a template with data substitution
     *
     * @param string $eventType Event type (e.g., 'appointment_confirmed')
     * @param string $channel Channel (email, sms, whatsapp)
     * @param array $data Data for placeholder substitution
     * @return array Rendered template with 'subject' (if applicable) and 'body'
     */
    public function render(string $eventType, string $channel, array $data, string $recipientClass = 'customer'): array
    {
        $this->loadLegalContent();
        $template = $this->getTemplate($eventType, $channel, $recipientClass);
        $this->logIfRequiredPlaceholdersMissing($eventType, $channel, (string) ($template['body'] ?? ''));

        // Prepare placeholders
        $placeholders = $this->buildPlaceholders($data);

        // Render subject
        $subject = $template['subject'] ?? '';
        if ($subject !== '') {
            $subject = strtr($subject, $placeholders);
        }

        // Render body
        $body = $template['body'] ?? '';
        if ($body !== '') {
            $body = $this->ensureBodyContainsRequiredPlaceholders($eventType, $channel, $body, $data);
            $body = strtr($body, $placeholders);
        }

        return [
            'subject' => $subject,
            'body' => $body,
        ];
    }

    private function ensureBodyContainsRequiredPlaceholders(string $eventType, string $channel, string $body, array $data): string
    {
        $requiredValidation = $this->validateRequiredPlaceholders($eventType, $channel, $body);
        if ($requiredValidation['valid']) {
            return $body;
        }

        if (!in_array('{reschedule_link}', $requiredValidation['missing'], true)) {
            return $body;
        }

        $rescheduleLink = trim((string) ($data['reschedule_link'] ?? ''));
        if ($rescheduleLink === '') {
            return $body;
        }

        if ($channel === 'email' && strpos($body, 'Manage booking: {reschedule_link}') === false) {
            return rtrim($body) . "\n\nManage booking: {reschedule_link}";
        }

        if ($channel === 'sms' && strpos($body, 'Manage: {reschedule_link}') === false) {
            return rtrim($body) . ' Manage: {reschedule_link}';
        }

        if ($channel === 'whatsapp' && strpos($body, 'Manage booking: {reschedule_link}') === false) {
            return rtrim($body) . "\nManage booking: {reschedule_link}";
        }

        return $body;
    }

    /**
     * Return required placeholders for an event/channel pair.
     *
     * @return array<int, string>
     */
    public function getRequiredPlaceholders(string $eventType, string $channel): array
    {
        return self::REQUIRED_PLACEHOLDERS[$eventType][$channel] ?? [];
    }

    /**
     * Validate required placeholders for an event/channel body.
     *
     * @return array{valid: bool, missing: array<int, string>}
     */
    public function validateRequiredPlaceholders(string $eventType, string $channel, string $content): array
    {
        $required = $this->getRequiredPlaceholders($eventType, $channel);
        if (empty($required)) {
            return ['valid' => true, 'missing' => []];
        }

        $missing = [];
        foreach ($required as $placeholder) {
            if (strpos($content, $placeholder) === false) {
                $missing[] = $placeholder;
            }
        }

        return [
            'valid' => empty($missing),
            'missing' => $missing,
        ];
    }

    /**
     * Build placeholder substitution array from data
     *
     * @param array $data Appointment/customer/service data
     * @return array Placeholder => value mapping
     */
    private function buildPlaceholders(array $data): array
    {
        // Extract appointment date/time
        $appointmentDate = '';
        $appointmentTime = '';
        $appointmentDatetime = '';

        if (!empty($data['start_datetime'])) {
            try {
                $loc = new LocalizationSettingsService();
                $dt = new \DateTime($data['start_datetime']);
                $timeStr      = $loc->formatTimeForDisplay($dt->format('H:i:s'));
                $appointmentDate     = $dt->format('l, F j, Y');
                $appointmentTime     = $timeStr;
                $appointmentDatetime = $dt->format('l, F j, Y') . ' at ' . $timeStr;
            } catch (\Exception $e) {
                // Fallback
                $appointmentDate = $data['date'] ?? '';
                $appointmentTime = $data['time'] ?? '';
            }
        } elseif (!empty($data['date'])) {
            $appointmentDate = $data['date'];
            $appointmentTime = $data['time'] ?? '';
            $appointmentDatetime = trim($appointmentDate . ' ' . $appointmentTime);
        }

        // Build terms/privacy links
        $termsLink = $this->legalContent['terms_url'] ?: base_url('booking/legal#terms');
        $privacyLink = $this->legalContent['privacy_url'] ?: base_url('booking/legal#privacy');

        return [
            '{customer_name}' => $data['customer_name'] ?? $data['name'] ?? '',
            '{customer_first_name}' => $this->extractFirstName($data['customer_name'] ?? $data['name'] ?? ''),
            '{customer_email}' => $data['customer_email'] ?? $data['email'] ?? '',
            '{customer_phone}' => $data['customer_phone'] ?? $data['phone'] ?? '',
            '{service_name}' => $data['service_name'] ?? $data['service']['name'] ?? '',
            '{service_duration}' => (string) ($data['service_duration'] ?? $data['service']['duration'] ?? ''),
            '{provider_name}' => $data['provider_name'] ?? $data['provider']['name'] ?? '',
            '{appointment_date}' => $appointmentDate,
            '{appointment_time}' => $appointmentTime,
            '{appointment_datetime}' => $appointmentDatetime,
            '{business_name}' => $data['business_name'] ?? $this->legalContent['business_name'] ?? '',
            '{business_email}' => $data['business_email'] ?? $this->legalContent['business_email'] ?? '',
            '{business_phone}' => $data['business_phone'] ?? $this->legalContent['business_phone'] ?? '',
            '{business_address}' => $data['business_address'] ?? $this->legalContent['business_address'] ?? '',
            '{cancellation_policy}' => $this->legalContent['cancellation_policy'] ?? '',
            '{rescheduling_policy}' => $this->legalContent['rescheduling_policy'] ?? '',
            '{terms_link}' => $termsLink,
            '{privacy_link}' => $privacyLink,
            '{reschedule_link}' => $data['reschedule_link'] ?? '',
            '{booking_url}' => $data['booking_url'] ?? base_url('booking'),
            '{booking_id}' => (string) ($data['booking_id'] ?? $data['appointment_id'] ?? ''),
            '{internal_view_link}' => $data['internal_view_link'] ?? '',
            '{internal_edit_link}' => $data['internal_edit_link'] ?? '',
            '{internal_contact_link}' => $data['internal_contact_link'] ?? '',
            '{booked_via}' => $data['booked_via'] ?? '',
            '{booked_timestamp}' => $data['booked_timestamp'] ?? '',
            // Location placeholders (from appointment snapshot)
            '{location_name}' => $data['location_name'] ?? '',
            '{location_address}' => $data['location_address'] ?? '',
            '{location_contact}' => $data['location_contact'] ?? '',
        ];
    }

    /**
     * Extract first name from full name
     */
    private function extractFirstName(string $fullName): string
    {
        $parts = explode(' ', trim($fullName));
        return $parts[0] ?? $fullName;
    }

    /**
     * Get all supported placeholders
     */
    public function getPlaceholders(): array
    {
        return self::PLACEHOLDERS;
    }

    /**
     * Validate template content (check for unclosed placeholders, etc.)
     *
     * @param string $content Template content
     * @return array Validation result ['valid' => bool, 'errors' => array]
     */
    public function validateTemplate(string $content): array
    {
        $errors = [];

        // Check for unclosed braces
        $openCount = substr_count($content, '{');
        $closeCount = substr_count($content, '}');
        if ($openCount !== $closeCount) {
            $errors[] = 'Unbalanced braces in template';
        }

        // Check for unknown placeholders
        preg_match_all('/\{([a-z_]+)\}/', $content, $matches);
        foreach ($matches[0] as $placeholder) {
            if (!isset(self::PLACEHOLDERS[$placeholder])) {
                $errors[] = "Unknown placeholder: {$placeholder}";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    private function logIfRequiredPlaceholdersMissing(string $eventType, string $channel, string $content): void
    {
        $requiredValidation = $this->validateRequiredPlaceholders($eventType, $channel, $content);
        if ($requiredValidation['valid']) {
            return;
        }

        log_message(
            'warning',
            sprintf(
                '[NotificationTemplateService] Missing required placeholders for %s.%s: %s',
                $eventType,
                $channel,
                implode(', ', $requiredValidation['missing'])
            )
        );
    }

    /**
     * Preview a template with sample data
     *
     * @param string $eventType Event type
     * @param string $channel Channel
     * @return array Rendered template with sample data
     */
    public function preview(string $eventType, string $channel): array
    {
        $sampleData = [
            'customer_name' => 'John Smith',
            'customer_email' => 'john.smith@example.com',
            'customer_phone' => '+1 (555) 123-4567',
            'service_name' => 'Standard Consultation',
            'service_duration' => '60',
            'provider_name' => 'Dr. Jane Wilson',
            'start_datetime' => date('Y-m-d 14:30:00', strtotime('+2 days')),
            'business_name' => $this->legalContent['business_name'] ?? 'WebScheduler',
        ];

        return $this->render($eventType, $channel, $sampleData);
    }
}
