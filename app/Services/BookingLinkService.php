<?php

namespace App\Services;

class BookingLinkService
{
    public function bookingHomeUrl(): string
    {
        return base_url('booking');
    }

    public function manageReferenceUrl(?string $hash = null, ?string $publicToken = null): string
    {
        $reference = trim((string) ($hash ?? ''));
        if ($reference === '') {
            $reference = trim((string) ($publicToken ?? ''));
        }

        if ($reference === '') {
            return $this->bookingHomeUrl();
        }

        // Short branded link: /r/{reference}
        return base_url('r/' . rawurlencode($reference));
    }
}