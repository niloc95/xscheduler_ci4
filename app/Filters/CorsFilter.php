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
 * Configured in app/Config/Api.php:
 *     public $cors = [
 *         'allowedOrigins' => ['*'],
 *         'allowedMethods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
 *         'allowedHeaders' => ['Content-Type', 'Authorization'],
 *         'maxAge' => 600,
 *         'allowCredentials' => true,
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
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
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

        $origin = $request->getHeaderLine('Origin');
        $allowedOrigins = $config->cors['allowedOrigins'] ?? ['*'];
        $allowOrigin = in_array('*', $allowedOrigins, true) ? '*' : ($origin ?: '');

        $response->setHeader('Access-Control-Allow-Origin', $allowOrigin);
        $response->setHeader('Vary', 'Origin');
        $response->setHeader('Access-Control-Allow-Methods', implode(',', $config->cors['allowedMethods'] ?? []));
        $response->setHeader('Access-Control-Allow-Headers', implode(',', $config->cors['allowedHeaders'] ?? []));
        $response->setHeader('Access-Control-Max-Age', (string)($config->cors['maxAge'] ?? 600));
        if (!empty($config->cors['allowCredentials'])) {
            $response->setHeader('Access-Control-Allow-Credentials', 'true');
        }

        // Short-circuit preflight
        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            return $response->setStatusCode(204);
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // No-op; headers already set in before()
    }
}
