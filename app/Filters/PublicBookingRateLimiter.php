<?php

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
