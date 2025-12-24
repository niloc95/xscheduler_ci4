<?php

namespace App\Commands;

class SendAppointmentWhatsAppReminders extends AbstractQueueDispatchCommand
{
    protected $group = 'notifications';

    protected $name = 'notifications:send-whatsapp-reminders';

    protected $description = 'Legacy alias: enqueue due reminders and dispatch queue (use notifications:dispatch-queue).';

    /**
     * @param array<int, string> $params
     */
    public function run(array $params)
    {
        $businessId = (int) ($params[0] ?? 0);
        $limit = (int) ($params[1] ?? 100);
        $this->runQueue($businessId, $limit, 'WebSchedulr - Send Appointment WhatsApp Reminders');
    }
}

