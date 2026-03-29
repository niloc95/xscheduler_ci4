<?php

if (!function_exists('request_id')) {
    /**
     * Return current request id, generating one when absent.
     */
    function request_id(): string
    {
        $requestId = trim((string) ($_SERVER['HTTP_X_REQUEST_ID'] ?? ''));

        if ($requestId === '') {
            $requestId = bin2hex(random_bytes(16));
            $_SERVER['HTTP_X_REQUEST_ID'] = $requestId;
        }

        return $requestId;
    }
}

if (!function_exists('log_structured')) {
    /**
     * Write a structured log payload with standard context fields.
     */
    function log_structured(string $level, string $message, array $context = []): void
    {
        static $seen = [];

        $context['request_id'] = $context['request_id'] ?? request_id();
        $context['correlation_id'] = $context['correlation_id'] ?? $context['request_id'];
        $context['occurred_at'] = $context['occurred_at'] ?? date('c');

        $dedupeKey = sha1($level . '|' . $message . '|' . json_encode($context));
        if (isset($seen[$dedupeKey])) {
            return;
        }
        $seen[$dedupeKey] = true;

        $json = json_encode($context, JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $json = '{"encode_error":"context_json_failed"}';
        }

        log_message($level, '{message} | context={context}', [
            'message' => $message,
            'context' => $json,
        ]);
    }
}

if (!function_exists('log_api_call')) {
    /**
     * Emit a normalized API call log entry.
     */
    function log_api_call(string $level, string $event, int $statusCode, array $context = []): void
    {
        $method = null;
        $path = null;
        $durationMs = null;

        try {
            $request = service('request');
            $method = method_exists($request, 'getMethod') ? strtoupper((string) $request->getMethod()) : null;
            $path = method_exists($request, 'getPath') ? (string) $request->getPath() : null;
        } catch (\Throwable $e) {
            // Request service not available in this runtime context.
        }

        if (isset($_SERVER['REQUEST_TIME_FLOAT']) && is_numeric($_SERVER['REQUEST_TIME_FLOAT'])) {
            $durationMs = (int) round((microtime(true) - (float) $_SERVER['REQUEST_TIME_FLOAT']) * 1000);
        }

        log_structured($level, $event, array_merge([
            'status_code' => $statusCode,
            'http_method' => $method,
            'path' => $path,
            'duration_ms' => $durationMs,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ], $context));
    }
}
