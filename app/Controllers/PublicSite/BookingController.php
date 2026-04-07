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

    public function reference(string $reference)
    {
        $context = $this->booking->buildViewContext();
        $context['manageReference'] = $reference;

        return view('public/booking', ['context' => $context]);
    }

    public function slots()
    {
        helper('logging');

        try {
            $providerId = (int) ($this->request->getGet('provider_id') ?? 0);
            $serviceId = (int) ($this->request->getGet('service_id') ?? 0);
            $date = $this->request->getGet('date');
            $locationId = (int) ($this->request->getGet('location_id') ?? 0) ?: null;

            if ($providerId <= 0 || $serviceId <= 0 || !$date) {
                log_structured('warning', 'public_booking.slots_validation_failed', [
                    'provider_id' => $providerId,
                    'service_id' => $serviceId,
                    'date' => (string) $date,
                    'location_id' => $locationId,
                ]);
                return $this->respondJson([
                    'error' => 'provider_id, service_id, and date are required.',
                ], 422);
            }

            $slots = $this->booking->getAvailableSlots($providerId, $serviceId, $date, $locationId);
            log_structured('info', 'public_booking.slots_loaded', [
                'provider_id' => $providerId,
                'service_id' => $serviceId,
                'date' => (string) $date,
                'location_id' => $locationId,
                'slot_count' => count($slots),
            ]);
            return $this->respondJson(['data' => $slots]);
        } catch (PublicBookingException $e) {
            log_structured('warning', 'public_booking.slots_failed', [
                'message' => $e->getMessage(),
                'errors' => $e->getErrors(),
                'status_code' => $e->getStatusCode(),
            ]);
            return $this->respondJson([
                'error' => $e->getMessage(),
                'details' => $e->getErrors(),
            ], $e->getStatusCode());
        } catch (\Throwable $e) {
            log_message('error', '[PublicBooking] Slot fetch failed: ' . $e->getMessage());
            log_structured('error', 'public_booking.slots_exception', [
                'exception_message' => $e->getMessage(),
                'exception_class' => get_class($e),
            ]);
            return $this->respondJson(['error' => 'Unable to load slots.'], 500);
        }
    }

    public function calendar()
    {
        helper('logging');

        try {
            $providerId = (int) ($this->request->getGet('provider_id') ?? 0);
            $serviceId = (int) ($this->request->getGet('service_id') ?? 0);
            $startDate = $this->request->getGet('start_date');
            $days = (int) ($this->request->getGet('days') ?? 60);
            $locationId = (int) ($this->request->getGet('location_id') ?? 0) ?: null;

            if ($providerId <= 0 || $serviceId <= 0) {
                log_structured('warning', 'public_booking.calendar_validation_failed', [
                    'provider_id' => $providerId,
                    'service_id' => $serviceId,
                    'start_date' => (string) $startDate,
                    'days' => $days,
                ]);
                return $this->respondJson([
                    'error' => 'provider_id and service_id are required.',
                ], 422);
            }

            if ($startDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
                log_structured('warning', 'public_booking.calendar_validation_failed', [
                    'provider_id' => $providerId,
                    'service_id' => $serviceId,
                    'start_date' => (string) $startDate,
                    'reason' => 'invalid_start_date_format',
                ]);
                return $this->respondJson([
                    'error' => 'start_date must use Y-m-d format.',
                ], 422);
            }

            $calendar = $this->booking->getAvailabilityCalendar($providerId, $serviceId, $startDate, $days, $locationId);
            log_structured('info', 'public_booking.calendar_loaded', [
                'provider_id' => $providerId,
                'service_id' => $serviceId,
                'start_date' => (string) $startDate,
                'days' => $days,
                'location_id' => $locationId,
                'available_dates' => count($calendar['availableDates'] ?? []),
            ]);
            return $this->respondJson(['data' => $calendar]);
        } catch (PublicBookingException $e) {
            log_structured('warning', 'public_booking.calendar_failed', [
                'message' => $e->getMessage(),
                'errors' => $e->getErrors(),
                'status_code' => $e->getStatusCode(),
            ]);
            return $this->respondJson([
                'error' => $e->getMessage(),
                'details' => $e->getErrors(),
            ], $e->getStatusCode());
        } catch (\Throwable $e) {
            log_message('error', '[PublicBooking] Calendar fetch failed: ' . $e->getMessage());
            log_structured('error', 'public_booking.calendar_exception', [
                'exception_message' => $e->getMessage(),
                'exception_class' => get_class($e),
            ]);
            return $this->respondJson(['error' => 'Unable to load availability calendar.'], 500);
        }
    }

    public function store()
    {
        helper('logging');

        try {
            $payload = $this->payload();
            log_structured('info', 'public_booking.create_attempt', [
                'provider_id' => isset($payload['provider_id']) ? (int) $payload['provider_id'] : null,
                'service_id' => isset($payload['service_id']) ? (int) $payload['service_id'] : null,
                'location_id' => isset($payload['location_id']) ? (int) $payload['location_id'] : null,
                'slot_date' => (string) ($payload['date'] ?? ''),
                'slot_start' => (string) ($payload['start'] ?? ''),
            ]);
            $result = $this->booking->createBooking($payload);
            log_structured('info', 'public_booking.created', [
                'appointment_id' => isset($result['appointment']['id']) ? (int) $result['appointment']['id'] : null,
                'provider_id' => isset($result['appointment']['provider_id']) ? (int) $result['appointment']['provider_id'] : null,
                'service_id' => isset($result['appointment']['service_id']) ? (int) $result['appointment']['service_id'] : null,
            ]);
            return $this->respondJson(['data' => $result], ResponseInterface::HTTP_CREATED);
        } catch (PublicBookingException $e) {
            log_structured('warning', 'public_booking.create_failed', [
                'message' => $e->getMessage(),
                'errors' => $e->getErrors(),
                'status_code' => $e->getStatusCode(),
            ]);
            return $this->respondJson([
                'error' => $e->getMessage(),
                'details' => $e->getErrors(),
            ], $e->getStatusCode());
        } catch (\Throwable $e) {
            log_message('error', '[PublicBooking] Create failed: ' . $e->getMessage());
            log_structured('error', 'public_booking.create_exception', [
                'exception_message' => $e->getMessage(),
                'exception_class' => get_class($e),
            ]);
            return $this->respondJson(['error' => 'Unable to complete booking at this time.'], 500);
        }
    }

    public function show(string $token)
    {
        helper('logging');

        try {
            $email = $this->request->getGet('email');
            $phone = $this->request->getGet('phone');
            $phoneCountryCode = $this->request->getGet('phone_country_code');
            $result = $this->booking->lookupAppointment($token, $email, $phone, $phoneCountryCode);
            log_structured('info', 'public_booking.lookup_success', [
                'token_prefix' => substr($token, 0, 8),
                'appointment_id' => isset($result['appointment']['id']) ? (int) $result['appointment']['id'] : null,
            ]);
            return $this->respondJson(['data' => $result]);
        } catch (PublicBookingException $e) {
            log_structured('warning', 'public_booking.lookup_failed', [
                'token_prefix' => substr($token, 0, 8),
                'message' => $e->getMessage(),
                'errors' => $e->getErrors(),
                'status_code' => $e->getStatusCode(),
            ]);
            return $this->respondJson([
                'error' => $e->getMessage(),
                'details' => $e->getErrors(),
            ], $e->getStatusCode());
        } catch (\Throwable $e) {
            log_message('error', '[PublicBooking] Lookup failed: ' . $e->getMessage());
            log_structured('error', 'public_booking.lookup_exception', [
                'token_prefix' => substr($token, 0, 8),
                'exception_message' => $e->getMessage(),
                'exception_class' => get_class($e),
            ]);
            return $this->respondJson(['error' => 'Unable to locate that booking.'], 500);
        }
    }

    public function search()
    {
        helper('logging');

        try {
            $email = $this->request->getGet('email');
            $phone = $this->request->getGet('phone');
            $phoneCountryCode = $this->request->getGet('phone_country_code');
            $results = $this->booking->lookupAppointmentsByContact($email, $phone, $phoneCountryCode);
            log_structured('info', 'public_booking.search_success', [
                'result_count' => count($results),
            ]);
            return $this->respondJson(['data' => $results]);
        } catch (PublicBookingException $e) {
            log_structured('warning', 'public_booking.search_failed', [
                'message' => $e->getMessage(),
                'errors' => $e->getErrors(),
                'status_code' => $e->getStatusCode(),
            ]);
            return $this->respondJson([
                'error' => $e->getMessage(),
                'details' => $e->getErrors(),
            ], $e->getStatusCode());
        } catch (\Throwable $e) {
            log_message('error', '[PublicBooking] Search failed: ' . $e->getMessage());
            log_structured('error', 'public_booking.search_exception', [
                'exception_message' => $e->getMessage(),
                'exception_class' => get_class($e),
            ]);
            return $this->respondJson(['error' => 'Unable to search for bookings.'], 500);
        }
    }

    public function update(string $token)
    {
        helper('logging');

        try {
            $payload = $this->payload();
            log_structured('info', 'public_booking.reschedule_attempt', [
                'token_prefix' => substr($token, 0, 8),
                'provider_id' => isset($payload['provider_id']) ? (int) $payload['provider_id'] : null,
                'service_id' => isset($payload['service_id']) ? (int) $payload['service_id'] : null,
                'date' => (string) ($payload['date'] ?? ''),
                'start' => (string) ($payload['start'] ?? ''),
            ]);
            $result = $this->booking->reschedule($token, $payload);
            log_structured('info', 'public_booking.rescheduled', [
                'token_prefix' => substr($token, 0, 8),
                'appointment_id' => isset($result['appointment']['id']) ? (int) $result['appointment']['id'] : null,
            ]);
            return $this->respondJson(['data' => $result]);
        } catch (PublicBookingException $e) {
            log_structured('warning', 'public_booking.reschedule_failed', [
                'token_prefix' => substr($token, 0, 8),
                'message' => $e->getMessage(),
                'errors' => $e->getErrors(),
                'status_code' => $e->getStatusCode(),
            ]);
            return $this->respondJson([
                'error' => $e->getMessage(),
                'details' => $e->getErrors(),
            ], $e->getStatusCode());
        } catch (\Throwable $e) {
            log_message('error', '[PublicBooking] Reschedule failed: ' . $e->getMessage());
            log_structured('error', 'public_booking.reschedule_exception', [
                'token_prefix' => substr($token, 0, 8),
                'exception_message' => $e->getMessage(),
                'exception_class' => get_class($e),
            ]);
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
