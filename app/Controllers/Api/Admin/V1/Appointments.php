<?php
namespace App\Controllers\Api\Admin\V1;

use CodeIgniter\RESTful\ResourceController;
use App\Models\AppointmentModel;

class Appointments extends ResourceController
{
    protected $format = 'json';

    public function index()
    {
        $model = new AppointmentModel();
        return $this->respond(['appointments' => $model->findAll(200)]);
    }

    public function show($id = null)
    {
        $model = new AppointmentModel();
        $row = $model->find((int)$id);
        if (!$row) return $this->failNotFound('Not found');
        return $this->respond(['appointment' => $row]);
    }
}
