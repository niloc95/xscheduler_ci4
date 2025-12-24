<?php

namespace App\Models;

class NotificationQueueModel extends BaseModel
{
    protected $table = 'xs_notification_queue';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'business_id',
        'channel',
        'event_type',
        'appointment_id',
        'status',
        'attempts',
        'max_attempts',
        'run_after',
        'locked_at',
        'lock_token',
        'last_error',
        'sent_at',
        'idempotency_key',
        'correlation_id',
    ];

    protected $validationRules = [
        'business_id' => 'required|is_natural_no_zero',
        'channel' => 'required|in_list[email,sms,whatsapp]',
        'event_type' => 'required|string|max_length[64]',
        'idempotency_key' => 'required|string|max_length[128]',
        'correlation_id' => 'permit_empty|string|max_length[64]',
    ];
}
