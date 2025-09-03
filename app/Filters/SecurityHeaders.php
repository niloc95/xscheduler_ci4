<?php
/**
 * Enhanced Security Middleware for XScheduler CI4
 * 
 * PROPRIETARY SOFTWARE - ALL RIGHTS RESERVED
 * Copyright (c) 2025 niloc95. All rights reserved.
 */

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class SecurityHeaders implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // Add security headers
        $response = service('response');
        
        // Prevent clickjacking
        $response->setHeader('X-Frame-Options', 'DENY');
        
        // Prevent MIME type sniffing
        $response->setHeader('X-Content-Type-Options', 'nosniff');
        
        // XSS Protection
        $response->setHeader('X-XSS-Protection', '1; mode=block');
        
        // Strict Transport Security (HTTPS only)
        if (is_https()) {
            $response->setHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
        
        // Content Security Policy
        $csp = "default-src 'self'; " .
               "script-src 'self' 'unsafe-inline' 'unsafe-eval'; " .
               "style-src 'self' 'unsafe-inline'; " .
               "img-src 'self' data: https:; " .
               "font-src 'self'; " .
               "connect-src 'self'; " .
               "frame-ancestors 'none';";
        
        $response->setHeader('Content-Security-Policy', $csp);
        
        // Referrer Policy
        $response->setHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        
        // Permissions Policy
        $response->setHeader('Permissions-Policy', 
            'geolocation=(), microphone=(), camera=(), payment=()');
        
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Remove server information
        $response->removeHeader('Server');
        $response->removeHeader('X-Powered-By');
        
        return $response;
    }
}
