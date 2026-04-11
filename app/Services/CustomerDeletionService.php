<?php

namespace App\Services;

use App\Models\AppointmentModel;
use App\Models\CustomerModel;

class CustomerDeletionService
{
    protected CustomerModel $customers;
    protected AppointmentModel $appointments;

    public function __construct(
        ?CustomerModel $customers = null,
        ?AppointmentModel $appointments = null,
    ) {
        $this->customers = $customers ?? new CustomerModel();
        $this->appointments = $appointments ?? new AppointmentModel();
    }

    public function deleteCustomerByIdentifier(int $currentUserId, string $identifier): array
    {
        if ($currentUserId <= 0) {
            return [
                'success' => false,
                'message' => 'Unauthorized.',
                'statusCode' => 401,
            ];
        }

        $customer = $this->customers->findByIdentifier($identifier);
        if (!$customer) {
            return [
                'success' => false,
                'message' => 'Customer not found.',
                'statusCode' => 404,
            ];
        }

        $customerId = (int) ($customer['id'] ?? 0);
        $appointmentCount = $this->countAppointmentsForCustomer($customerId);

        if ($appointmentCount > 0) {
            return [
                'success' => false,
                'message' => 'Customer cannot be deleted because appointment history exists.',
                'statusCode' => 422,
                'blockCode' => 'appointments_exist',
                'appointmentCount' => $appointmentCount,
            ];
        }

        if (!$this->customers->delete($customerId)) {
            return [
                'success' => false,
                'message' => 'Failed to delete customer.',
                'statusCode' => 500,
            ];
        }

        return [
            'success' => true,
            'message' => 'Customer deleted successfully.',
            'customerId' => $customerId,
        ];
    }

    public function countAppointmentsForCustomer(int $customerId): int
    {
        if ($customerId <= 0) {
            return 0;
        }

        return (int) $this->appointments
            ->builder()
            ->where('customer_id', $customerId)
            ->countAllResults();
    }
}