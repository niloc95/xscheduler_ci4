<?php

namespace App\Filters;

use App\Services\LocalizationSettingsService;
use App\Services\TimezoneService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Capture client timezone headers and persist them in the session so
 * backend controllers can perform consistent UTC/local conversions.
 */
class TimezoneDetection implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = session();

        $headerTimezone = trim((string) $request->getHeaderLine('X-Client-Timezone'));
        $headerOffset = trim((string) $request->getHeaderLine('X-Client-Offset'));

        $postTimezone = null;
        $postOffset = null;

        if ($request instanceof IncomingRequest) {
            $postTimezone = $request->getPost('client_timezone');
            $postOffset = $request->getPost('client_offset');
        }

        $postTimezone = $postTimezone !== null ? trim((string) $postTimezone) : '';
        $postOffset = $postOffset !== null ? trim((string) $postOffset) : '';

        $timezone = $headerTimezone ?: $postTimezone;
        $offset = $headerOffset !== '' ? $headerOffset : $postOffset;

        if ($timezone && TimezoneService::isValidTimezone($timezone)) {
            $session->set('client_timezone', $timezone);
        }

        if ($offset !== null && $offset !== '') {
            $session->set('client_timezone_offset', (int) $offset);
        }

        if (!$session->has('client_timezone')) {
            // Skip database query if setup hasn't been completed yet
            $setupCompleted = file_exists(WRITEPATH . 'setup_completed.flag') || 
                              file_exists(WRITEPATH . 'setup_complete.flag');
            
            if ($setupCompleted) {
                try {
                    $localizationService = new LocalizationSettingsService();
                    $resolvedTimezone = $localizationService->getTimezone();
                    $session->set('client_timezone', $resolvedTimezone);

                    if (!$session->has('client_timezone_offset')) {
                        $minutes = TimezoneService::getOffsetMinutes($resolvedTimezone);
                        $session->set('client_timezone_offset', $minutes);
                    }
                } catch (\Exception $e) {
                    // If database query fails (e.g., during setup), use default UTC
                    $session->set('client_timezone', 'UTC');
                    $session->set('client_timezone_offset', 0);
                }
            } else {
                // Setup not complete - use default UTC
                $session->set('client_timezone', 'UTC');
                $session->set('client_timezone_offset', 0);
            }
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return $response;
    }
}
