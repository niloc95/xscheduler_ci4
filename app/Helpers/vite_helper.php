<?php

if (!function_exists('vite_asset')) {
    function vite_asset(string $entry): array
    {
        $manifestPath = FCPATH . 'build/.vite/manifest.json';
        
        if (!file_exists($manifestPath)) {
            throw new Exception('Vite manifest file not found. Run "npm run build" first.');
        }
        
        $manifest = json_decode(file_get_contents($manifestPath), true);
        
        if (!isset($manifest[$entry])) {
            throw new Exception("Entry '{$entry}' not found in Vite manifest.");
        }
        
        $entryData = $manifest[$entry];
        $assets = [];
        
        // Add the main file
        $assets['file'] = base_url('build/' . $entryData['file']);
        
        // For CSS entries, the file IS the CSS file
        if (str_ends_with($entryData['file'], '.css')) {
            $assets['css'] = [base_url('build/' . $entryData['file'])];
        }
        // For JS entries, check if there are associated CSS files
        elseif (isset($entryData['css'])) {
            $assets['css'] = [];
            foreach ($entryData['css'] as $css) {
                $assets['css'][] = base_url('build/' . $css);
            }
        }
        
        return $assets;
    }
}

if (!function_exists('vite_js')) {
    function vite_js(string $entry): string
    {
        $assets = vite_asset($entry);
        return $assets['file'];
    }
}

if (!function_exists('vite_css')) {
    function vite_css(string $entry): array
    {
        $assets = vite_asset($entry);
        return $assets['css'] ?? [];
    }
}