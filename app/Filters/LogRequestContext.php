<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class LogRequestContext implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $requestId = trim((string) $request->getHeaderLine('X-Request-ID'));

        if ($requestId === '') {
            $requestId = bin2hex(random_bytes(16));
        }

        $_SERVER['HTTP_X_REQUEST_ID'] = $requestId;

        if (!isset($_SERVER['REQUEST_TIME_FLOAT']) || !is_numeric($_SERVER['REQUEST_TIME_FLOAT'])) {
            $_SERVER['REQUEST_TIME_FLOAT'] = microtime(true);
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $requestId = trim((string) ($_SERVER['HTTP_X_REQUEST_ID'] ?? ''));

        if ($requestId !== '') {
            $response->setHeader('X-Request-ID', $requestId);
        }

        return $response;
    }
}
