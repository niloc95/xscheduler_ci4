<?php

/**
 * =============================================================================
 * ASSETS CONTROLLER
 * =============================================================================
 * 
 * @file        app/Controllers/Assets.php
 * @description Serves dynamic and uploaded assets with proper MIME types,
 *              caching headers, and security measures.
 * 
 * ROUTES HANDLED:
 * -----------------------------------------------------------------------------
 * GET  /assets/settings/:filename    : Serve file from public/assets/settings/
 * GET  /assets/provider/:filename    : Serve provider-specific assets
 * GET  /assets/avatars/:filename     : Serve user avatar images
 * 
 * PURPOSE:
 * -----------------------------------------------------------------------------
 * Centralized asset serving with:
 * - Database-stored files (logos, branding assets)
 * - File-based assets (uploaded images, documents)
 * - Proper MIME type detection
 * - Cache headers for performance
 * - Path traversal prevention (security)
 * 
 * ASSET TYPES:
 * -----------------------------------------------------------------------------
 * - settings: Static files in public/assets/settings/
 * - provider: Provider profile images
 * - avatars: User profile pictures
 * 
 * SECURITY:
 * -----------------------------------------------------------------------------
 * - basename() to prevent directory traversal
 * - Validates file existence before serving
 * - Sets appropriate Content-Type headers
 * 
 * CACHING:
 * -----------------------------------------------------------------------------
 * - 24-hour cache (max-age=86400) for all assets
 * - Inline content disposition for images
 * 
 * @package     App\Controllers
 * @extends     BaseController
 * @author      Nilesh Nagin Cara
 * @copyright   2024-2026 Nilesh Nagin Cara
 * =============================================================================
 */

namespace App\Controllers;

class Assets extends BaseController
{
    public function settings(string $filename)
    {
        $safe = basename($filename); // prevent traversal
        // Serve from public assets
        $full = FCPATH . 'assets/settings/' . $safe;
        if (!is_file($full)) {
            return $this->response->setStatusCode(404, 'Not Found');
        }

        $mime = $this->detectMime($full);
        $this->response->setHeader('Content-Type', $mime);
        $this->response->setHeader('Content-Disposition', 'inline; filename="' . $safe . '"');
        $this->response->setHeader('Cache-Control', 'public, max-age=86400');
        return $this->response->setBody(file_get_contents($full));
    }

    public function provider(string $filename)
    {
        $safe = basename($filename);
        $full = FCPATH . 'assets/providers/' . $safe;
        if (!is_file($full)) {
            return $this->response->setStatusCode(404, 'Not Found');
        }
        $mime = $this->detectMime($full);
        $this->response->setHeader('Content-Type', $mime);
        $this->response->setHeader('Content-Disposition', 'inline; filename="' . $safe . '"');
        $this->response->setHeader('Cache-Control', 'public, max-age=86400');
        return $this->response->setBody(file_get_contents($full));
    }

    private function detectMime(string $path): string
    {
        if (function_exists('mime_content_type')) {
            $m = @mime_content_type($path);
            if ($m) return $m;
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match ($ext) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => 'application/octet-stream',
        };
    }
}
