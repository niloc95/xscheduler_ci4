<?php

namespace App\Models;

class NotificationOptOutModel extends BaseModel
{
    protected $table = 'xs_notification_opt_outs';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'business_id',
        'channel',
        'recipient',
        'reason',
    ];

    protected $validationRules = [
        'business_id' => 'required|is_natural_no_zero',
        'channel' => 'required|in_list[email,sms,whatsapp]',
        'recipient' => 'required|string|max_length[190]',
        'reason' => 'permit_empty|string|max_length[190]',
    ];
}
