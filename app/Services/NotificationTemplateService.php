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
        // Booking reference and calendar
        '{booking_reference}' => 'Formatted booking reference (e.g. WS-2026-0042)',
        '{calendar_link}' => 'Google Calendar add-to-calendar link',
        // Map navigation links (resolved from appointment location → business address fallback)
        '{google_maps_link}' => 'Google Maps URL for resolved appointment location',
        '{waze_link}' => 'Waze navigation URL for resolved appointment location',
        // Business hours
        '{business_hours}' => 'Business opening hours (formatted, one line per day)',
        // Video / delivery mode
        '{delivery_mode}' => 'Session type (In Person / Zoom / Jitsi Meet)',
        '{video_link}'    => 'Video meeting join link (empty for in-person appointments)',
        '{session_info}'  => 'Full session block: Maps/Waze location block for in-person, or video meeting URL block for online appointments (auto-formatted multi-line)',
        '{payment_info}'  => 'Deposit payment block (deposit paid, outstanding balance, payment ref). Empty string when no deposit was taken.',
    ];

    /**
     * Default templates for each event type and channel
     */
    private const DEFAULT_TEMPLATES = [
        'appointment_pending' => [
            'email' => [
                'subject' => 'Your Appointment Request is Received — {service_name}',
                'body' => <<<'HTML'
<p class="greeting">Hi {customer_first_name},</p>
<p>We have received your booking request. We will confirm your appointment shortly.</p>
<table class="details-card" role="presentation"><tr><td>
<p class="card-title">Appointment details</p>
<p class="detail-row"><span class="detail-label">📅 Date:</span> <span class="detail-value">{appointment_date}</span></p>
<p class="detail-row"><span class="detail-label">🕐 Time:</span> <span class="detail-value">{appointment_time}</span></p>
<p class="detail-row"><span class="detail-label">💼 Service:</span> <span class="detail-value">{service_name}</span></p>
<p class="detail-row"><span class="detail-label">👤 Provider:</span> <span class="detail-value">{provider_name}</span></p>
<p class="detail-row"><span class="detail-label">⏱ Duration:</span> <span class="detail-value">{service_duration} minutes</span></p>
</td></tr></table>
{session_info}
{payment_info}
<p class="detail-row"><span class="detail-label">☎ Enquiries:</span> <a href="mailto:{business_email}">{business_email}</a> · {business_phone}</p>
<table class="details-card" role="presentation"><tr><td>
<p class="card-title">🕰 Business hours</p>
<p class="detail-value">{business_hours}</p>
</td></tr></table>
<p class="muted">Booking reference: <span class="ref">#{booking_reference}</span><br>Booked for {customer_name} · {customer_phone} · {customer_email}</p>
<p>We will notify you as soon as your appointment is confirmed.</p>
<p class="muted">{cancellation_policy}<br>{rescheduling_policy}</p>
<hr class="divider">
<div class="cta">
<a class="btn" href="{reschedule_link}">Manage your appointment</a>
<a class="btn btn-secondary" href="{calendar_link}">Add to calendar</a>
</div>
<p class="muted">If a button does not work, copy this link:<br><span class="copy-link">{reschedule_link}</span></p>
<hr class="divider">
<p class="muted"><strong>{business_name}</strong><br><a href="{terms_link}">Terms</a> · <a href="{privacy_link}">Privacy</a></p>
HTML
            ],
            'sms' => [
                'body' => "⏳ Booking received! {service_name} on {appointment_date} at {appointment_time}. Pending confirmation. Ref #{booking_reference}. Manage: {reschedule_link}"
            ],
            'whatsapp' => [
                'body' => "⏳ *Appointment Request Received*\n\nHi {customer_first_name}!\n\nWe've received your booking request and will confirm your appointment shortly.\n\n*📅 Date:* {appointment_date}\n*🕐 Time:* {appointment_time}\n*💼 Service:* {service_name}\n*👤 Provider:* {provider_name}\n*⏱ Duration:* {service_duration} minutes\n{session_info}\n\n*Booking Ref:* #{booking_reference}\n{payment_info}\n{cancellation_policy}\n\nView / Reschedule / Cancel: {reschedule_link}\nAdd to Calendar: {calendar_link}\n\nWe will notify you once confirmed.\n\n_{business_name}_\n{terms_link} | {privacy_link}"
            ]
        ],
        'appointment_confirmed' => [
            'email' => [
                'subject' => 'Your Appointment is Confirmed — {appointment_date} at {appointment_time}',
                'body' => <<<'HTML'
<p class="greeting">Hi {customer_first_name},</p>
<p>Thank you for booking with <strong>{business_name}</strong>. Your appointment is confirmed ✓</p>
<table class="details-card" role="presentation"><tr><td>
<p class="card-title">Appointment details</p>
<p class="detail-row"><span class="detail-label">📅 Date:</span> <span class="detail-value">{appointment_date}</span></p>
<p class="detail-row"><span class="detail-label">🕐 Time:</span> <span class="detail-value">{appointment_time}</span></p>
<p class="detail-row"><span class="detail-label">💼 Service:</span> <span class="detail-value">{service_name}</span></p>
<p class="detail-row"><span class="detail-label">👤 Provider:</span> <span class="detail-value">{provider_name}</span></p>
<p class="detail-row"><span class="detail-label">⏱ Duration:</span> <span class="detail-value">{service_duration} minutes</span></p>
</td></tr></table>
{session_info}
{payment_info}
<p class="detail-row"><span class="detail-label">☎ Enquiries:</span> <a href="mailto:{business_email}">{business_email}</a> · {business_phone}</p>
<table class="details-card" role="presentation"><tr><td>
<p class="card-title">🕰 Business hours</p>
<p class="detail-value">{business_hours}</p>
</td></tr></table>
<p class="muted">Booking reference: <span class="ref">#{booking_reference}</span><br>Booked for {customer_name} · {customer_phone} · {customer_email}</p>
<p>Please arrive 5–10 minutes early and bring any relevant documentation.</p>
<p class="muted">{cancellation_policy}<br>{rescheduling_policy}</p>
<hr class="divider">
<div class="cta">
<a class="btn" href="{reschedule_link}">Manage your appointment</a>
<a class="btn btn-secondary" href="{calendar_link}">Add to calendar</a>
</div>
<p class="muted">If a button does not work, copy this link:<br><span class="copy-link">{reschedule_link}</span></p>
<hr class="divider">
<p class="muted"><strong>{business_name}</strong><br><a href="{terms_link}">Terms</a> · <a href="{privacy_link}">Privacy</a></p>
HTML
            ],
            'sms' => [
                'body' => "✅ Confirmed: {service_name} with {provider_name} on {appointment_date} at {appointment_time}. Ref #{booking_reference}. Manage: {reschedule_link}"
            ],
            'whatsapp' => [
                'body' => "✅ *Appointment Confirmed*\n\nHi {customer_first_name}!\n\nThank you for booking with {business_name}! Your appointment is confirmed ✓\n\n*📅 Date:* {appointment_date}\n*🕐 Time:* {appointment_time}\n*💼 Service:* {service_name}\n*👤 Provider:* {provider_name}\n*⏱ Duration:* {service_duration} minutes\n{session_info}\n\n*Booking Ref:* #{booking_reference}\n{payment_info}\n{cancellation_policy}\n\nView / Reschedule / Cancel: {reschedule_link}\nAdd to Calendar: {calendar_link}\n\n_{business_name}_\n{terms_link} | {privacy_link}"
            ]
        ],
        'appointment_reminder' => [
            'email' => [
                'subject' => 'Reminder: Your Appointment — {appointment_date} at {appointment_time}',
                'body' => <<<'HTML'
<p class="greeting">Hi {customer_first_name},</p>
<p>This is a friendly reminder of your upcoming appointment.</p>
<table class="details-card" role="presentation"><tr><td>
<p class="card-title">Appointment details</p>
<p class="detail-row"><span class="detail-label">📅 Date:</span> <span class="detail-value">{appointment_date}</span></p>
<p class="detail-row"><span class="detail-label">🕐 Time:</span> <span class="detail-value">{appointment_time}</span></p>
<p class="detail-row"><span class="detail-label">💼 Service:</span> <span class="detail-value">{service_name}</span></p>
<p class="detail-row"><span class="detail-label">👤 Provider:</span> <span class="detail-value">{provider_name}</span></p>
<p class="detail-row"><span class="detail-label">⏱ Duration:</span> <span class="detail-value">{service_duration} minutes</span></p>
</td></tr></table>
{session_info}
<p class="detail-row"><span class="detail-label">☎ Enquiries:</span> <a href="mailto:{business_email}">{business_email}</a> · {business_phone}</p>
<table class="details-card" role="presentation"><tr><td>
<p class="card-title">🕰 Business hours</p>
<p class="detail-value">{business_hours}</p>
</td></tr></table>
<p class="muted">Booking reference: <span class="ref">#{booking_reference}</span></p>
<p>Please arrive 5–10 minutes early. Contact us if your plans change.</p>
<p class="muted">{rescheduling_policy}</p>
<hr class="divider">
<div class="cta">
<a class="btn" href="{reschedule_link}">Manage your appointment</a>
<a class="btn btn-secondary" href="{calendar_link}">Add to calendar</a>
</div>
<p class="muted">If a button does not work, copy this link:<br><span class="copy-link">{reschedule_link}</span></p>
<hr class="divider">
<p class="muted"><strong>{business_name}</strong><br><a href="{terms_link}">Terms</a> · <a href="{privacy_link}">Privacy</a></p>
HTML
            ],
            'sms' => [
                'body' => "⏰ Reminder: {service_name} with {provider_name} on {appointment_date} at {appointment_time}. {delivery_mode}. Manage: {reschedule_link}"
            ],
            'whatsapp' => [
                'body' => "⏰ *Appointment Reminder*\n\nHi {customer_first_name}!\n\nDon't forget — you have an upcoming appointment!\n\n*📅 Date:* {appointment_date}\n*🕐 Time:* {appointment_time}\n*💼 Service:* {service_name}\n*👤 Provider:* {provider_name}\n*⏱ Duration:* {service_duration} minutes\n{session_info}\n\n*Booking Ref:* #{booking_reference}\n\nPlease arrive 5–10 minutes early. Contact us if your plans change.\n\nView / Reschedule / Cancel: {reschedule_link}\nAdd to Calendar: {calendar_link}\n\n_{business_name}_"
            ]
        ],
        'appointment_cancelled' => [
            'email' => [
                'subject' => 'Your Appointment Has Been Cancelled — {service_name} on {appointment_date}',
                'body' => <<<'HTML'
<p class="greeting">Hi {customer_first_name},</p>
<p>Your appointment has been cancelled.</p>
<table class="details-card" role="presentation"><tr><td>
<p class="card-title">Cancelled appointment</p>
<p class="detail-row"><span class="detail-label">📅 Date:</span> <span class="detail-value">{appointment_date}</span></p>
<p class="detail-row"><span class="detail-label">🕐 Time:</span> <span class="detail-value">{appointment_time}</span></p>
<p class="detail-row"><span class="detail-label">💼 Service:</span> <span class="detail-value">{service_name}</span></p>
<p class="detail-row"><span class="detail-label">👤 Provider:</span> <span class="detail-value">{provider_name}</span></p>
</td></tr></table>
<p class="detail-row"><span class="detail-label">☎ Enquiries:</span> <a href="mailto:{business_email}">{business_email}</a> · {business_phone}</p>
<p class="muted">Booking reference: <span class="ref">#{booking_reference}</span></p>
<p>We hope to see you again soon.</p>
<hr class="divider">
<div class="cta">
<a class="btn" href="{booking_url}">Book a new appointment</a>
</div>
<p class="muted">If a button does not work, copy this link:<br><span class="copy-link">{booking_url}</span></p>
<hr class="divider">
<p class="muted"><strong>{business_name}</strong><br><a href="{terms_link}">Terms</a> · <a href="{privacy_link}">Privacy</a></p>
HTML
            ],
            'sms' => [
                'body' => "❌ Cancelled: {service_name} on {appointment_date}. Rebook: {booking_url}"
            ],
            'whatsapp' => [
                'body' => "❌ *Appointment Cancelled*\n\nHi {customer_first_name},\n\nYour appointment has been cancelled.\n\n*📅 Date:* {appointment_date}\n*🕐 Time:* {appointment_time}\n*💼 Service:* {service_name}\n*👤 Provider:* {provider_name}\n\n*Booking Ref:* #{booking_reference}\n\nWe hope to see you again soon! Book a new appointment:\n{booking_url}\n\n_{business_name}_"
            ]
        ],
        'appointment_rescheduled' => [
            'email' => [
                'subject' => 'Your Appointment Has Been Rescheduled — {appointment_date} at {appointment_time}',
                'body' => <<<'HTML'
<p class="greeting">Hi {customer_first_name},</p>
<p>Your appointment has been moved to a new date and time.</p>
<table class="details-card" role="presentation"><tr><td>
<p class="card-title">New date &amp; time</p>
<p class="detail-row"><span class="detail-label">📅 Date:</span> <span class="detail-value">{appointment_date}</span></p>
<p class="detail-row"><span class="detail-label">🕐 Time:</span> <span class="detail-value">{appointment_time}</span></p>
<p class="detail-row"><span class="detail-label">💼 Service:</span> <span class="detail-value">{service_name}</span></p>
<p class="detail-row"><span class="detail-label">👤 Provider:</span> <span class="detail-value">{provider_name}</span></p>
<p class="detail-row"><span class="detail-label">⏱ Duration:</span> <span class="detail-value">{service_duration} minutes</span></p>
</td></tr></table>
{session_info}
<p class="detail-row"><span class="detail-label">☎ Enquiries:</span> <a href="mailto:{business_email}">{business_email}</a> · {business_phone}</p>
<table class="details-card" role="presentation"><tr><td>
<p class="card-title">🕰 Business hours</p>
<p class="detail-value">{business_hours}</p>
</td></tr></table>
<p class="muted">Booking reference: <span class="ref">#{booking_reference}</span><br>Booked for {customer_name} · {customer_phone} · {customer_email}</p>
<p class="muted">{rescheduling_policy}</p>
<hr class="divider">
<div class="cta">
<a class="btn" href="{reschedule_link}">Manage your appointment</a>
<a class="btn btn-secondary" href="{calendar_link}">Add to calendar</a>
</div>
<p class="muted">If a button does not work, copy this link:<br><span class="copy-link">{reschedule_link}</span></p>
<hr class="divider">
<p class="muted"><strong>{business_name}</strong><br><a href="{terms_link}">Terms</a> · <a href="{privacy_link}">Privacy</a></p>
HTML
            ],
            'sms' => [
                'body' => "📅 Rescheduled: {service_name} is now {appointment_date} at {appointment_time}. Ref #{booking_reference}. Manage: {reschedule_link}"
            ],
            'whatsapp' => [
                'body' => "📅 *Appointment Rescheduled*\n\nHi {customer_first_name}!\n\nYour appointment has been moved to a new date and time.\n\n*New Date & Time*\n*📅 Date:* {appointment_date}\n*🕐 Time:* {appointment_time}\n*💼 Service:* {service_name}\n*👤 Provider:* {provider_name}\n*⏱ Duration:* {service_duration} minutes\n{session_info}\n\n*Booking Ref:* #{booking_reference}\n\nView / Reschedule / Cancel: {reschedule_link}\nAdd to Calendar: {calendar_link}\n\n_{business_name}_"
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
                    . "{session_info}\n\n"
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
                    . "{session_info}\n\n"
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
                    . "{session_info}\n\n"
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
                    . "{session_info}\n\n"
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
                    . "{session_info}\n\n"
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
            'general.telephone_number',
            'general.mobile_number',
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
            'business_phone' => $settings['general.telephone_number']
                ?? $settings['general.mobile_number']
                ?? $settings['general.company_phone']
                ?? '',
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

        // The email channel renders HTML when the template body is an HTML fragment
        // (redesigned customer templates). Plain-text templates — internal, legacy, or
        // admin-customised — stay plain text and are converted by EmailBodyRenderer's
        // safety net at send time. SMS/WhatsApp are always plain text.
        $isHtml = ($channel === 'email') && EmailBodyRenderer::isHtmlBody((string) ($template['body'] ?? ''));

        // Prepare placeholders (rich blocks render HTML only when the body is HTML)
        $placeholders = $this->buildPlaceholders($data, $isHtml);

        // Render subject — always plain text, so use raw (unescaped) placeholder values.
        $subject = $template['subject'] ?? '';
        if ($subject !== '') {
            $subject = strtr($subject, $placeholders);
        }

        // Render body — HTML bodies use escaped scalar values to stay markup-safe.
        $body = $template['body'] ?? '';
        if ($body !== '') {
            $body = $this->ensureBodyContainsRequiredPlaceholders($eventType, $channel, $body, $data);
            $bodyPlaceholders = $isHtml ? $this->escapeScalarPlaceholders($placeholders) : $placeholders;
            $body = strtr($body, $bodyPlaceholders);
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
     * @param bool  $html When true, the rich-block placeholders ({session_info},
     *                    {payment_info}, {business_hours}) render HTML fragments instead
     *                    of plain text. Driven by whether the template body is HTML —
     *                    see render(). Scalar values are returned raw here; body-only
     *                    HTML escaping is applied in render().
     * @return array Placeholder => value mapping
     */
    private function buildPlaceholders(array $data, bool $html = false): array
    {
        // Extract appointment date/time
        $appointmentDate = '';
        $appointmentTime = '';
        $appointmentDatetime = '';

        if (!empty($data['start_datetime'])) {
            try {
                $loc = new LocalizationSettingsService();
                $displayTz = $data['display_timezone'] ?? 'UTC';
                $dt = new \DateTime($data['start_datetime'], new \DateTimeZone($displayTz));
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

        // Build booking reference (WS-{year}-{id})
        $apptId = (int) ($data['booking_id'] ?? $data['appointment_id'] ?? 0);
        if (!empty($data['start_datetime'])) {
            try {
                $refYear = (new \DateTime($data['start_datetime'], new \DateTimeZone($data['display_timezone'] ?? 'UTC')))->format('Y');
            } catch (\Exception $e) {
                $refYear = date('Y');
            }
        } else {
            $refYear = date('Y');
        }
        $bookingReference = $apptId > 0
            ? 'WS-' . $refYear . '-' . str_pad($apptId, 4, '0', STR_PAD_LEFT)
            : '';

        // Resolve location with fallback to business address
        $resolvedLocationName    = trim((string) ($data['location_name'] ?? ''));
        $resolvedLocationAddress = trim((string) ($data['location_address'] ?? ''));
        $resolvedLocationContact = trim((string) ($data['location_contact'] ?? ''));
        if ($resolvedLocationAddress === '') {
            $resolvedLocationAddress = (string) ($this->legalContent['business_address'] ?? '');
        }

        // Build Google Maps and Waze navigation links from resolved address
        $googleMapsLink = '';
        $wazeLink       = '';
        $mapQuery = trim($resolvedLocationName . ' ' . $resolvedLocationAddress);
        if ($mapQuery !== '') {
            $enc            = urlencode($mapQuery);
            $googleMapsLink = 'https://www.google.com/maps/search/?api=1&query=' . $enc;
            $wazeLink       = 'https://waze.com/ul?q=' . $enc . '&navigate=yes';
        }

        // Build Google Calendar add-to-calendar link
        $calendarLink = '';
        if (!empty($data['start_datetime'])) {
            try {
                $calDisplayTz = $data['display_timezone'] ?? 'UTC';
                $calDt    = new \DateTime($data['start_datetime'], new \DateTimeZone($calDisplayTz));
                $calDtUtc = (clone $calDt)->setTimezone(new \DateTimeZone('UTC'));
                $calStart = $calDtUtc->format('Ymd\THis\Z');
                $calDurationMinutes = (int) ($data['service_duration'] ?? $data['service']['duration'] ?? 60);
                if ($calDurationMinutes < 1) {
                    $calDurationMinutes = 60;
                }
                $calEndUtc = clone $calDtUtc;
                $calEndUtc->modify('+' . $calDurationMinutes . ' minutes');
                $calEnd      = $calEndUtc->format('Ymd\THis\Z');
                $calTitle    = urlencode(
                    ($data['service_name'] ?? $data['service']['name'] ?? 'Appointment')
                    . ' with '
                    . ($data['provider_name'] ?? $data['provider']['name'] ?? '')
                );
                $calDetails  = urlencode('Booking ref: ' . $bookingReference);
                $calLocation = urlencode(trim(
                    $resolvedLocationName . ' ' . $resolvedLocationAddress
                ));
                $calendarLink = 'https://calendar.google.com/calendar/render?action=TEMPLATE'
                    . '&text=' . $calTitle
                    . '&dates=' . $calStart . '/' . $calEnd
                    . '&details=' . $calDetails
                    . '&location=' . $calLocation;
            } catch (\Exception $e) {
                $calendarLink = '';
            }
        }

        $placeholders = [
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
            // Location placeholders (resolved: appointment snapshot → business address fallback)
            '{location_name}' => $resolvedLocationName,
            '{location_address}' => $resolvedLocationAddress,
            '{location_contact}' => $resolvedLocationContact,
            // Booking reference and calendar link
            '{booking_reference}' => $bookingReference,
            '{calendar_link}' => $calendarLink,
            // Map navigation links
            '{google_maps_link}' => $googleMapsLink,
            '{waze_link}' => $wazeLink,
            // Business hours
            '{business_hours}' => $this->buildBusinessHoursText($html),
            // Video / delivery mode
            '{delivery_mode}' => $data['delivery_mode'] ?? 'In Person',
            '{video_link}'    => $data['video_link']    ?? '',
            '{payment_info}'  => $this->buildPaymentInfoBlock($data, $html),
            '{session_info}'  => $this->buildSessionInfo(
                $data['delivery_mode']    ?? '',
                $data['video_link']       ?? '',
                $resolvedLocationName,
                $resolvedLocationAddress,
                $googleMapsLink,
                $wazeLink,
                $html,
            ),
        ];

        return $placeholders;
    }

    /**
     * Placeholders whose values are trusted HTML fragments in email (HTML) mode and
     * must NOT be HTML-escaped when substituted into an HTML body.
     *
     * @var array<int, string>
     */
    private const RAW_HTML_PLACEHOLDERS = ['{session_info}', '{payment_info}', '{business_hours}'];

    /**
     * Return a copy of the placeholder map with scalar (non-block) values HTML-escaped,
     * for substitution into an HTML email body. Prevents customer/business data from
     * breaking the markup or injecting tags. The rich-block placeholders are passed
     * through untouched because they already emit trusted HTML.
     *
     * @param array<string, mixed> $placeholders
     * @return array<string, mixed>
     */
    private function escapeScalarPlaceholders(array $placeholders): array
    {
        foreach ($placeholders as $key => $value) {
            if (!in_array($key, self::RAW_HTML_PLACEHOLDERS, true)) {
                $placeholders[$key] = esc((string) $value);
            }
        }

        return $placeholders;
    }

    /**
     * Build the payment info block for notification templates.
     * Returns a formatted multi-line section for paid or pending-payment appointments.
     * Returns '' when no deposit applies (payment_status = 'none' / no amount set).
     */
    private function buildPaymentInfoBlock(array $data, bool $html = false): string
    {
        $status = (string) ($data['payment_status'] ?? '');
        $amount = empty($data['payment_amount']) ? 0.0 : (float) $data['payment_amount'];
        $ref    = (string) ($data['payment_reference'] ?? '');

        if ($status === 'paid' && $amount > 0) {
            $loc     = new LocalizationSettingsService();
            $price   = (float) ($data['service_price'] ?? 0);
            $balance = $price > 0 ? max(0.0, $price - $amount) : 0.0;

            if ($html) {
                $rows = '<p class="detail-row"><span class="detail-label">💳 Deposit paid:</span> '
                    . '<span class="detail-value">' . esc($loc->formatCurrency($amount)) . '</span></p>';
                if ($balance > 0) {
                    $rows .= '<p class="detail-row"><span class="detail-label">💰 Outstanding:</span> '
                        . '<span class="detail-value">' . esc($loc->formatCurrency($balance)) . '</span> '
                        . '<span class="muted">(payable on the day)</span></p>';
                }
                if ($ref !== '') {
                    $rows .= '<p class="detail-row"><span class="detail-label">🔑 Payment ref:</span> '
                        . '<span class="detail-value">' . esc($ref) . '</span></p>';
                }
                return $this->paymentCardHtml('Payment', $rows);
            }

            $lines   = [];
            $lines[] = "── PAYMENT ──────────────────────────────";
            $lines[] = "💳 Deposit paid:    " . $loc->formatCurrency($amount);
            if ($balance > 0) {
                $lines[] = "💰 Outstanding:     " . $loc->formatCurrency($balance) . " (payable on the day)";
            }
            if ($ref !== '') {
                $lines[] = "🔑 Payment ref:     " . $ref;
            }
            $lines[] = "─────────────────────────────────────────";
            return implode("\n", $lines) . "\n";
        }

        if ($status === 'pending' && $amount > 0) {
            $loc     = new LocalizationSettingsService();

            if ($html) {
                $rows = '<p class="detail-row"><span class="detail-label">💳 Deposit due:</span> '
                    . '<span class="detail-value">' . esc($loc->formatCurrency($amount)) . '</span></p>'
                    . '<p class="muted">Your appointment will be confirmed once payment is received.</p>';
                return $this->paymentCardHtml('Deposit required', $rows);
            }

            $lines   = [];
            $lines[] = "── DEPOSIT REQUIRED ─────────────────────";
            $lines[] = "💳 Deposit due:     " . $loc->formatCurrency($amount);
            $lines[] = "   Your appointment will be confirmed once payment is received.";
            $lines[] = "─────────────────────────────────────────";
            return implode("\n", $lines) . "\n";
        }

        return '';
    }

    /**
     * Wrap payment rows in an HTML details card (email channel only).
     */
    private function paymentCardHtml(string $title, string $rowsHtml): string
    {
        return '<table class="details-card" role="presentation"><tr><td>'
            . '<p class="card-title">' . esc($title) . '</p>'
            . $rowsHtml
            . '</td></tr></table>';
    }

    /**
     * Build the session info block for notifications.
     *
     * For online appointments: shows the delivery mode and video join URL.
     * For in-person appointments: reproduces the full location block including Maps/Waze links.
     * Accepts both raw DB values ('online_zoom', 'online_jitsi') and the human-readable labels
     * ('Zoom', 'Jitsi Meet') written by the dispatcher into $templateData.
     */
    private function buildSessionInfo(
        string $mode,
        string $videoLink,
        string $locationName,
        string $locationAddress,
        string $googleMapsLink,
        string $wazeLink,
        bool $html = false
    ): string {
        $isZoom  = in_array($mode, ['online_zoom', 'Zoom'], true);
        $isJitsi = in_array($mode, ['online_jitsi', 'Jitsi Meet'], true);

        if ($isZoom || $isJitsi) {
            $provider = $isZoom ? 'Zoom' : 'Jitsi Meet';
            if ($html) {
                return $this->sessionInfoOnlineHtml($provider, $videoLink);
            }
            $linkLine = $videoLink !== ''
                ? "   Join URL:  {$videoLink}"
                : "   (Meeting link will be sent separately)";
            return "🎥 Online Session ({$provider})\n{$linkLine}";
        }

        // In-person: produce the full rich block only when we have address data
        if ($locationName === '' && $locationAddress === '') {
            return '';
        }

        if ($html) {
            return $this->sessionInfoInPersonHtml($locationName, $locationAddress, $googleMapsLink, $wazeLink);
        }

        $lines   = [];
        $lines[] = "📍 Location:  {$locationName}";
        if ($locationAddress !== '') {
            $lines[] = "              {$locationAddress}";
        }
        if ($googleMapsLink !== '' || $wazeLink !== '') {
            $lines[] = "   Maps: {$googleMapsLink} | Waze: {$wazeLink}";
        }
        return implode("\n", $lines);
    }

    /**
     * HTML session block for an online appointment (email channel only).
     */
    private function sessionInfoOnlineHtml(string $provider, string $videoLink): string
    {
        $cta = $videoLink !== ''
            ? '<div class="cta"><a class="btn" href="' . esc($videoLink) . '">🎥 Join session</a></div>'
            : '<p class="muted">The meeting link will be sent to you separately.</p>';

        return '<table class="details-card" role="presentation"><tr><td>'
            . '<p class="card-title">🎥 Online Session (' . esc($provider) . ')</p>'
            . $cta
            . '</td></tr></table>';
    }

    /**
     * HTML session block for an in-person appointment, with Maps/Waze buttons
     * (email channel only).
     */
    private function sessionInfoInPersonHtml(
        string $locationName,
        string $locationAddress,
        string $googleMapsLink,
        string $wazeLink
    ): string {
        $value = '';
        if ($locationName !== '') {
            $value .= '<strong>' . esc($locationName) . '</strong>';
        }
        if ($locationAddress !== '') {
            $value .= ($value !== '' ? '<br>' : '') . esc($locationAddress);
        }

        $buttons = '';
        if ($googleMapsLink !== '') {
            $buttons .= '<a class="btn" href="' . esc($googleMapsLink) . '">📍 Open in Google Maps</a>';
        }
        if ($wazeLink !== '') {
            $buttons .= '<a class="btn btn-secondary" href="' . esc($wazeLink) . '">🚗 Open in Waze</a>';
        }

        return '<table class="details-card" role="presentation"><tr><td>'
            . '<p class="card-title">📍 Location</p>'
            . '<p class="detail-value">' . $value . '</p>'
            . ($buttons !== '' ? '<div class="cta">' . $buttons . '</div>' : '')
            . '</td></tr></table>';
    }

    /**
     * Build a formatted business hours text block for notification templates.
     * Reads global bounds (business.work_start / business.work_end) from xs_settings.
     * xs_business_hours rows are all per-provider — no global rows exist.
     */
    private function buildBusinessHoursText(bool $html = false): string
    {
        $hours = $this->buildBusinessHoursFromDefaultSettings();
        $text  = $hours !== '' ? $hours : 'Please contact us for business hours.';

        // Email channel: preserve the per-day line breaks as HTML so they don't collapse.
        return $html ? nl2br(esc($text), false) : $text;
    }

    /**
     * Build weekday business hours from Settings > Business Hours defaults.
     */
    private function buildBusinessHoursFromDefaultSettings(): string
    {
        try {
            $settings = $this->settingModel->getByKeys([
                'business.work_start',
                'business.work_end',
            ]);

            $start = trim((string) ($settings['business.work_start'] ?? ''));
            $end = trim((string) ($settings['business.work_end'] ?? ''));

            if ($start === '' || $end === '') {
                return '';
            }

            $start = substr($start, 0, 5);
            $end = substr($end, 0, 5);
            if (!preg_match('/^\d{2}:\d{2}$/', $start) || !preg_match('/^\d{2}:\d{2}$/', $end)) {
                return '';
            }

            $lines = [];
            $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            $openDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'];

            foreach ($days as $day) {
                if (in_array($day, $openDays, true)) {
                    $lines[] = sprintf('  %-4s %s – %s', $day, $start, $end);
                } else {
                    $lines[] = sprintf('  %-4s Closed', $day);
                }
            }

            return implode("\n", $lines);
        } catch (\Throwable $e) {
            return '';
        }
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
