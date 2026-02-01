<?php

/**
 * =============================================================================
 * UI HELPER
 * =============================================================================
 * 
 * @file        app/Helpers/ui_helper.php
 * @description Helper functions for rendering standardized UI components.
 *              Ensures consistent styling across the application.
 * 
 * LOADING:
 * -----------------------------------------------------------------------------
 * Loaded automatically via BaseController or manually:
 *     helper('ui');
 * 
 * AVAILABLE FUNCTIONS:
 * -----------------------------------------------------------------------------
 * ui_button($text, $href, $type, $attributes)
 *   Render a button or link styled as button
 *   Types: primary, secondary, ghost, pill
 *   Example: ui_button('Save', null, 'primary', ['id' => 'save-btn'])
 * 
 * ui_card($title, $content, $footer, $options)
 *   Render a card component with optional header/footer
 *   Example: ui_card('Settings', '<p>Content</p>', null, ['class' => 'mt-4'])
 * 
 * ui_badge($text, $variant)
 *   Render a badge/tag component
 *   Variants: primary, success, warning, danger, info
 * 
 * ui_alert($message, $type, $dismissible)
 *   Render an alert message box
 *   Types: success, error, warning, info
 * 
 * ui_modal($id, $title, $content, $footer, $options)
 *   Render a modal dialog structure
 * 
 * ui_table($headers, $rows, $options)
 *   Render a data table with consistent styling
 * 
 * BUTTON TYPES:
 * -----------------------------------------------------------------------------
 * - primary   : Blue accent color, main actions
 * - secondary : Gray, secondary actions
 * - ghost     : Transparent, subtle actions
 * - pill      : Rounded pill shape
 * 
 * TAILWIND INTEGRATION:
 * -----------------------------------------------------------------------------
 * All components use Tailwind CSS classes with dark mode support.
 * Custom class overrides can be passed via options/attributes.
 * 
 * @see         resources/css/components.css for base styles
 * @package     App\Helpers
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

if (!function_exists('ui_button')) {
    function ui_button($text, $href = null, $type = 'primary', $attributes = []) {
        $type = strtolower($type ?? 'primary');

        $variants = [
            'primary'   => 'btn-primary',
            'secondary' => 'btn-secondary',
            'ghost'     => 'btn-ghost',
            'pill'      => 'btn-pill',
        ];

        if (! array_key_exists($type, $variants)) {
            $type = 'primary';
        }

        $extraClasses = '';
        if (isset($attributes['class'])) {
            $extraClasses = $attributes['class'];
            unset($attributes['class']);
        }

        $classAttribute = trim('btn ' . $variants[$type] . ' ' . $extraClasses);

        $attrs = '';
        foreach ($attributes as $key => $value) {
            $attrs .= ' ' . $key . '="' . esc($value) . '"';
        }

        $content = esc($text);

        if ($href !== null) {
            return '<a href="' . esc($href) . '" class="' . $classAttribute . '"' . $attrs . '>' . $content . '</a>';
        }

        return '<button class="' . $classAttribute . '" type="button"' . $attrs . '>' . $content . '</button>';
    }
}

if (!function_exists('ui_card')) {
    /**
     * Render a standardized card component.
     *
     * @param string|null $title     Optional title rendered in the header.
     * @param string       $content   Card body HTML content.
     * @param string|null  $footer    Optional footer HTML content.
     * @param array        $options   Configure wrapper/body/header/footer classes and subtitles.
     */
    function ui_card($title = null, $content = '', $footer = null, array $options = []) {
        $defaults = [
            'class' => '',
            'headerClass' => '',
            'bodyClass' => '',
            'footerClass' => '',
            'titleTag' => 'h3',
            'titleClass' => '',
            'subtitle' => null,
            'subtitleClass' => '',
        ];

        $config = array_merge($defaults, $options);

        $titleTag = in_array(strtolower($config['titleTag']), ['h2', 'h3', 'h4', 'h5', 'h6'], true) ? strtolower($config['titleTag']) : 'h3';

        $cardClasses = trim('card ' . $config['class']);
        $headerClasses = trim('card-header ' . $config['headerClass']);
        $titleClasses = trim('card-title ' . $config['titleClass']);
        $subtitleClasses = trim('card-subtitle ' . $config['subtitleClass']);
        $bodyClasses = trim('card-body ' . $config['bodyClass']);
        $footerClasses = trim('card-footer ' . $config['footerClass']);

        $html = '<div class="' . $cardClasses . '">';

        if ($title !== null || $config['subtitle'] !== null) {
            $html .= '<div class="' . $headerClasses . '">';

            if ($title !== null) {
                $html .= '<' . $titleTag . ' class="' . $titleClasses . '">' . $title . '</' . $titleTag . '>';
            }

            if ($config['subtitle'] !== null) {
                $html .= '<div class="' . $subtitleClasses . '">' . $config['subtitle'] . '</div>';
            }

            $html .= '</div>';
        }

        $html .= '<div class="' . $bodyClasses . '">' . $content . '</div>';

        if ($footer !== null) {
            $html .= '<div class="' . $footerClasses . '">' . $footer . '</div>';
        }

        $html .= '</div>';

        return $html;
    }
}

if (!function_exists('ui_alert')) {
    /**
     * Render Material Design 3.0 compliant alert component
     *
     * @param string $message Alert message text
     * @param string $type Alert type: info, success, warning, error
     * @param string|null $title Optional alert title
     * @param bool $dismissible Show close button
     * @return string HTML for the alert
     */
    function ui_alert($message, $type = 'info', $title = null, $dismissible = false) {
        // Material Design 3.0 color palette
        $variants = [
            'info' => [
                'bg' => 'bg-blue-50',
                'border' => 'border-blue-200',
                'icon' => 'text-blue-600',
                'title' => 'text-blue-900',
                'message' => 'text-blue-800',
                'icon_path' => '<path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />'
            ],
            'success' => [
                'bg' => 'bg-green-50',
                'border' => 'border-green-200',
                'icon' => 'text-green-600',
                'title' => 'text-green-900',
                'message' => 'text-green-800',
                'icon_path' => '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />'
            ],
            'warning' => [
                'bg' => 'bg-yellow-50',
                'border' => 'border-yellow-200',
                'icon' => 'text-yellow-600',
                'title' => 'text-yellow-900',
                'message' => 'text-yellow-800',
                'icon_path' => '<path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />'
            ],
            'error' => [
                'bg' => 'bg-red-50',
                'border' => 'border-red-200',
                'icon' => 'text-red-600',
                'title' => 'text-red-900',
                'message' => 'text-red-800',
                'icon_path' => '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />'
            ]
        ];
        
        $variant = $variants[$type] ?? $variants['info'];
        
        $html = '<div class="rounded-lg border ' . $variant['bg'] . ' ' . $variant['border'] . ' p-4 shadow-sm" role="alert">';
        $html .= '<div class="flex items-start">';
        
        // Icon
        $html .= '<div class="flex-shrink-0">';
        $html .= '<svg class="h-5 w-5 ' . $variant['icon'] . '" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">';
        $html .= $variant['icon_path'];
        $html .= '</svg>';
        $html .= '</div>';
        
        // Content
        $html .= '<div class="ml-3 flex-1">';
        
        if ($title) {
            $html .= '<h3 class="text-sm font-medium ' . $variant['title'] . ' mb-1">' . esc($title) . '</h3>';
        }
        
        $html .= '<div class="text-sm ' . $variant['message'] . '">' . esc($message) . '</div>';
        $html .= '</div>';
        
        // Dismiss button
        if ($dismissible) {
            $html .= '<div class="ml-auto pl-3">';
            $html .= '<div class="-mx-1.5 -my-1.5">';
            $html .= '<button type="button" class="inline-flex rounded-md p-1.5 ' . $variant['icon'] . ' hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-' . str_replace('text-', '', $variant['icon']) . ' focus:ring-' . str_replace('text-', '', $variant['icon']) . '" data-dismiss="alert" aria-label="Dismiss">';
            $html .= '<span class="sr-only">Dismiss</span>';
            $html .= '<svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">';
            $html .= '<path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />';
            $html .= '</svg>';
            $html .= '</button>';
            $html .= '</div>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
}

if (!function_exists('ui_dashboard_stat_card')) {
    /**
     * Render a compact dashboard stat card used across admin widgets.
     */
    function ui_dashboard_stat_card(string $label, $value, array $options = []): string
    {
        $defaults = [
            'class' => 'min-w-[12rem] rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800',
            'labelClass' => 'text-sm font-medium text-gray-500 dark:text-gray-400',
            'valueClass' => 'mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100',
            'valueId' => null,
            'valueAttributes' => [],
        ];

        $config = array_merge($defaults, $options);

        $valueText = is_numeric($value) ? number_format((float) $value) : esc((string) $value);

        $valueAttributes = '';
        if (!empty($config['valueId'])) {
            $valueAttributes .= ' id="' . esc($config['valueId']) . '"';
        }

        if (!empty($config['valueAttributes']) && is_array($config['valueAttributes'])) {
            foreach ($config['valueAttributes'] as $attr => $attrValue) {
                $valueAttributes .= ' ' . esc($attr) . '="' . esc($attrValue) . '"';
            }
        }

        return '<div class="' . esc($config['class']) . '">' .
            '<p class="' . esc($config['labelClass']) . '">' . esc($label) . '</p>' .
            '<p class="' . esc($config['valueClass']) . '"' . $valueAttributes . '>' . $valueText . '</p>' .
        '</div>';
    }
}