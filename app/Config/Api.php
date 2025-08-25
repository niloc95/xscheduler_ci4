<?php

namespace App\Config;

use CodeIgniter\Config\BaseConfig;

class Api extends BaseConfig
{
    /**
     * Bearer tokens allowed to access the API.
     * Example: ['my-dev-token','another-token']
     */
    public array $bearerTokens = [
        // Dev token for local testing (override via env api.bearerToken)
        'dev-local-token-123'
    ];

    /**
     * Basic auth users [username => password]. For demos only.
     * Prefer Bearer in production.
     */
    public array $basicUsers = [
        // username => password (dev only)
        'dev' => 'dev'
    ];

    /**
     * CORS configuration for API endpoints.
     */
    public array $cors = [
        'allowedOrigins' => ['*'],
        'allowedMethods' => ['GET','POST','PUT','PATCH','DELETE','OPTIONS'],
        'allowedHeaders' => ['Content-Type','Authorization','Accept','X-Requested-With'],
        'exposedHeaders' => [],
        'maxAge' => 600,
        'allowCredentials' => false,
    ];

    public function envBearerToken(): ?string
    {
        $val = env('api.bearerToken');
        return $val ? (string)$val : null;
    }
}
