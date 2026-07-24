<?php

/**
 * =============================================================================
 * CORS FILTER
 * =============================================================================
 * 
 * @file        app/Filters/CorsFilter.php
 * @description HTTP middleware for Cross-Origin Resource Sharing (CORS).
 *              Enables API access from external domains.
 * 
 * FILTER ALIAS: 'api_cors'
 * 
 * PURPOSE:
 * -----------------------------------------------------------------------------
 * Handles CORS preflight and response headers:
 * - Access-Control-Allow-Origin
 * - Access-Control-Allow-Methods
 * - Access-Control-Allow-Headers
 * - Access-Control-Allow-Credentials
 * 
 * BEHAVIOR:
 * -----------------------------------------------------------------------------
 * Before Request:
 * 1. Read allowed origins from Api config
 * 2. Set CORS headers on response
 * 3. Handle OPTIONS preflight with 200 response
 * 4. Continue to controller for actual requests
 * 
 * CONFIGURATION:
 * -----------------------------------------------------------------------------
 * Configured in app/Config/Api.php. `allowedOrigins` is an explicit allow-list
 * (empty = same-origin only), extended by the `api.allowedOrigins` env var.
 * Only a listed origin is echoed back; wildcards are not supported because
 * /api/* is CSRF-exempt.
 *     public $cors = [
 *         'allowedOrigins' => [],
 *         'allowedMethods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
 *         'allowedHeaders' => ['Content-Type', 'Authorization', 'Accept', 'X-Requested-With'],
 *         'maxAge' => 600,
 *         'allowCredentials' => false,
 *     ];
 * 
 * PREFLIGHT:
 * -----------------------------------------------------------------------------
 * OPTIONS requests are short-circuited with a 200 response
 * to satisfy browser preflight requirements.
 * 
 * @see         app/Config/Api.php for CORS configuration
 * @package     App\Filters
 * @implements  FilterInterface
 * @author      Nilesh Nagin Cara
 * @copyright   2024-2026 Nilesh Nagin Cara
 * =============================================================================
 */

namespace App\Filters;

use App\Config\Api as ApiConfig;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class CorsFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $config = config(ApiConfig::class);
        $response = service('response');

        $origin = trim((string) $request->getHeaderLine('Origin'));
        $allowedOrigins = $config->allowedOrigins();

        // Vary regardless of the outcome so caches never reuse an allow header
        // across origins.
        $response->setHeader('Vary', 'Origin');

        // Echo back only an explicitly allow-listed origin. An unknown origin
        // gets no Access-Control-Allow-Origin header at all, which the browser
        // treats as a rejection. There is deliberately no wildcard branch:
        // /api/* is CSRF-exempt, so a permissive origin plus cookie auth would
        // be a live CSRF hole.
        $originAllowed = $origin !== '' && in_array($origin, $allowedOrigins, true);

        if ($originAllowed) {
            $response->setHeader('Access-Control-Allow-Origin', $origin);
            $response->setHeader('Access-Control-Allow-Methods', implode(',', $config->cors['allowedMethods'] ?? []));
            $response->setHeader('Access-Control-Allow-Headers', implode(',', $config->cors['allowedHeaders'] ?? []));
            $response->setHeader('Access-Control-Max-Age', (string)($config->cors['maxAge'] ?? 600));

            if (!empty($config->cors['exposedHeaders'])) {
                $response->setHeader('Access-Control-Expose-Headers', implode(',', $config->cors['exposedHeaders']));
            }

            if (!empty($config->cors['allowCredentials'])) {
                $response->setHeader('Access-Control-Allow-Credentials', 'true');
            }
        }

        // Short-circuit preflight. A preflight from a disallowed origin still
        // returns 204, just without the allow headers that would permit it.
        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            return $response->setStatusCode(204);
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // No-op; headers already set in before()
    }
}
