<?php

namespace App\Models;

class AppointmentCustomFieldModel extends BaseModel
{
    protected $table = 'xs_appointment_custom_fields';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'appointment_id',
        'field_key',
        'value',
        'created_at',
        'updated_at',
    ];
}
