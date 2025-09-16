<?php
namespace App\Controllers\Api\Admin\V1;

use CodeIgniter\RESTful\ResourceController;
use App\Models\UserModel;

class Users extends ResourceController
{
    protected $format = 'json';

    public function index()
    {
        $model = new UserModel();
        return $this->respond(['users' => $model->findAll()]);
    }

    public function create()
    {
        $data = $this->request->getJSON(true) ?? [];
        $model = new UserModel();
        if (!$model->insert($data)) {
            return $this->failValidationErrors($model->errors());
        }
        return $this->respondCreated(['id' => (int)$model->getInsertID()]);
    }

    public function update($id = null)
    {
        $id = (int)$id;
        $model = new UserModel();
        if (!$model->find($id)) {
            return $this->failNotFound('User not found');
        }
        $data = $this->request->getJSON(true) ?? [];
        if (!$model->update($id, $data)) {
            return $this->failValidationErrors($model->errors());
        }
        return $this->respond(['user' => $model->find($id)]);
    }

    public function delete($id = null)
    {
        $id = (int)$id;
        $model = new UserModel();
        if (!$model->find($id)) {
            return $this->failNotFound('User not found');
        }
        $model->delete($id);
        return $this->respondDeleted(['deleted' => true]);
    }
}
