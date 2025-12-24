<?php

namespace App\Models;

class MessageTemplateModel extends BaseModel
{
    protected $table = 'xs_message_templates';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'business_id',
        'event_type',
        'channel',
        'provider',
        'provider_template_id',
        'locale',
        'subject',
        'body',
        'is_active',
    ];

    protected $validationRules = [
        'business_id' => 'required|is_natural_no_zero',
        'event_type'  => 'required|max_length[64]',
        'channel'     => 'required|in_list[email,sms,whatsapp]',
    ];
}
