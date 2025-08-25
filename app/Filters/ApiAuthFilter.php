<?php

namespace App\Filters;

use App\Config\Api as ApiConfig;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class ApiAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $config = config(ApiConfig::class);
        $auth = $request->getHeaderLine('Authorization');

        // Bearer token
        if (preg_match('/^Bearer\s+(.*)$/i', $auth ?? '', $m)) {
            $token = trim($m[1]);
            $allowed = $config->bearerTokens;
            if ($env = $config->envBearerToken()) {
                $allowed[] = $env;
            }
            if ($token !== '' && in_array($token, $allowed, true)) {
                return; // Authorized
            }
        }

        // Basic auth (dev/demo)
        if (preg_match('/^Basic\s+(.*)$/i', $auth ?? '', $m)) {
            $decoded = base64_decode($m[1], true) ?: '';
            [$u, $p] = array_pad(explode(':', $decoded, 2), 2, '');
            if ($u !== '' && isset($config->basicUsers[$u]) && hash_equals((string)$config->basicUsers[$u], $p)) {
                return; // Authorized
            }
        }

        // Unauthorized
        return service('response')
            ->setStatusCode(401)
            ->setJSON(['error' => ['message' => 'Unauthorized', 'code' => 'unauthorized']]);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // No-op
    }
}
