<?php

namespace App\Controllers\Api\V1;

use App\Services\SchedulingService;

class Availabilities extends BaseApiController
{
    // GET /api/v1/availabilities?providerId=1&serviceId=2&date=YYYY-MM-DD
    public function index()
    {
        $rules = [
            'providerId' => 'required|is_natural_no_zero',
            'serviceId'  => 'required|is_natural_no_zero',
            'date'       => 'required|valid_date[Y-m-d]'
        ];
        if (!$this->validate($rules)) {
            return $this->error(400, 'Invalid parameters', 'validation_error', $this->validator->getErrors());
        }

        $providerId = (int) $this->request->getGet('providerId');
        $serviceId  = (int) $this->request->getGet('serviceId');
        $date       = $this->request->getGet('date');

        $svc = new SchedulingService();
        $slots = $svc->getAvailabilities($providerId, $serviceId, $date);
        return $this->ok([
            'providerId' => $providerId,
            'serviceId' => $serviceId,
            'date' => $date,
            'slots' => $slots,
        ]);
    }
}
