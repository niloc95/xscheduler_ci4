<?php

namespace App\Services;

use App\Models\SettingModel;

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
        '{booking_url}' => 'Online booking URL',
    ];

    /**
     * Default templates for each event type and channel
     */
    private const DEFAULT_TEMPLATES = [
        'appointment_confirmed' => [
            'email' => [
                'subject' => 'Appointment Confirmed - {service_name}',
                'body' => "Hi {customer_name},\n\nYour appointment has been confirmed!\n\n📅 Date: {appointment_date}\n🕐 Time: {appointment_time}\n💼 Service: {service_name}\n👤 With: {provider_name}\n\nImportant Information:\n{cancellation_policy}\n{rescheduling_policy}\n\nThank you for booking with {business_name}!\n\nView our Terms & Conditions: {terms_link}\nPrivacy Policy: {privacy_link}"
            ],
            'sms' => [
                'body' => "✅ Appt confirmed: {service_name} on {appointment_date} at {appointment_time} with {provider_name}. {business_name}"
            ],
            'whatsapp' => [
                'body' => "✅ *Appointment Confirmed*\n\nHi {customer_name}!\n\nYour appointment has been confirmed:\n\n📅 *Date:* {appointment_date}\n🕐 *Time:* {appointment_time}\n💼 *Service:* {service_name}\n👤 *With:* {provider_name}\n\n{cancellation_policy}\n\nThank you for booking with {business_name}!\n\n_Reply to this message if you need to make any changes._"
            ]
        ],
        'appointment_reminder' => [
            'email' => [
                'subject' => 'Reminder: Your Appointment Tomorrow - {service_name}',
                'body' => "Hi {customer_name},\n\nThis is a friendly reminder about your upcoming appointment:\n\n📅 Date: {appointment_date}\n🕐 Time: {appointment_time}\n💼 Service: {service_name}\n👤 With: {provider_name}\n\n{rescheduling_policy}\n\nWe look forward to seeing you!\n\n{business_name}"
            ],
            'sms' => [
                'body' => "⏰ Reminder: {service_name} on {appointment_date} at {appointment_time}. {business_name}"
            ],
            'whatsapp' => [
                'body' => "⏰ *Appointment Reminder*\n\nHi {customer_name}!\n\nThis is a friendly reminder about your upcoming appointment:\n\n📅 *Date:* {appointment_date}\n🕐 *Time:* {appointment_time}\n💼 *Service:* {service_name}\n👤 *With:* {provider_name}\n\nWe look forward to seeing you!\n\n_{business_name}_"
            ]
        ],
        'appointment_cancelled' => [
            'email' => [
                'subject' => 'Appointment Cancelled - {service_name}',
                'body' => "Hi {customer_name},\n\nYour appointment has been cancelled:\n\n📅 Date: {appointment_date}\n🕐 Time: {appointment_time}\n💼 Service: {service_name}\n\nWe hope to see you again soon! To reschedule, please contact us or book online.\n\n{business_name}"
            ],
            'sms' => [
                'body' => "❌ Appt cancelled: {service_name} on {appointment_date}. Contact us to reschedule. {business_name}"
            ],
            'whatsapp' => [
                'body' => "❌ *Appointment Cancelled*\n\nHi {customer_name},\n\nYour appointment has been cancelled:\n\n📅 *Date:* {appointment_date}\n🕐 *Time:* {appointment_time}\n💼 *Service:* {service_name}\n\nWe hope to see you again soon!\n\nTo reschedule, please contact us or book online.\n\n_{business_name}_"
            ]
        ],
        'appointment_rescheduled' => [
            'email' => [
                'subject' => 'Appointment Rescheduled - {service_name}',
                'body' => "Hi {customer_name},\n\nYour appointment has been rescheduled to:\n\n📅 New Date: {appointment_date}\n🕐 New Time: {appointment_time}\n💼 Service: {service_name}\n👤 With: {provider_name}\n\n{rescheduling_policy}\n\nPlease let us know if this doesn't work for you.\n\n{business_name}"
            ],
            'sms' => [
                'body' => "📅 Appt rescheduled: {service_name} now {appointment_date} at {appointment_time}. {business_name}"
            ],
            'whatsapp' => [
                'body' => "📅 *Appointment Rescheduled*\n\nHi {customer_name}!\n\nYour appointment has been rescheduled to:\n\n📅 *New Date:* {appointment_date}\n🕐 *New Time:* {appointment_time}\n💼 *Service:* {service_name}\n👤 *With:* {provider_name}\n\nPlease let us know if this doesn't work for you.\n\n_{business_name}_"
            ]
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
    public function getTemplate(string $eventType, string $channel): array
    {
        $cacheKey = "{$eventType}.{$channel}";
        if (isset($this->templateCache[$cacheKey])) {
            return $this->templateCache[$cacheKey];
        }

        // Try to load from database
        $settingKey = "notification_template.{$eventType}.{$channel}";
        $stored = $this->settingModel->getByKeys([$settingKey]);
        
        $template = null;
        if (!empty($stored[$settingKey])) {
            $decoded = is_string($stored[$settingKey]) 
                ? json_decode($stored[$settingKey], true) 
                : $stored[$settingKey];
            if (is_array($decoded) && !empty($decoded['body'])) {
                $template = $decoded;
            }
        }

        // Fall back to defaults
        if ($template === null) {
            $template = self::DEFAULT_TEMPLATES[$eventType][$channel] ?? [
                'subject' => '',
                'body' => '',
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
    public function render(string $eventType, string $channel, array $data): array
    {
        $this->loadLegalContent();
        $template = $this->getTemplate($eventType, $channel);

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
            $body = strtr($body, $placeholders);
        }

        return [
            'subject' => $subject,
            'body' => $body,
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
                $dt = new \DateTime($data['start_datetime']);
                $appointmentDate = $dt->format('l, F j, Y'); // Monday, January 15, 2025
                $appointmentTime = $dt->format('g:i A');     // 2:30 PM
                $appointmentDatetime = $dt->format('l, F j, Y \a\t g:i A');
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
        $termsLink = $this->legalContent['terms_url'] ?: ($this->legalContent['terms'] ? '[Terms & Conditions]' : '');
        $privacyLink = $this->legalContent['privacy_url'] ?: ($this->legalContent['privacy'] ? '[Privacy Policy]' : '');

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
            '{booking_url}' => $data['booking_url'] ?? base_url('booking'),
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
            'business_name' => $this->legalContent['business_name'] ?? 'WebSchedulr',
        ];

        return $this->render($eventType, $channel, $sampleData);
    }
}
