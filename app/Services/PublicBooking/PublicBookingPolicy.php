<?php

/**
 * Customer self-service policy for public bookings: reschedule/cancel
 * windows, booking horizon, and the legal links shown on the booking page.
 *
 * Collaborator of App\Services\PublicBookingService (the public booking facade).
 */

namespace App\Services\PublicBooking;

use App\Exceptions\PublicBookingException;
use App\Models\SettingModel;
use App\Services\Appointment\AppointmentStatus;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

class PublicBookingPolicy
{
    private SettingModel $settings;

    public function __construct(
        SettingModel $settings
    ) {
        $this->settings = $settings;
    }

    /**
     * Check whether an appointment's current start time is outside the reschedule policy window.
     * Returns false when rescheduling is disabled OR the appointment is within the cutoff.
     */
    public function canReschedule(string $startAt): bool
    {
        $policy = $this->getReschedulePolicy();
        return $this->isOutsidePolicyWindow($startAt, $policy);
    }

    public function assertRescheduleWindow(string $currentStart): void
    {
        $policy = $this->getReschedulePolicy();
        if (!$policy['enabled']) {
            throw new PublicBookingException('Rescheduling is not permitted for this booking.', 403);
        }

        // DB stores UTC — compare both datetimes in UTC
        $start = new DateTimeImmutable($currentStart, new DateTimeZone('UTC'));
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        if ($start <= $now) {
            throw new PublicBookingException('This appointment can no longer be rescheduled.', 403);
        }

        $cutoff = $now->add(new DateInterval('PT' . $policy['hours'] . 'H'));
        if ($cutoff > $start) {
            throw new PublicBookingException('This appointment is too close to reschedule online.', 403);
        }
    }

    /**
     * Check whether the appointment can be cancelled online based on status and policy window.
     */
    public function canCancel(array $appointment): bool
    {
        $status = AppointmentStatus::normalize((string) ($appointment['status'] ?? ''));
        if (!in_array($status, AppointmentStatus::UPCOMING, true)) {
            return false;
        }

        $policy = $this->getCancelPolicy();
        return $this->isOutsidePolicyWindow((string) ($appointment['start_at'] ?? ''), $policy);
    }

    public function assertCancellationWindow(array $appointment): void
    {
        $status = AppointmentStatus::normalize((string) ($appointment['status'] ?? ''));

        if ($status === AppointmentStatus::CANCELLED) {
            throw new PublicBookingException('This appointment has already been cancelled.', 409);
        }

        if (!in_array($status, AppointmentStatus::UPCOMING, true)) {
            throw new PublicBookingException('This appointment can no longer be cancelled online.', 403);
        }

        $policy = $this->getCancelPolicy();
        if (!$policy['enabled']) {
            throw new PublicBookingException('Online cancellations are not permitted for this booking.', 403);
        }

        $start = new DateTimeImmutable((string) $appointment['start_at'], new DateTimeZone('UTC'));
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        if ($start <= $now) {
            throw new PublicBookingException('This appointment can no longer be cancelled online.', 403);
        }

        $cutoff = $now->add(new DateInterval('PT' . $policy['hours'] . 'H'));
        if ($cutoff > $start) {
            throw new PublicBookingException('This appointment is too close to cancel online.', 403);
        }
    }

    public function getReschedulePolicy(): array
    {
        $settings = $this->settings->getByKeys(['business.reschedule']);
        $value = $settings['business.reschedule'] ?? '24h';

        return match ($value) {
            'none' => ['enabled' => false, 'hours' => 0, 'label' => 'Disabled'],
            '12h' => ['enabled' => true, 'hours' => 12, 'label' => '12 hours'],
            default => ['enabled' => true, 'hours' => 24, 'label' => '24 hours'],
        };
    }

    public function getCancelPolicy(): array
    {
        $settings = $this->settings->getByKeys(['business.cancel']);
        $value = $settings['business.cancel'] ?? '24h';

        return match ($value) {
            'none' => ['enabled' => false, 'hours' => 0, 'label' => 'Disabled'],
            '12h' => ['enabled' => true, 'hours' => 12, 'label' => '12 hours'],
            default => ['enabled' => true, 'hours' => 24, 'label' => '24 hours'],
        };
    }

    /**
     * Returns the maximum number of days ahead a public customer may book.
     * Defaults to 60 when the setting is absent (matches legacy hardcoded value).
     */
    public function getFutureLimitDays(): int
    {
        $settings = $this->settings->getByKeys(['business.future_limit']);
        $value = (int) ($settings['business.future_limit'] ?? 60);
        return $value > 0 ? $value : 60;
    }

    /**
     * Reject slots the public booking page would never offer: in the past, or
     * beyond the booking horizon (business.future_limit days). The slot picker
     * already hides these; this stops hand-crafted requests.
     */
    public function assertBookableStart(DateTimeImmutable $start): void
    {
        $now = new DateTimeImmutable('now', $start->getTimezone());

        if ($start <= $now) {
            throw new PublicBookingException('That time has already passed. Please choose a future slot.', 422, ['slot_start' => 'past']);
        }

        $horizon = $now->setTime(23, 59, 59)->modify('+' . $this->getFutureLimitDays() . ' days');
        if ($start > $horizon) {
            throw new PublicBookingException('That date is too far ahead to book online.', 422, ['slot_start' => 'beyond_limit']);
        }
    }

    public function getLegalPolicyContext(): array
    {
        $legal = $this->settings->getByKeys([
            'legal.cancellation_policy',
            'legal.rescheduling_policy',
            'legal.terms_url',
            'legal.privacy_url',
        ]);

        return [
            'cancellationPolicy' => (string) ($legal['legal.cancellation_policy'] ?? ''),
            'reschedulingPolicy' => (string) ($legal['legal.rescheduling_policy'] ?? ''),
            'termsUrl' => (string) ($legal['legal.terms_url'] ?? ''),
            'privacyUrl' => (string) ($legal['legal.privacy_url'] ?? ''),
            'legalPageUrl' => (string) base_url('booking/legal'),
        ];
    }

    public function isOutsidePolicyWindow(string $startAt, array $policy): bool
    {
        if (!$policy['enabled']) {
            return false;
        }

        if (trim($startAt) === '') {
            return false;
        }

        $start = new DateTimeImmutable($startAt, new DateTimeZone('UTC'));
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        if ($start <= $now) {
            return false;
        }

        $cutoff = $now->add(new DateInterval('PT' . $policy['hours'] . 'H'));
        return $cutoff <= $start;
    }
}
