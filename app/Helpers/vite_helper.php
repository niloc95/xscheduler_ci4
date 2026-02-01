<?php

/**
 * =============================================================================
 * VITE HELPER
 * =============================================================================
 * 
 * @file        app/Helpers/vite_helper.php
 * @description Helper functions for integrating Vite-built assets into
 *              CodeIgniter views.
 * 
 * LOADING:
 * -----------------------------------------------------------------------------
 * Load in views or layouts that need Vite assets:
 *     helper('vite');
 * 
 * AVAILABLE FUNCTIONS:
 * -----------------------------------------------------------------------------
 * vite_asset($entry)
 *   Get full asset info (file URL + CSS URLs) for an entry point
 *   Returns: ['file' => '...', 'css' => ['...']]
 * 
 * vite_js($entry)
 *   Get the JavaScript file URL for an entry point
 *   Example: vite_js('resources/js/app.js') => '/build/assets/app-abc123.js'
 * 
 * vite_css($entry)
 *   Get array of CSS file URLs for an entry point
 *   Example: vite_css('resources/js/app.js') => ['/build/assets/app-def456.css']
 * 
 * BUILD REQUIREMENTS:
 * -----------------------------------------------------------------------------
 * Run 'npm run build' before using these functions.
 * Functions read from: public/build/.vite/manifest.json
 * 
 * MANIFEST STRUCTURE:
 * -----------------------------------------------------------------------------
 * The Vite manifest maps entry points to built file names:
 *     {
 *       "resources/js/app.js": {
 *         "file": "assets/app-abc123.js",
 *         "css": ["assets/app-def456.css"]
 *       }
 *     }
 * 
 * USAGE IN VIEWS:
 * -----------------------------------------------------------------------------
 *     <?php helper('vite'); ?>
 *     
 *     <!-- JavaScript -->
 *     <script type="module" src="<?= vite_js('resources/js/app.js') ?>"></script>
 *     
 *     <!-- CSS -->
 *     <?php foreach (vite_css('resources/js/app.js') as $css): ?>
 *         <link rel="stylesheet" href="<?= $css ?>">
 *     <?php endforeach; ?>
 * 
 * @see         vite.config.js for build configuration
 * @see         app/Views/layouts/*.php for usage
 * @package     App\Helpers
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

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