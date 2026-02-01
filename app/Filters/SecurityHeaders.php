<?php

/**
 * =============================================================================
 * SECURITY HEADERS FILTER
 * =============================================================================
 * 
 * @file        app/Filters/SecurityHeaders.php
 * @description HTTP middleware that adds security headers to all responses.
 *              Implements defense-in-depth security measures.
 * 
 * FILTER ALIAS: 'security_headers'
 * 
 * HEADERS ADDED:
 * -----------------------------------------------------------------------------
 * - X-Frame-Options: DENY
 *   Prevents clickjacking by disabling iframe embedding
 * 
 * - X-Content-Type-Options: nosniff
 *   Prevents MIME type sniffing attacks
 * 
 * - X-XSS-Protection: 1; mode=block
 *   Enables browser XSS filtering (legacy browsers)
 * 
 * - Referrer-Policy: strict-origin-when-cross-origin
 *   Controls referrer information sent with requests
 * 
 * - Strict-Transport-Security (HTTPS only)
 *   Enforces HTTPS connections (HSTS)
 * 
 * - Permissions-Policy
 *   Restricts browser features (camera, microphone, etc.)
 * 
 * BEHAVIOR:
 * -----------------------------------------------------------------------------
 * After Request (response ready):
 * 1. Set security headers on response object
 * 2. Continue with response delivery
 * 
 * CSP NOTE:
 * -----------------------------------------------------------------------------
 * Content-Security-Policy is configured separately in
 * app/Config/ContentSecurityPolicy.php
 * 
 * @see         app/Config/ContentSecurityPolicy.php for CSP
 * @see         app/Config/Filters.php for global filter setup
 * @package     App\Filters
 * @implements  FilterInterface
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class SecurityHeaders implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // This filter sets response headers, so we don't need to do anything here
        // Headers will be set in the after() method
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Add security headers
        
        // Prevent clickjacking
        $response->setHeader('X-Frame-Options', 'DENY');
        
        // Prevent MIME type sniffing
        $response->setHeader('X-Content-Type-Options', 'nosniff');
        
        // XSS Protection
        $response->setHeader('X-XSS-Protection', '1; mode=block');
        
        // Strict Transport Security (HTTPS only)
        if ($request->getUri()->getScheme() === 'https') {
            $response->setHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
        
        // Content Security Policy
        // Allow Google Fonts for Material Symbols (styles + font files)
        $csp = "default-src 'self'; " .
         "script-src 'self' 'unsafe-inline' 'unsafe-eval'; " .
         "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; " .
         "img-src 'self' data: https:; " .
         "font-src 'self' https://fonts.gstatic.com data:; " .
         "connect-src 'self'; " .
         "frame-ancestors 'none';";
        
        $response->setHeader('Content-Security-Policy', $csp);
        
        // Referrer Policy
        $response->setHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        
        // Permissions Policy
        $response->setHeader('Permissions-Policy', 
            'geolocation=(), microphone=(), camera=(), payment=()');
        
        // Remove server information
        $response->removeHeader('Server');
        $response->removeHeader('X-Powered-By');
        
        return $response;
    }
}
