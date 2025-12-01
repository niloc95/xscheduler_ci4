<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Services\CalendarPrototypeService;
use App\Services\CalendarPrototypeTelemetryService;

class CalendarPrototype extends BaseController
{
    private CalendarPrototypeService $prototypeService;
    private CalendarPrototypeTelemetryService $telemetry;
    private bool $enabled;
    private string $featureKey;

    public function __construct()
    {
        $this->prototypeService = new CalendarPrototypeService();
        $this->telemetry = new CalendarPrototypeTelemetryService();
        $config = config('Calendar');
        $this->enabled = (bool) ($config->prototypeEnabled ?? false);
        $this->featureKey = $config->prototypeFeatureKey ?? 'calendar_prototype';
        helper('permissions');
    }

    public function bootstrap()
    {
        if (!$this->enabled) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Prototype not enabled']);
        }

        $payload = $this->prototypeService->buildBootstrapPayload(
            $this->request->getGet('start'),
            $this->request->getGet('end'),
        );

        return $this->response->setJSON([
            'data' => $payload,
            'meta' => [
                'feature' => $this->featureKey,
            ],
        ]);
    }

    public function range()
    {
        if (!$this->enabled) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Prototype not enabled']);
        }

        $start = $this->request->getGet('start');
        $end = $this->request->getGet('end');

        if (!$start) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON(['error' => 'The "start" query parameter is required.']);
        }

        $payload = $this->prototypeService->buildBootstrapPayload($start, $end);

        return $this->response->setJSON([
            'data' => $payload,
            'meta' => [
                'feature' => $this->featureKey,
                'requested' => [
                    'start' => $start,
                    'end' => $end,
                ],
            ],
        ]);
    }

    public function telemetry()
    {
        if (!$this->enabled) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Prototype not enabled']);
        }

        $body = $this->request->getJSON(true) ?? [];
        $event = isset($body['event']) ? trim((string) $body['event']) : '';
        if ($event === '') {
            return $this->response
                ->setStatusCode(422)
                ->setJSON(['error' => 'Telemetry "event" is required.']);
        }

        $meta = isset($body['meta']) && is_array($body['meta']) ? $body['meta'] : [];
        $clientTimestamp = isset($body['timestamp']) ? (string) $body['timestamp'] : null;

        $session = session();
        $context = [
            'userId' => $session ? $session->get('user_id') : null,
            'role' => function_exists('current_user_role') ? current_user_role() : null,
            'ip' => $this->request->getIPAddress(),
            'feature' => $this->featureKey,
            'clientTimestamp' => $clientTimestamp,
            'userAgentHash' => $this->hashUserAgent(),
            'view' => $meta['activeView'] ?? null,
        ];

        $this->telemetry->record($event, $meta, $context);

        return $this->response->setJSON(['status' => 'ok']);
    }

    private function hashUserAgent(): ?string
    {
        $agent = $this->request->getUserAgent();
        $userAgent = $agent ? $agent->getAgentString() : '';
        if ($userAgent === '') {
            return null;
        }

        return substr(hash('sha256', $userAgent), 0, 16);
    }
}
