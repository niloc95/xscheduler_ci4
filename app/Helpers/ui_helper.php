<?php

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