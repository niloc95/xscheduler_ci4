<?php

if (!function_exists('ui_button')) {
    function ui_button($text, $href = null, $type = 'primary', $attributes = []) {
        $baseClasses = $type === 'primary' ? 'btn-primary' : 'btn-secondary';
        $attrs = '';
        
        foreach ($attributes as $key => $value) {
            $attrs .= " {$key}=\"{$value}\"";
        }
        
        if ($href) {
            return "<a href=\"{$href}\" class=\"{$baseClasses}\"{$attrs}>{$text}</a>";
        } else {
            return "<button class=\"{$baseClasses}\" type=\"button\"{$attrs}>{$text}</button>";
        }
    }
}

if (!function_exists('ui_card')) {
    function ui_card($title = null, $content = '', $footer = null) {
        $html = '<div class="card">';
        
        if ($title) {
            $html .= '<div class="card-header">';
            $html .= '<h4 class="card-title">' . $title . '</h4>';
            $html .= '</div>';
        }
        
        $html .= '<div class="card-body">' . $content . '</div>';
        
        if ($footer) {
            $html .= '<div class="card-footer">' . $footer . '</div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
}

if (!function_exists('ui_alert')) {
    function ui_alert($message, $type = 'info', $title = null) {
        $typeClass = "alert-{$type}";
        $iconColors = [
            'info' => 'text-blue-400',
            'success' => 'text-green-400',
            'warning' => 'text-yellow-400',
            'error' => 'text-red-400'
        ];
        
        $textColors = [
            'info' => 'text-blue-800',
            'success' => 'text-green-800',
            'warning' => 'text-yellow-800',
            'error' => 'text-red-800'
        ];
        
        $messageColors = [
            'info' => 'text-blue-700',
            'success' => 'text-green-700',
            'warning' => 'text-yellow-700',
            'error' => 'text-red-700'
        ];
        
        $html = "<div class=\"alert {$typeClass}\" role=\"alert\">";
        $html .= '<div class="flex">';
        $html .= '<div class="flex-shrink-0">';
        $html .= '<svg class="h-5 w-5 ' . $iconColors[$type] . '" viewBox="0 0 20 20" fill="currentColor">';
        $html .= '<path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />';
        $html .= '</svg>';
        $html .= '</div>';
        $html .= '<div class="ml-3">';
        
        if ($title) {
            $html .= '<h5 class="text-sm font-medium ' . $textColors[$type] . ' mb-1">' . $title . '</h5>';
        }
        
        $html .= '<p class="text-sm ' . $messageColors[$type] . ' mb-0">' . $message . '</p>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
}