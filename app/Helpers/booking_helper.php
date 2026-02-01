<?php

/**
 * =============================================================================
 * BOOKING HELPER
 * =============================================================================
 * 
 * @file        app/Helpers/booking_helper.php
 * @description Helper functions for rendering dynamic booking form fields
 *              based on configurable settings.
 * 
 * LOADING:
 * -----------------------------------------------------------------------------
 * Load manually where needed:
 *     helper('booking');
 * 
 * AVAILABLE FUNCTIONS:
 * -----------------------------------------------------------------------------
 * render_booking_fields($settings, $values)
 *   Render HTML form fields for public booking form
 *   Supports custom fields with configurable display/required options
 * 
 * validate_booking_fields($settings, $data)
 *   Validate submitted booking form data against settings rules
 * 
 * CONFIGURABLE FIELDS:
 * -----------------------------------------------------------------------------
 * Standard Fields:
 * - first_names      : Customer first name(s)
 * - surname          : Customer surname
 * - email            : Customer email
 * - phone            : Customer phone number
 * 
 * Custom Fields (1-6):
 * - custom_field_1..6_enabled  : Enable/disable custom field
 * - custom_field_1..6_title    : Field label text
 * - custom_field_1..6_type     : Field type (text, textarea, select, checkbox)
 * - custom_field_1..6_required : Whether field is required
 * 
 * SETTING KEYS:
 * -----------------------------------------------------------------------------
 *     booking.first_names_display
 *     booking.first_names_required
 *     booking.custom_field_1_enabled
 *     booking.custom_field_1_title
 *     booking.custom_field_1_type
 *     booking.custom_field_1_required
 * 
 * @see         app/Models/SettingModel.php for settings storage
 * @see         app/Controllers/Api/Appointments.php for booking API
 * @package     App\Helpers
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

if (!function_exists('render_booking_fields')) {
    /**
     * Render booking form fields based on settings configuration
     * 
     * @param array $settings Settings array from SettingModel
     * @param array $values Optional pre-filled values
     * @return string HTML markup for booking fields
     */
    function render_booking_fields(array $settings = [], array $values = []): string
    {
        $html = '';
        
        // Name fields
        if (($settings['booking.first_names_display'] ?? '1') === '1') {
            $required = ($settings['booking.first_names_required'] ?? '1') === '1';
            $html .= '<div class="form-field">';
            $html .= '<label class="form-label">First Names' . ($required ? ' <span class="text-red-500">*</span>' : '') . '</label>';
            $html .= '<input type="text" name="first_names" class="form-input" value="' . esc($values['first_names'] ?? '') . '"' . ($required ? ' required' : '') . '>';
            $html .= '</div>';
        }
        
        if (($settings['booking.surname_display'] ?? '1') === '1') {
            $required = ($settings['booking.surname_required'] ?? '1') === '1';
            $html .= '<div class="form-field">';
            $html .= '<label class="form-label">Surname' . ($required ? ' <span class="text-red-500">*</span>' : '') . '</label>';
            $html .= '<input type="text" name="surname" class="form-input" value="' . esc($values['surname'] ?? '') . '"' . ($required ? ' required' : '') . '>';
            $html .= '</div>';
        }
        
        // Custom fields
        for ($i = 1; $i <= 6; $i++) {
            if (($settings["booking.custom_field_{$i}_enabled"] ?? '0') === '1') {
                $title = $settings["booking.custom_field_{$i}_title"] ?? "Custom Field {$i}";
                $type = $settings["booking.custom_field_{$i}_type"] ?? 'text';
                $required = ($settings["booking.custom_field_{$i}_required"] ?? '0') === '1';
                
                $html .= '<div class="form-field">';
                $html .= '<label class="form-label">' . esc($title) . ($required ? ' <span class="text-red-500">*</span>' : '') . '</label>';
                
                switch ($type) {
                    case 'textarea':
                        $html .= '<textarea name="custom_field_' . $i . '" class="form-input" rows="3"' . ($required ? ' required' : '') . '>' . esc($values["custom_field_{$i}"] ?? '') . '</textarea>';
                        break;
                    case 'select':
                        // Future implementation - for now render as text
                        $html .= '<input type="text" name="custom_field_' . $i . '" class="form-input" value="' . esc($values["custom_field_{$i}"] ?? '') . '"' . ($required ? ' required' : '') . '>';
                        break;
                    case 'checkbox':
                        // Future implementation - for now render as text
                        $html .= '<input type="text" name="custom_field_' . $i . '" class="form-input" value="' . esc($values["custom_field_{$i}"] ?? '') . '"' . ($required ? ' required' : '') . '>';
                        break;
                    case 'text':
                    default:
                        $html .= '<input type="text" name="custom_field_' . $i . '" class="form-input" value="' . esc($values["custom_field_{$i}"] ?? '') . '"' . ($required ? ' required' : '') . '>';
                        break;
                }
                
                $html .= '</div>';
            }
        }
        
        // Standard fields (email, phone, notes)
        $standardFields = $settings['booking.fields'] ?? ['email', 'phone'];
        if (is_string($standardFields)) {
            $standardFields = json_decode($standardFields, true) ?: [];
        }
        
        if (in_array('email', $standardFields)) {
            $html .= '<div class="form-field">';
            $html .= '<label class="form-label">Email <span class="text-red-500">*</span></label>';
            $html .= '<input type="email" name="email" class="form-input" value="' . esc($values['email'] ?? '') . '" required>';
            $html .= '</div>';
        }
        
        if (in_array('phone', $standardFields)) {
            $html .= '<div class="form-field">';
            $html .= '<label class="form-label">Phone</label>';
            $html .= '<input type="tel" name="phone" class="form-input" value="' . esc($values['phone'] ?? '') . '">';
            $html .= '</div>';
        }
        
        if (in_array('notes', $standardFields)) {
            $html .= '<div class="form-field">';
            $html .= '<label class="form-label">Notes</label>';
            $html .= '<textarea name="notes" class="form-input" rows="3">' . esc($values['notes'] ?? '') . '</textarea>';
            $html .= '</div>';
        }
        
        return $html;
    }
}

if (!function_exists('get_booking_field_config')) {
    /**
     * Get booking field configuration as an array
     * 
     * @param array $settings Settings array from SettingModel
     * @return array Configuration array for booking fields
     */
    function get_booking_field_config(array $settings = []): array
    {
        $config = [
            'name_fields' => [
                'first_names' => [
                    'display' => ($settings['booking.first_names_display'] ?? '1') === '1',
                    'required' => ($settings['booking.first_names_required'] ?? '1') === '1',
                ],
                'surname' => [
                    'display' => ($settings['booking.surname_display'] ?? '1') === '1',
                    'required' => ($settings['booking.surname_required'] ?? '1') === '1',
                ],
            ],
            'custom_fields' => [],
            'standard_fields' => $settings['booking.fields'] ?? ['email', 'phone'],
        ];
        
        // Process custom fields
        for ($i = 1; $i <= 6; $i++) {
            if (($settings["booking.custom_field_{$i}_enabled"] ?? '0') === '1') {
                $config['custom_fields'][$i] = [
                    'title' => $settings["booking.custom_field_{$i}_title"] ?? "Custom Field {$i}",
                    'type' => $settings["booking.custom_field_{$i}_type"] ?? 'text',
                    'required' => ($settings["booking.custom_field_{$i}_required"] ?? '0') === '1',
                ];
            }
        }
        
        return $config;
    }
}