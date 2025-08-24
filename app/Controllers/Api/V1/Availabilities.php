<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Libraries\SlotGenerator;

class Availabilities extends BaseController
{
    // GET /api/v1/availabilities?providerId=1&serviceId=2&date=YYYY-MM-DD
    public function index()
    {
        $providerId = (int) ($this->request->getGet('providerId') ?? 0);
        $serviceId  = (int) ($this->request->getGet('serviceId') ?? 0);
        $date       = $this->request->getGet('date') ?? date('Y-m-d');

        if ($providerId <= 0 || $serviceId <= 0) {
            return $this->response->setStatusCode(400)->setJSON([
                'error' => 'providerId and serviceId are required',
            ]);
        }

        $slotGen = new SlotGenerator();
        $slots = $slotGen->getAvailableSlots($providerId, $serviceId, $date);

        return $this->response->setJSON([
            'providerId' => $providerId,
            'serviceId' => $serviceId,
            'date' => $date,
            'slots' => $slots,
        ]);
    }
}
