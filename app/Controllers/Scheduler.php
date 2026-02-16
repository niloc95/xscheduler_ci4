<?php

/**
 * =============================================================================
 * LEGACY SCHEDULER CONTROLLER (DEPRECATED)
 * =============================================================================
 * 
 * @file        app/Controllers/Scheduler.php
 * @description Legacy FullCalendar-based scheduler. Routes now redirect to
 *              the new Appointments module. Kept for backward compatibility.
 * 
 * ROUTES HANDLED (ALL DEPRECATED):
 * -----------------------------------------------------------------------------
 * GET  /scheduler                    : 308 Redirect to /appointments
 * GET  /scheduler/client             : 308 Redirect to /appointments
 * GET  /api/slots                    : Legacy availability slots (deprecated)
 * 
 * DEPRECATION NOTICE:
 * -----------------------------------------------------------------------------
 * ⚠️ This controller is ARCHIVED and will be removed in a future version.
 * 
 * Migration path:
 * - /scheduler      → Use /appointments
 * - /api/slots      → Use /api/availability or /api/availability/calendar
 * 
 * HISTORY:
 * -----------------------------------------------------------------------------
 * - Original FullCalendar-based scheduling system
 * - Replaced with new appointment management module (Oct 2025)
 * - Routes return 308 (Permanent Redirect) for SEO
 * - API endpoints maintained temporarily for integrations
 * 
 * @deprecated Since v2.0 - Use Appointments controller instead
 * @see         app/Controllers/Appointments.php for replacement
 * @see         docs/architecture/LEGACY_SCHEDULER_ARCHITECTURE.md
 * @package     App\Controllers
 * @extends     BaseController
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\AppointmentModel;
use App\Models\ServiceModel;
use App\Models\CustomerModel;

class Scheduler extends BaseController
{
    /**
     * ⚠️ ARCHIVED: Legacy Scheduler Controller (FullCalendar-based)
     *
     * Admin/staff and public scheduler routes now redirect permanently to the
     * Appointments module. The API endpoints remain temporarily available for
     * backwards compatibility with legacy integrations.
     *
     * See: docs/architecture/LEGACY_SCHEDULER_ARCHITECTURE.md
     * Replacement: app/Controllers/Appointments.php
     *
     * Last Updated: October 7, 2025
     */

    // Legacy scheduler dashboard route (permanently redirects to Appointments)
    public function index()
    {
        return redirect()->to(base_url('appointments'), 'auto', 308);
    }

    // Legacy public booking route (redirects to Appointments)
    public function client()
    {
        return redirect()->to(base_url('appointments'), 'auto', 308);
    }

    // API: GET /api/slots?provider_id=1&service_id=2&date=2025-08-24
    // @deprecated Use /api/availability or /api/availability/calendar instead
    public function slots()
    {
        // Add deprecation headers
        $this->response->setHeader('Deprecation', 'true');
        $this->response->setHeader('Sunset', 'Sat, 01 Mar 2026 00:00:00 GMT');
        $this->response->setHeader('Link', '</api/availability>; rel="successor-version"');
        
        $providerId = (int) ($this->request->getGet('provider_id') ?? 0);
        $serviceId  = (int) ($this->request->getGet('service_id') ?? 0);
        $date       = $this->request->getGet('date') ?? date('Y-m-d');

        if ($providerId <= 0 || $serviceId <= 0) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'provider_id and service_id are required']);
        }

        $availabilityService = new \App\Services\AvailabilityService();
        $slots = $availabilityService->getAvailableSlots($providerId, $date, $serviceId);
        return $this->response->setJSON(['date' => $date, 'slots' => $slots]);
    }

    // API: POST /api/book
    // body: { name, email, phone?, provider_id, service_id, date, start, notes? }
    // @deprecated Use /api/appointments POST instead
    public function book()
    {
        // Add deprecation headers
        $this->response->setHeader('Deprecation', 'true');
        $this->response->setHeader('Sunset', 'Sat, 01 Mar 2026 00:00:00 GMT');
        $this->response->setHeader('Link', '</api/appointments>; rel="successor-version"');
        
        $data = $this->request->getJSON(true) ?? $this->request->getPost();
        $required = ['name','email','provider_id','service_id','date','start'];
        foreach ($required as $r) {
            if (empty($data[$r])) {
                return $this->response->setStatusCode(400)->setJSON(['error' => "Missing field: $r"]);
            }
        }

        $providerId = (int) $data['provider_id'];
        $serviceId  = (int) $data['service_id'];
        $date       = $data['date'];
        $start      = $data['start']; // HH:MM

        $service = (new ServiceModel())->find($serviceId);
        if (!$service) return $this->response->setStatusCode(404)->setJSON(['error' => 'Service not found']);
        $duration = (int)($service['duration_min'] ?? 30);

        $startDT = strtotime($date . ' ' . $start);
        $endDT   = $startDT + ($duration * 60);

        // Create or find customer by email - using helper method
        $customerModel = new CustomerModel();
        $customerId = $customerModel->findOrCreateByEmail(
            $data['email'],
            $data['name'],
            $data['phone'] ?? null
        );

        // Final availability check to avoid race conditions
        $availabilityService = new \App\Services\AvailabilityService();
        $available = $availabilityService->getAvailableSlots($providerId, $date, $serviceId);
        $requested = date('H:i', $startDT) . '-' . date('H:i', $endDT);
        $isAvailable = false;
        foreach ($available as $s) {
            if ($s['start'] === date('H:i', $startDT) && $s['end'] === date('H:i', $endDT)) {
                $isAvailable = true; break;
            }
        }
        if (!$isAvailable) {
            return $this->response->setStatusCode(409)->setJSON(['error' => 'Time slot no longer available']);
        }

        // Save appointment
        $apptModel = new AppointmentModel();
        $id = $apptModel->insert([
            'customer_id' => $customerId,
            // Maintain NOT NULL user_id by pointing to provider (system user)
            'user_id' => $providerId,
            'provider_id' => $providerId,
            'service_id' => $serviceId,
            'start_time' => date('Y-m-d H:i:s', $startDT),
            'end_time' => date('Y-m-d H:i:s', $endDT),
            'status' => 'pending',
            'notes' => $data['notes'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->response->setJSON(['ok' => true, 'appointment_id' => $id]);
    }
}
