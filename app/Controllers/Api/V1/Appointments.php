<?php

namespace App\Controllers\Api\V1;

use App\Models\AppointmentModel;
use App\Services\SchedulingService;

class Appointments extends BaseApiController
{
    // GET /api/v1/appointments?providerId=&date=
    public function index()
    {
        $rules = [
            'providerId' => 'permit_empty|is_natural_no_zero',
            'date' => 'permit_empty|valid_date[Y-m-d]',
        ];
        if (!$this->validate($rules)) {
            return $this->error(400, 'Invalid parameters', 'validation_error', $this->validator->getErrors());
        }

        [$page, $length, $offset] = $this->paginationParams();
        [$sortField, $sortDir] = $this->sortParam(['id','start_time','end_time','provider_id','service_id','status'], 'start_time');

        $providerId = (int) ($this->request->getGet('providerId') ?? 0);
        $date = $this->request->getGet('date');
        $model = new AppointmentModel();
        $builder = $model->orderBy($sortField, strtoupper($sortDir));
        if ($providerId > 0) {
            $builder = $builder->where('provider_id', $providerId);
        }
        if ($date) {
            $builder = $builder->where('DATE(start_time)', $date);
        }
    $rows = $builder->findAll($length, $offset);
    $totalBuilder = $model->builder();
    if ($providerId > 0) {
        $totalBuilder->where('provider_id', $providerId);
    }
    if ($date) {
        $totalBuilder->where('DATE(start_time)', $date);
    }
    $total = $totalBuilder->countAllResults();
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
    return $this->ok($items, [
        'page' => $page,
        'length' => $length,
        'total' => (int)$total,
        'sort' => $sortField . ':' . $sortDir,
    ]);
    }

    // POST /api/v1/appointments
    // { name, email, phone?, providerId, serviceId, date, start, notes? }
    public function create()
    {
        $payload = $this->request->getJSON(true) ?? $this->request->getPost();
        $rules = [
            'name' => 'required|min_length[2]',
            'email' => 'required|valid_email',
            'providerId' => 'required|is_natural_no_zero',
            'serviceId' => 'required|is_natural_no_zero',
            'date' => 'required|valid_date[Y-m-d]',
            'start' => 'required|regex_match[/^\\d{2}:\\d{2}$/]',
            'phone' => 'permit_empty|string',
            'notes' => 'permit_empty|string',
        ];
        if (!$this->validateData($payload, $rules)) {
            return $this->error(400, 'Invalid request body', 'validation_error', $this->validator->getErrors());
        }
        $svc = new SchedulingService();
        try {
            $res = $svc->createAppointment($payload);
        } catch (\InvalidArgumentException $e) {
            return $this->error(400, $e->getMessage());
        } catch (\RuntimeException $e) {
            $status = $e->getMessage() === 'Service not found' ? 404 : 409;
            return $this->error($status, $e->getMessage());
        }
        return $this->created($res);
    }
}
