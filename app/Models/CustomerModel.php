<?php
namespace App\Models;

class CustomerModel extends BaseModel
{
    protected $table = 'customers';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'first_name','last_name','email','phone','address','notes','created_at','updated_at'
    ];
    protected $validationRules = [
        'first_name' => 'required|min_length[1]|max_length[120]',
        'last_name'  => 'permit_empty|max_length[160]',
        'email'      => 'permit_empty|valid_email',
        'phone'      => 'permit_empty|max_length[32]'
    ];

    public function findByEmailOrPhone(?string $email, ?string $phone): ?array
    {
        if (!$email && !$phone) return null;
        $builder = $this->builder();
        if ($email) { $builder->groupStart()->where('email', $email)->groupEnd(); }
        if ($phone) { $builder->orWhere('phone', $phone); }
        return $builder->get()->getRowArray();
    }
}
