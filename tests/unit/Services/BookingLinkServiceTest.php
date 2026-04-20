<?php

namespace Tests\Unit\Services;

use App\Services\BookingLinkService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class BookingLinkServiceTest extends CIUnitTestCase
{
    public function testManageReferenceUrlUsesHashWhenAvailable(): void
    {
        $service = new BookingLinkService();

        $url = $service->manageReferenceUrl('abc123', 'token456');

        $this->assertStringContainsString('/r/abc123', $url);
    }

    public function testManageReferenceUrlFallsBackToPublicToken(): void
    {
        $service = new BookingLinkService();

        $url = $service->manageReferenceUrl('', 'token456');

        $this->assertStringContainsString('/r/token456', $url);
    }

    public function testManageReferenceUrlFallsBackToBookingHomeWithoutReference(): void
    {
        $service = new BookingLinkService();

        $url = $service->manageReferenceUrl('', '');

        $this->assertStringContainsString('/booking', $url);
        $this->assertStringNotContainsString('/r/', $url);
    }
}
