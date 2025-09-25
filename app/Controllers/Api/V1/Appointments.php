<?php

namespace App\Controllers\Api\V1;

use App\Models\AppointmentModel;
use App\Services\SchedulingService;

class Appointments extends BaseApiController
{
    // GET /api/v1/appointments?providerId=&date=
    public function index()
    {
        // Support either (start,end) range or (date) single-day filter
        $rules = [
            'providerId' => 'permit_empty|is_natural_no_zero',
            'serviceId' => 'permit_empty|is_natural_no_zero',
            'date' => 'permit_empty|valid_date[Y-m-d]',
            'start' => 'permit_empty|valid_date[Y-m-d]',
            'end' => 'permit_empty|valid_date[Y-m-d]',
        ];
        if (!$this->validate($rules)) {
            return $this->error(400, 'Invalid parameters', 'validation_error', $this->validator->getErrors());
        }

        [$page, $length, $offset] = $this->paginationParams();
        [$sortField, $sortDir] = $this->sortParam(['id','start_time','end_time','provider_id','service_id','status'], 'start_time');

    $providerId = (int) ($this->request->getGet('providerId') ?? 0);
    $serviceId  = (int) ($this->request->getGet('serviceId') ?? 0);
        $date = $this->request->getGet('date');
        $start = $this->request->getGet('start');
        $end   = $this->request->getGet('end');
        $model = new AppointmentModel();
        $builder = $model->orderBy($sortField, strtoupper($sortDir));
        if ($providerId > 0) {
            $builder = $builder->where('provider_id', $providerId);
        }
        if ($serviceId > 0) {
            $builder = $builder->where('service_id', $serviceId);
        }
        if ($date) {
            $builder = $builder->where('start_time >=', $date . ' 00:00:00')
                               ->where('start_time <=', $date . ' 23:59:59');
        }
        if ($start && $end) {
            $builder = $builder->where('start_time >=', $start . ' 00:00:00')
                               ->where('start_time <=', $end . ' 23:59:59');
        }
        $rows = $builder->findAll($length, $offset);
        $totalBuilder = $model->builder();
        if ($providerId > 0) {
            $totalBuilder->where('provider_id', $providerId);
        }
        if ($serviceId > 0) {
            $totalBuilder->where('service_id', $serviceId);
        }
        if ($date) {
            $totalBuilder->where('start_time >=', $date . ' 00:00:00')
                         ->where('start_time <=', $date . ' 23:59:59');
        }
        if ($start && $end) {
            $totalBuilder->where('start_time >=', $start . ' 00:00:00')
                         ->where('start_time <=', $end . ' 23:59:59');
        }
        $total = $totalBuilder->countAllResults();
        $items = array_map(function ($r) {
            return [
                'id' => (int)$r['id'],
                'providerId' => (int)$r['provider_id'],
                'serviceId' => (int)$r['service_id'],
                'customerId' => isset($r['customer_id']) ? (int)$r['customer_id'] : null,
                'title' => 'Service #' . (int)$r['service_id'],
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

    // GET /api/v1/appointments/{id}
    public function show($id = null)
    {
        $id = (int)$id;
        if ($id <= 0) return $this->error(400, 'Invalid id');
        $m = new AppointmentModel();
        $r = $m->find($id);
        if (!$r) return $this->error(404, 'Not found');
        $item = [
            'id' => (int)$r['id'],
            'providerId' => (int)$r['provider_id'],
            'serviceId' => (int)$r['service_id'],
            'customerId' => isset($r['customer_id']) ? (int)$r['customer_id'] : null,
            'title' => 'Service #' . (int)$r['service_id'],
            'start' => $r['start_time'],
            'end' => $r['end_time'],
            'status' => $r['status'] ?? 'booked',
            'notes' => $r['notes'] ?? null,
        ];
        return $this->ok($item);
    }

    // PATCH /api/v1/appointments/{id}
    public function update($id = null)
    {
        $id = (int)$id;
        if ($id <= 0) return $this->error(400, 'Invalid id');
        $payload = $this->request->getJSON(true) ?? $this->request->getRawInput();
        if (!$payload) return $this->error(400, 'No body');

        $update = [];
        if (!empty($payload['start'])) $update['start_time'] = $payload['start'];
        if (!empty($payload['end'])) $update['end_time'] = $payload['end'];
        if (!empty($payload['status'])) $update['status'] = $payload['status'];
        if (empty($update)) return $this->error(400, 'No updatable fields');

        $m = new AppointmentModel();
        if (!$m->find($id)) return $this->error(404, 'Not found');
        $ok = $m->update($id, $update);
        if (!$ok) return $this->error(500, 'Update failed');
        return $this->ok(['ok' => true]);
    }

    // DELETE /api/v1/appointments/{id}
    public function delete($id = null)
    {
        $id = (int)$id;
        if ($id <= 0) return $this->error(400, 'Invalid id');
        $m = new AppointmentModel();
        if (!$m->find($id)) return $this->error(404, 'Not found');
        // Soft cancel
        $ok = $m->update($id, ['status' => 'cancelled']);
        if (!$ok) return $this->error(500, 'Delete failed');
        return $this->ok(['ok' => true]);
    }

    // GET /api/v1/appointments/counts?providerId=&serviceId=
    public function counts()
    {
        $rules = [
            'providerId' => 'permit_empty|is_natural_no_zero',
            'serviceId'  => 'permit_empty|is_natural_no_zero',
        ];
        if (!$this->validate($rules)) {
            return $this->error(400, 'Invalid parameters', 'validation_error', $this->validator->getErrors());
        }

        $providerId = (int) ($this->request->getGet('providerId') ?? 0);
        $serviceId  = (int) ($this->request->getGet('serviceId') ?? 0);

        // Calculate server-side date ranges (local app timezone)
        $now = new \DateTimeImmutable('now');
        $todayStart = $now->setTime(0, 0, 0);
        $todayEnd   = $now->setTime(23, 59, 59);

        // Week: Sunday (0) to Saturday (6) to match UI
        $dow = (int) $now->format('w'); // 0 (Sun) - 6 (Sat)
        $weekStart = $todayStart->modify('-' . $dow . ' days');
        $weekEnd   = $weekStart->modify('+6 days')->setTime(23, 59, 59);

        // Month: first to last day
        $monthStart = $now->modify('first day of this month')->setTime(0, 0, 0);
        $monthEnd   = $now->modify('last day of this month')->setTime(23, 59, 59);

        $counts = [
            'today' => $this->countInRange($providerId, $serviceId, $todayStart, $todayEnd),
            'week'  => $this->countInRange($providerId, $serviceId, $weekStart, $weekEnd),
            'month' => $this->countInRange($providerId, $serviceId, $monthStart, $monthEnd),
        ];

        return $this->ok($counts);
    }

    // GET /api/v1/appointments/summary?providerId=&serviceId=
    // Same as counts(), provided for a clear, global metrics contract
    public function summary()
    {
        $rules = [
            'providerId' => 'permit_empty|is_natural_no_zero',
            'serviceId'  => 'permit_empty|is_natural_no_zero',
        ];
        if (!$this->validate($rules)) {
            return $this->error(400, 'Invalid parameters', 'validation_error', $this->validator->getErrors());
        }

        $providerId = (int) ($this->request->getGet('providerId') ?? 0);
        $serviceId  = (int) ($this->request->getGet('serviceId') ?? 0);

        $now = new \DateTimeImmutable('now');
        $todayStart = $now->setTime(0, 0, 0);
        $todayEnd   = $now->setTime(23, 59, 59);

        $dow = (int) $now->format('w');
        $weekStart = $todayStart->modify('-' . $dow . ' days');
        $weekEnd   = $weekStart->modify('+6 days')->setTime(23, 59, 59);

        $monthStart = $now->modify('first day of this month')->setTime(0, 0, 0);
        $monthEnd   = $now->modify('last day of this month')->setTime(23, 59, 59);

        $summary = [
            'today' => $this->countInRange($providerId, $serviceId, $todayStart, $todayEnd),
            'week'  => $this->countInRange($providerId, $serviceId, $weekStart, $weekEnd),
            'month' => $this->countInRange($providerId, $serviceId, $monthStart, $monthEnd),
        ];

        return $this->ok($summary);
    }

    private function countInRange(int $providerId, int $serviceId, \DateTimeImmutable $start, \DateTimeImmutable $end): int
    {
        $m = new AppointmentModel();
        $b = $m->builder();
        $b->select('COUNT(*) AS c')
          ->where('start_time >=', $start->format('Y-m-d H:i:s'))
          ->where('start_time <=', $end->format('Y-m-d H:i:s'));
        if ($providerId > 0) $b->where('provider_id', $providerId);
        if ($serviceId  > 0) $b->where('service_id',  $serviceId);
        $row = $b->get()->getRowArray();
        return (int) ($row['c'] ?? 0);
    }
}
