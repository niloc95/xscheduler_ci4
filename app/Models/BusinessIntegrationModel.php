<?php

namespace App\Models;

class BusinessIntegrationModel extends BaseModel
{
    protected $table = 'xs_business_integrations';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'business_id',
        'channel',
        'provider_name',
        'encrypted_config',
        'is_active',
        'health_status',
        'last_tested_at',
    ];

    protected $validationRules = [
        'business_id' => 'required|is_natural_no_zero',
        'channel'     => 'required|in_list[email,sms,whatsapp]',
    ];
}
