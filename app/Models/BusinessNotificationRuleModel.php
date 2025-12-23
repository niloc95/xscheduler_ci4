<?php

namespace App\Models;

class BusinessNotificationRuleModel extends BaseModel
{
    protected $table = 'xs_business_notification_rules';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'business_id',
        'event_type',
        'channel',
        'is_enabled',
        'reminder_offset_minutes',
    ];

    protected $validationRules = [
        'business_id' => 'required|is_natural_no_zero',
        'event_type'  => 'required|max_length[64]',
        'channel'     => 'required|in_list[email,sms,whatsapp]',
        'is_enabled'  => 'permit_empty|in_list[0,1]',
    ];
}
