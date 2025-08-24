<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Models\AppointmentModel;
use App\Models\ServiceModel;
use App\Models\UserModel;
use App\Libraries\SlotGenerator;

class Appointments extends BaseController
{
    // GET /api/v1/appointments?providerId=&date=
    public function index()
    {
        $providerId = (int) ($this->request->getGet('providerId') ?? 0);
        $date = $this->request->getGet('date');
        $model = new AppointmentModel();
        $builder = $model->orderBy('start_time', 'ASC');
        if ($providerId > 0) {
            $builder = $builder->where('provider_id', $providerId);
        }
        if ($date) {
            $builder = $builder->where('DATE(start_time)', $date);
        }
        $rows = $builder->findAll();
        $items = array_map(function ($r) {
            return [
                'id' => (int)$r['id'],
                'providerId' => (int)$r['provider_id'],
                'serviceId' => (int)$r['service_id'],
                'customerId' => (int)$r['user_id'],
                'start' => $r['start_time'],
                'end' => $r['end_time'],
                'status' => $r['status'] ?? 'booked',
                'notes' => $r['notes'] ?? null,
            ];
        }, $rows);

        return $this->response->setJSON(['data' => $items]);
    }

    // POST /api/v1/appointments
    // { name, email, phone?, providerId, serviceId, date, start, notes? }
    public function create()
    {
        $payload = $this->request->getJSON(true) ?? $this->request->getPost();
        $required = ['name','email','providerId','serviceId','date','start'];
        foreach ($required as $r) {
            if (empty($payload[$r])) {
                return $this->response->setStatusCode(400)->setJSON(['error' => "Missing field: $r"]);
            }
        }

        $providerId = (int)$payload['providerId'];
        $serviceId  = (int)$payload['serviceId'];
        $date       = $payload['date'];
        $start      = $payload['start'];

        // Service and duration
        $service = (new ServiceModel())->find($serviceId);
        if (!$service) return $this->response->setStatusCode(404)->setJSON(['error' => 'Service not found']);
        $duration = (int)($service['duration_min'] ?? 30);

        $startDT = strtotime($date . ' ' . $start);
        $endDT   = $startDT + ($duration * 60);

        // Upsert customer by email
        $userModel = new UserModel();
        $user = $userModel->where('email', $payload['email'])->first();
        if (!$user) {
            $userId = $userModel->insert([
                'name' => $payload['name'],
                'email' => $payload['email'],
                'phone' => $payload['phone'] ?? null,
                'password_hash' => password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT),
                'role' => 'customer',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } else {
            $userId = $user['id'];
        }

        // Availability check
        $slotGen = new SlotGenerator();
        $available = $slotGen->getAvailableSlots($providerId, $serviceId, $date);
        $isAvailable = false;
        foreach ($available as $s) {
            if ($s['start'] === date('H:i', $startDT) && $s['end'] === date('H:i', $endDT)) {
                $isAvailable = true; break;
            }
        }
        if (!$isAvailable) {
            return $this->response->setStatusCode(409)->setJSON(['error' => 'Time slot no longer available']);
        }

        // Create appointment
        $apptModel = new AppointmentModel();
        $id = $apptModel->insert([
            'user_id' => $userId,
            'provider_id' => $providerId,
            'service_id' => $serviceId,
            'start_time' => date('Y-m-d H:i:s', $startDT),
            'end_time' => date('Y-m-d H:i:s', $endDT),
            'status' => 'booked',
            'notes' => $payload['notes'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->response->setJSON(['ok' => true, 'appointmentId' => $id]);
    }
}
