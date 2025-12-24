<?php

namespace App\Models;

class NotificationDeliveryLogModel extends BaseModel
{
    protected $table = 'xs_notification_delivery_logs';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'business_id',
        'queue_id',
        'correlation_id',
        'channel',
        'event_type',
        'appointment_id',
        'recipient',
        'provider',
        'status',
        'attempt',
        'error_message',
    ];

    protected $validationRules = [
        'business_id' => 'required|is_natural_no_zero',
        'channel' => 'required|in_list[email,sms,whatsapp]',
        'event_type' => 'required|string|max_length[64]',
        'status' => 'required|in_list[success,failed,cancelled,skipped]',
        'attempt' => 'permit_empty|is_natural_no_zero',
    ];
}
