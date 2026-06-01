<?php

namespace App\Models;

class PaymentTransactionModel extends BaseModel
{
    protected $table      = 'xs_payment_transactions';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'business_id',
        'appointment_id',
        'gateway',
        'gateway_reference',
        'amount',
        'currency',
        'status',
        'raw_payload',
    ];

    /**
     * Find a pending transaction by gateway reference.
     * Used by webhook handlers to detect duplicate deliveries.
     */
    public function findByReference(string $gateway, string $reference): ?array
    {
        return $this->where('gateway', $gateway)
                    ->where('gateway_reference', $reference)
                    ->first();
    }

    /**
     * Find all transactions for an appointment.
     */
    public function forAppointment(int $appointmentId): array
    {
        return $this->where('appointment_id', $appointmentId)
                    ->orderBy('created_at', 'DESC')
                    ->findAll();
    }

    /**
     * Mark an existing transaction as complete and store the raw webhook payload.
     */
    public function markComplete(int $id, string $rawPayload): bool
    {
        return $this->update($id, [
            'status'      => 'complete',
            'raw_payload' => $rawPayload,
        ]);
    }

    /**
     * Mark an existing transaction as failed.
     */
    public function markFailed(int $id, string $rawPayload = ''): bool
    {
        return $this->update($id, [
            'status'      => 'failed',
            'raw_payload' => $rawPayload ?: null,
        ]);
    }
}
