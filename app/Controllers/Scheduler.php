<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Libraries\SlotGenerator;
use App\Models\AppointmentModel;
use App\Models\ServiceModel;
use App\Models\UserModel;
use App\Models\CustomerModel;

class Scheduler extends BaseController
{
    // Admin/staff scheduler dashboard (default view)
    public function index()
    {
        // Provide Services and Providers for filter dropdowns
        $serviceModel = new ServiceModel();
        $services = $serviceModel->orderBy('name', 'ASC')->findAll();
        $providers = (new UserModel())->getProviders();
        return view('scheduler/index', [
            'services' => $services,
            'providers' => $providers,
        ]);
    }

    // Public/client-facing scheduler view
    public function client()
    {
        // Load services for dropdown
        $serviceModel = new ServiceModel();
        $services = $serviceModel->orderBy('name', 'ASC')->findAll();
        return view('scheduler/client', ['services' => $services]);
    }

    // API: GET /api/slots?provider_id=1&service_id=2&date=2025-08-24
    public function slots()
    {
        $providerId = (int) ($this->request->getGet('provider_id') ?? 0);
        $serviceId  = (int) ($this->request->getGet('service_id') ?? 0);
        $date       = $this->request->getGet('date') ?? date('Y-m-d');

        if ($providerId <= 0 || $serviceId <= 0) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'provider_id and service_id are required']);
        }

        $slotGen = new SlotGenerator();
        $slots = $slotGen->getAvailableSlots($providerId, $serviceId, $date);
        return $this->response->setJSON(['date' => $date, 'slots' => $slots]);
    }

    // API: POST /api/book
    // body: { name, email, phone?, provider_id, service_id, date, start, notes? }
    public function book()
    {
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

        // Create or find customer by email in xs_customers
        $customerModel = new CustomerModel();
        $customer = $customerModel->where('email', $data['email'])->first();
        if (!$customer) {
            $names = preg_split('/\s+/', trim((string)$data['name']));
            $first = $names[0] ?? '';
            $last  = count($names) > 1 ? trim(implode(' ', array_slice($names, 1))) : null;
            $customerId = $customerModel->insert([
                'first_name' => $first,
                'last_name'  => $last,
                'email'      => $data['email'],
                'phone'      => $data['phone'] ?? null,
                'created_at' => date('Y-m-d H:i:s'),
            ], false);
        } else {
            $customerId = $customer['id'];
        }

        // Final availability check to avoid race conditions
        $slotGen = new SlotGenerator();
        $available = $slotGen->getAvailableSlots($providerId, $serviceId, $date);
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
            'status' => 'booked',
            'notes' => $data['notes'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->response->setJSON(['ok' => true, 'appointment_id' => $id]);
    }
}
