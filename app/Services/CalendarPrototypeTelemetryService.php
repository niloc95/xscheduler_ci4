<?php

namespace App\Services;

use DateTimeImmutable;
use Psr\Log\LoggerInterface;

class CalendarPrototypeTelemetryService
{
    private const CHANNEL = 'calendar_prototype';

    /** @var string[] */
    private const ALLOWED_META_KEYS = [
        'rangeStart',
        'rangeEnd',
        'loadedRangeStart',
        'loadedRangeEnd',
        'activeView',
        'source',
        'durationMs',
        'status',
        'requestStart',
        'requestEnd',
        'resultCount',
    ];

    private LoggerInterface $logger;

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? service('logger');
    }

    public function record(string $event, array $meta = [], array $context = []): void
    {
        $payload = [
            'channel' => self::CHANNEL,
            'event' => $event,
            'timestamp' => (new DateTimeImmutable('now'))->format(DATE_ATOM),
            'meta' => $this->filterMeta($meta),
            'context' => $this->filterContext($context),
        ];

        $this->logger->info(
            sprintf('[%s] %s', self::CHANNEL, $event),
            $payload,
        );
    }

    private function filterMeta(array $meta): array
    {
        $allowed = array_flip(self::ALLOWED_META_KEYS);
        $filtered = array_intersect_key($meta, $allowed);

        foreach ($filtered as $key => $value) {
            if (is_scalar($value) || $value === null) {
                continue;
            }
            $filtered[$key] = json_encode($value);
        }

        return $filtered;
    }

    private function filterContext(array $context): array
    {
        $whitelist = [
            'userId',
            'role',
            'ip',
            'feature',
            'clientTimestamp',
            'userAgentHash',
            'view',
        ];

        $allowed = array_flip($whitelist);
        $filtered = array_intersect_key($context, $allowed);

        return array_filter($filtered, static fn ($value) => $value !== null && $value !== '');
    }
}
