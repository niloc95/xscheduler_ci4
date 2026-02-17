<?php

namespace App\Controllers\PublicSite;

use App\Controllers\BaseController;
use App\Exceptions\PublicBookingException;
use App\Services\PublicBookingService;
use CodeIgniter\HTTP\ResponseInterface;

class BookingController extends BaseController
{
    private PublicBookingService $booking;

    public function __construct(?PublicBookingService $booking = null)
    {
        $this->booking = $booking ?? new PublicBookingService();
    }

    public function index()
    {
        $context = $this->booking->buildViewContext();

        $acceptsJson = stripos($this->request->getHeaderLine('Accept'), 'application/json') !== false;
        if ($this->request->isAJAX() || $acceptsJson) {
            return $this->respondJson(['data' => $context]);
        }

        return view('public/booking', ['context' => $context]);
    }

    public function slots()
    {
        try {
            $providerId = (int) ($this->request->getGet('provider_id') ?? 0);
            $serviceId = (int) ($this->request->getGet('service_id') ?? 0);
            $date = $this->request->getGet('date');

            if ($providerId <= 0 || $serviceId <= 0 || !$date) {
                return $this->respondJson([
                    'error' => 'provider_id, service_id, and date are required.',
                ], 422);
            }

            $slots = $this->booking->getAvailableSlots($providerId, $serviceId, $date);
            return $this->respondJson(['data' => $slots]);
        } catch (PublicBookingException $e) {
            return $this->respondJson([
                'error' => $e->getMessage(),
                'details' => $e->getErrors(),
            ], $e->getStatusCode());
        } catch (\Throwable $e) {
            log_message('error', '[PublicBooking] Slot fetch failed: ' . $e->getMessage());
            return $this->respondJson(['error' => 'Unable to load slots.'], 500);
        }
    }

    public function calendar()
    {
        try {
            $providerId = (int) ($this->request->getGet('provider_id') ?? 0);
            $serviceId = (int) ($this->request->getGet('service_id') ?? 0);
            $startDate = $this->request->getGet('start_date');
            $days = (int) ($this->request->getGet('days') ?? 60);

            if ($providerId <= 0 || $serviceId <= 0) {
                return $this->respondJson([
                    'error' => 'provider_id and service_id are required.',
                ], 422);
            }

            if ($startDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
                return $this->respondJson([
                    'error' => 'start_date must use Y-m-d format.',
                ], 422);
            }

            $calendar = $this->booking->getAvailabilityCalendar($providerId, $serviceId, $startDate, $days);
            return $this->respondJson(['data' => $calendar]);
        } catch (PublicBookingException $e) {
            return $this->respondJson([
                'error' => $e->getMessage(),
                'details' => $e->getErrors(),
            ], $e->getStatusCode());
        } catch (\Throwable $e) {
            log_message('error', '[PublicBooking] Calendar fetch failed: ' . $e->getMessage());
            return $this->respondJson(['error' => 'Unable to load availability calendar.'], 500);
        }
    }

    public function store()
    {
        try {
            $payload = $this->payload();
            $result = $this->booking->createBooking($payload);
            return $this->respondJson(['data' => $result], ResponseInterface::HTTP_CREATED);
        } catch (PublicBookingException $e) {
            return $this->respondJson([
                'error' => $e->getMessage(),
                'details' => $e->getErrors(),
            ], $e->getStatusCode());
        } catch (\Throwable $e) {
            log_message('error', '[PublicBooking] Create failed: ' . $e->getMessage());
            return $this->respondJson(['error' => 'Unable to complete booking at this time.'], 500);
        }
    }

    public function show(string $token)
    {
        try {
            $email = $this->request->getGet('email');
            $phone = $this->request->getGet('phone');
            $result = $this->booking->lookupAppointment($token, $email, $phone);
            return $this->respondJson(['data' => $result]);
        } catch (PublicBookingException $e) {
            return $this->respondJson([
                'error' => $e->getMessage(),
                'details' => $e->getErrors(),
            ], $e->getStatusCode());
        } catch (\Throwable $e) {
            log_message('error', '[PublicBooking] Lookup failed: ' . $e->getMessage());
            return $this->respondJson(['error' => 'Unable to locate that booking.'], 500);
        }
    }

    public function update(string $token)
    {
        try {
            $payload = $this->payload();
            $result = $this->booking->reschedule($token, $payload);
            return $this->respondJson(['data' => $result]);
        } catch (PublicBookingException $e) {
            return $this->respondJson([
                'error' => $e->getMessage(),
                'details' => $e->getErrors(),
            ], $e->getStatusCode());
        } catch (\Throwable $e) {
            log_message('error', '[PublicBooking] Reschedule failed: ' . $e->getMessage());
            return $this->respondJson(['error' => 'Unable to update the booking right now.'], 500);
        }
    }

    private function payload(): array
    {
        $json = $this->request->getJSON(true);
        if (is_array($json)) {
            return $json;
        }

        $post = $this->request->getPost();
        if (!empty($post)) {
            return $post;
        }

        return [];
    }

    private function respondJson(array $payload, int $status = 200)
    {
        // Include refreshed CSRF token so JS can update on regenerate
        $payload['csrf'] = [
            'name'  => csrf_token(),
            'value' => csrf_hash(),
        ];

        return $this->response->setStatusCode($status)->setJSON($payload);
    }
}
