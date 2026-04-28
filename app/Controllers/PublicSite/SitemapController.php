<?php

namespace App\Controllers\PublicSite;

use App\Controllers\BaseController;
use App\Services\PublicBookingService;

class SitemapController extends BaseController
{
    private PublicBookingService $booking;

    public function __construct(?PublicBookingService $booking = null)
    {
        $this->booking = $booking ?? new PublicBookingService();
    }

    public function index()
    {
        $items = $this->booking->getSitemapUrls();

        $xml = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
        ];

        foreach ($items as $item) {
            $loc = $this->xmlEscape((string) ($item['loc'] ?? ''));
            if ($loc === '') {
                continue;
            }

            $lastmod = $this->xmlEscape((string) ($item['lastmod'] ?? ''));
            $changefreq = $this->xmlEscape((string) ($item['changefreq'] ?? 'weekly'));
            $priority = $this->xmlEscape((string) ($item['priority'] ?? '0.7'));

            $xml[] = '  <url>';
            $xml[] = '    <loc>' . $loc . '</loc>';
            if ($lastmod !== '') {
                $xml[] = '    <lastmod>' . $lastmod . '</lastmod>';
            }
            $xml[] = '    <changefreq>' . $changefreq . '</changefreq>';
            $xml[] = '    <priority>' . $priority . '</priority>';
            $xml[] = '  </url>';
        }

        $xml[] = '</urlset>';

        return $this->response
            ->setStatusCode(200)
            ->setContentType('application/xml', 'UTF-8')
            ->setBody(implode("\n", $xml));
    }

    private function xmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
