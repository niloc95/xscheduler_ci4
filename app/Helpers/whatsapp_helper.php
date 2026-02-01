<?php

/**
 * =============================================================================
 * WHATSAPP HELPER
 * =============================================================================
 * 
 * @file        app/Helpers/whatsapp_helper.php
 * @description Helper functions for generating WhatsApp wa.me links.
 *              Enables zero-configuration WhatsApp messaging without API setup.
 * 
 * LOADING:
 * -----------------------------------------------------------------------------
 * Load manually where needed:
 *     helper('whatsapp');
 * 
 * AVAILABLE FUNCTIONS:
 * -----------------------------------------------------------------------------
 * whatsapp_link($phone, $message)
 *   Generate a wa.me link with pre-filled message
 *   Example: whatsapp_link('+27123456789', 'Hello!')
 *            => 'https://wa.me/27123456789?text=Hello%21'
 * 
 * whatsapp_appointment_confirmed_message($appointment, $provider, $service, $business)
 *   Generate appointment confirmation message
 * 
 * whatsapp_appointment_reminder_message($appointment, $provider, $service, $business)
 *   Generate appointment reminder message
 * 
 * whatsapp_appointment_cancelled_message($appointment, $provider, $service, $business)
 *   Generate cancellation notification message
 * 
 * whatsapp_appointment_rescheduled_message($appointment, $provider, $service, $business)
 *   Generate reschedule notification message
 * 
 * PHONE NUMBER FORMAT:
 * -----------------------------------------------------------------------------
 * - Accepts: +27123456789, 27123456789, 0123456789
 * - Strips: spaces, dashes, parentheses
 * - Output: Pure digits for wa.me URL
 * 
 * MESSAGE TEMPLATES:
 * -----------------------------------------------------------------------------
 * All message functions return formatted text with:
 * - Business name and greeting
 * - Appointment details (date, time, service)
 * - Provider name (if applicable)
 * - Location details (if applicable)
 * - Action prompts
 * 
 * ZERO-CONFIG APPROACH:
 * -----------------------------------------------------------------------------
 * These links open WhatsApp without any API keys or configuration.
 * The user clicks the link and WhatsApp opens with pre-filled message.
 * Perfect for small businesses without API budget.
 * 
 * @see         app/Services/NotificationWhatsAppService.php for API-based approach
 * @package     App\Helpers
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

if (!function_exists('whatsapp_link')) {
    /**
     * Generate a WhatsApp wa.me link with pre-filled message
     *
     * @param string $phone Phone number in E.164 format (e.g., +15551234567)
     * @param string $message Pre-filled message text
     * @return string The wa.me URL
     */
    function whatsapp_link(string $phone, string $message = ''): string
    {
        // Strip non-numeric except leading +
        $phone = preg_replace('/[^\d]/', '', ltrim($phone, '+'));
        
        $url = 'https://wa.me/' . $phone;
        
        if ($message !== '') {
            $url .= '?text=' . rawurlencode($message);
        }
        
        return $url;
    }
}

if (!function_exists('whatsapp_appointment_confirmed_message')) {
    /**
     * Generate appointment confirmation message
     *
     * @param array $appointment Appointment data
     * @param array $provider Provider data
     * @param array $service Service data
     * @param array $business Business settings
     * @return string Formatted message
     */
    function whatsapp_appointment_confirmed_message(array $appointment, array $provider = [], array $service = [], array $business = []): string
    {
        $businessName = $business['business_name'] ?? 'Our Business';
        $customerName = $appointment['customer_name'] ?? $appointment['name'] ?? 'Valued Customer';
        $serviceName = $service['name'] ?? $appointment['service_name'] ?? 'Service';
        $providerName = $provider['name'] ?? $appointment['provider_name'] ?? '';
        
        $date = '';
        $time = '';
        if (!empty($appointment['start_datetime'])) {
            $dt = new DateTime($appointment['start_datetime']);
            $date = $dt->format('l, F j, Y'); // e.g., Monday, January 15, 2025
            $time = $dt->format('g:i A');     // e.g., 2:30 PM
        } elseif (!empty($appointment['date'])) {
            $date = $appointment['date'];
            $time = $appointment['time'] ?? '';
        }
        
        $message = "✅ *Appointment Confirmed*\n\n";
        $message .= "Hi {$customerName}!\n\n";
        $message .= "Your appointment has been confirmed:\n\n";
        $message .= "📅 *Date:* {$date}\n";
        $message .= "🕐 *Time:* {$time}\n";
        $message .= "💼 *Service:* {$serviceName}\n";
        
        if ($providerName) {
            $message .= "👤 *With:* {$providerName}\n";
        }
        
        $message .= "\nThank you for booking with {$businessName}!\n";
        $message .= "\n_Reply to this message if you need to make any changes._";
        
        return $message;
    }
}

if (!function_exists('whatsapp_appointment_reminder_message')) {
    /**
     * Generate appointment reminder message
     *
     * @param array $appointment Appointment data
     * @param array $provider Provider data
     * @param array $service Service data
     * @param array $business Business settings
     * @return string Formatted message
     */
    function whatsapp_appointment_reminder_message(array $appointment, array $provider = [], array $service = [], array $business = []): string
    {
        $businessName = $business['business_name'] ?? 'Our Business';
        $customerName = $appointment['customer_name'] ?? $appointment['name'] ?? 'Valued Customer';
        $serviceName = $service['name'] ?? $appointment['service_name'] ?? 'Service';
        $providerName = $provider['name'] ?? $appointment['provider_name'] ?? '';
        
        $date = '';
        $time = '';
        if (!empty($appointment['start_datetime'])) {
            $dt = new DateTime($appointment['start_datetime']);
            $date = $dt->format('l, F j, Y');
            $time = $dt->format('g:i A');
        } elseif (!empty($appointment['date'])) {
            $date = $appointment['date'];
            $time = $appointment['time'] ?? '';
        }
        
        $message = "⏰ *Appointment Reminder*\n\n";
        $message .= "Hi {$customerName}!\n\n";
        $message .= "This is a friendly reminder about your upcoming appointment:\n\n";
        $message .= "📅 *Date:* {$date}\n";
        $message .= "🕐 *Time:* {$time}\n";
        $message .= "💼 *Service:* {$serviceName}\n";
        
        if ($providerName) {
            $message .= "👤 *With:* {$providerName}\n";
        }
        
        $message .= "\nWe look forward to seeing you!\n";
        $message .= "\n_{$businessName}_";
        
        return $message;
    }
}

if (!function_exists('whatsapp_appointment_cancelled_message')) {
    /**
     * Generate appointment cancellation message
     *
     * @param array $appointment Appointment data
     * @param array $provider Provider data
     * @param array $service Service data
     * @param array $business Business settings
     * @return string Formatted message
     */
    function whatsapp_appointment_cancelled_message(array $appointment, array $provider = [], array $service = [], array $business = []): string
    {
        $businessName = $business['business_name'] ?? 'Our Business';
        $customerName = $appointment['customer_name'] ?? $appointment['name'] ?? 'Valued Customer';
        $serviceName = $service['name'] ?? $appointment['service_name'] ?? 'Service';
        
        $date = '';
        $time = '';
        if (!empty($appointment['start_datetime'])) {
            $dt = new DateTime($appointment['start_datetime']);
            $date = $dt->format('l, F j, Y');
            $time = $dt->format('g:i A');
        } elseif (!empty($appointment['date'])) {
            $date = $appointment['date'];
            $time = $appointment['time'] ?? '';
        }
        
        $message = "❌ *Appointment Cancelled*\n\n";
        $message .= "Hi {$customerName},\n\n";
        $message .= "Your appointment has been cancelled:\n\n";
        $message .= "📅 *Date:* {$date}\n";
        $message .= "🕐 *Time:* {$time}\n";
        $message .= "💼 *Service:* {$serviceName}\n";
        $message .= "\nWe hope to see you again soon!\n";
        $message .= "\nTo reschedule, please contact us or book online.\n";
        $message .= "\n_{$businessName}_";
        
        return $message;
    }
}

if (!function_exists('whatsapp_appointment_rescheduled_message')) {
    /**
     * Generate appointment rescheduled message
     *
     * @param array $appointment New appointment data
     * @param array $oldAppointment Previous appointment data (optional)
     * @param array $provider Provider data
     * @param array $service Service data
     * @param array $business Business settings
     * @return string Formatted message
     */
    function whatsapp_appointment_rescheduled_message(array $appointment, array $oldAppointment = [], array $provider = [], array $service = [], array $business = []): string
    {
        $businessName = $business['business_name'] ?? 'Our Business';
        $customerName = $appointment['customer_name'] ?? $appointment['name'] ?? 'Valued Customer';
        $serviceName = $service['name'] ?? $appointment['service_name'] ?? 'Service';
        $providerName = $provider['name'] ?? $appointment['provider_name'] ?? '';
        
        $newDate = '';
        $newTime = '';
        if (!empty($appointment['start_datetime'])) {
            $dt = new DateTime($appointment['start_datetime']);
            $newDate = $dt->format('l, F j, Y');
            $newTime = $dt->format('g:i A');
        } elseif (!empty($appointment['date'])) {
            $newDate = $appointment['date'];
            $newTime = $appointment['time'] ?? '';
        }
        
        $message = "📅 *Appointment Rescheduled*\n\n";
        $message .= "Hi {$customerName}!\n\n";
        $message .= "Your appointment has been rescheduled to:\n\n";
        $message .= "📅 *New Date:* {$newDate}\n";
        $message .= "🕐 *New Time:* {$newTime}\n";
        $message .= "💼 *Service:* {$serviceName}\n";
        
        if ($providerName) {
            $message .= "👤 *With:* {$providerName}\n";
        }
        
        $message .= "\nPlease let us know if this doesn't work for you.\n";
        $message .= "\n_{$businessName}_";
        
        return $message;
    }
}

if (!function_exists('whatsapp_generate_link_for_event')) {
    /**
     * Generate WhatsApp link for a specific event type
     *
     * @param string $eventType Event type (appointment_confirmed, appointment_reminder, etc.)
     * @param string $phone Customer phone number
     * @param array $appointment Appointment data
     * @param array $provider Provider data
     * @param array $service Service data
     * @param array $business Business settings
     * @return string WhatsApp link URL
     */
    function whatsapp_generate_link_for_event(string $eventType, string $phone, array $appointment, array $provider = [], array $service = [], array $business = []): string
    {
        $message = '';
        
        switch ($eventType) {
            case 'appointment_confirmed':
            case 'appointment_created':
                $message = whatsapp_appointment_confirmed_message($appointment, $provider, $service, $business);
                break;
            case 'appointment_reminder':
                $message = whatsapp_appointment_reminder_message($appointment, $provider, $service, $business);
                break;
            case 'appointment_cancelled':
                $message = whatsapp_appointment_cancelled_message($appointment, $provider, $service, $business);
                break;
            case 'appointment_rescheduled':
                $message = whatsapp_appointment_rescheduled_message($appointment, [], $provider, $service, $business);
                break;
            default:
                // Generic message
                $message = "Hi! This is a message from " . ($business['business_name'] ?? 'our business') . " regarding your appointment.";
        }
        
        return whatsapp_link($phone, $message);
    }
}
