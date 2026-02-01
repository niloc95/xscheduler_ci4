<?php

/**
 * =============================================================================
 * PUBLIC BOOKING RATE LIMITER FILTER
 * =============================================================================
 * 
 * @file        app/Filters/PublicBookingRateLimiter.php
 * @description HTTP middleware for rate limiting public booking endpoints.
 *              Prevents abuse and brute force attempts on public APIs.
 * 
 * FILTER ALIAS: 'rate_limit'
 * 
 * PURPOSE:
 * -----------------------------------------------------------------------------
 * Protects public endpoints from abuse:
 * - Booking form submissions
 * - Availability checks
 * - Customer lookup APIs
 * 
 * RATE LIMIT LOGIC:
 * -----------------------------------------------------------------------------
 * - Tracks requests per IP address
 * - Uses cache for distributed tracking
 * - Configurable limits per route/bucket
 * - Returns 429 Too Many Requests when exceeded
 * 
 * USAGE IN ROUTES:
 * -----------------------------------------------------------------------------
 *     // 20 requests per 60 seconds (default)
 *     ['filter' => 'rate_limit']
 * 
 *     // Custom: 10 requests per 120 seconds
 *     ['filter' => 'rate_limit:booking,10,120']
 * 
 * ARGUMENTS:
 * -----------------------------------------------------------------------------
 * 1. bucket : Identifier for rate limit bucket (default: 'default')
 * 2. limit  : Max requests allowed (default: 20)
 * 3. decay  : Time window in seconds (default: 60)
 * 
 * RESPONSE ON LIMIT:
 * -----------------------------------------------------------------------------
 * HTTP 429 Too Many Requests
 * {
 *   "error": "Rate limit exceeded",
 *   "retry_after": 45
 * }
 * 
 * @see         app/Config/Filters.php for setup
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
use Config\Services;

class PublicBookingRateLimiter implements FilterInterface
{
    private int $defaultMaxAttempts = 20;
    private int $defaultDecaySeconds = 60;

    public function before(RequestInterface $request, $arguments = null)
    {
        $bucket = $arguments[0] ?? 'default';
        $limit = isset($arguments[1]) ? max(1, (int) $arguments[1]) : $this->defaultMaxAttempts;
        $decay = isset($arguments[2]) ? max(1, (int) $arguments[2]) : $this->defaultDecaySeconds;
        $ip = $request->getIPAddress() ?? 'unknown';
        $key = sprintf('public_booking-%s-%s', preg_replace('/[^a-z0-9_-]/i', '-', $bucket), sha1($ip));

        $cache = cache();
        $entry = $cache->get($key);
        $now = time();

        if (!is_array($entry) || ($entry['expires_at'] ?? 0) <= $now) {
            $entry = [
                'count' => 0,
                'expires_at' => $now + $decay,
            ];
        }

        if ($entry['count'] >= $limit) {
            $retryAfter = max(1, $entry['expires_at'] - $now);
            return Services::response()
                ->setStatusCode(429)
                ->setJSON([
                    'error' => 'Too many requests. Please wait before trying again.',
                    'retry_after' => $retryAfter,
                ]);
        }

        $entry['count']++;
        $cache->save($key, $entry, $decay);
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // No-op
    }
}
