<?php

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
