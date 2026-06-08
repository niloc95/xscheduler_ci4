<?php

namespace App\Services;

use App\Models\AuditLogModel;
use App\Models\CustomerModel;
use InvalidArgumentException;
use RuntimeException;

class CustomerService
{
    private CustomerModel $customers;
    private PhoneNumberService $phoneNumbers;
    private AuditLogModel $auditLogs;

    public function __construct(
        ?CustomerModel $customers = null,
        ?PhoneNumberService $phoneNumbers = null,
        ?AuditLogModel $auditLogs = null,
    ) {
        $this->customers = $customers ?? new CustomerModel();
        $this->phoneNumbers = $phoneNumbers ?? new PhoneNumberService();
        $this->auditLogs = $auditLogs ?? new AuditLogModel();
    }

    /**
     * Upsert a customer record by email, or by full name when email is absent.
     *
     * Matching rules:
     * - Email present: match by normalized email only.
     * - Email absent: match by first_name + last_name.
     * - Optional customer_id hint is honored when no email match exists.
     *
     * @return array{id:int,wasCreated:bool,changedFields:array<int,string>}
     */
    public function upsertCustomer(array $data): array
    {
        $normalized = $this->normalizePayload($data);

        $preferredCustomerId = isset($data['customer_id']) && is_numeric($data['customer_id'])
            ? (int) $data['customer_id']
            : null;

        $existing = $this->findExistingCustomer(
            $normalized['email'],
            $normalized['first_name'],
            $normalized['last_name'],
            $preferredCustomerId
        );

        if ($existing !== null) {
            $update = $this->buildUpdatePatch($existing, $normalized);
            $changedFields = array_keys($update);

            if ($update !== []) {
                $ok = $this->customers->update((int) $existing['id'], $update);
                if ($ok === false) {
                    throw new RuntimeException('Unable to update customer record.');
                }

                $this->logCustomerMutation('customer_updated', (int) $existing['id'], $changedFields);
            }

            return [
                'id' => (int) $existing['id'],
                'wasCreated' => false,
                'changedFields' => $changedFields,
            ];
        }

        $insertData = $this->buildInsertPayload($normalized);
        $insertId = $this->customers->insert($insertData, true);

        if (!$insertId) {
            throw new RuntimeException('Unable to create customer record.');
        }

        $this->logCustomerMutation('customer_created', (int) $insertId, array_keys($insertData));

        return [
            'id' => (int) $insertId,
            'wasCreated' => true,
            'changedFields' => array_keys($insertData),
        ];
    }

    /**
     * @return array{first_name:?string,last_name:?string,email:?string,phone:?string,address:?string,notes:?string,custom_fields:?string}
     */
    private function normalizePayload(array $data): array
    {
        $firstName = $this->nullableString($data['first_name'] ?? $data['customer_first_name'] ?? null);
        $lastName = $this->nullableString($data['last_name'] ?? $data['customer_last_name'] ?? null);
        $email = $this->normalizeEmail($data['email'] ?? $data['customer_email'] ?? null);

        $rawPhone = $data['phone'] ?? $data['customer_phone'] ?? null;
        $phoneCountryCode = $data['phone_country_code'] ?? $data['customer_phone_country_code'] ?? null;
        $phone = $this->phoneNumbers->normalize(is_string($rawPhone) ? $rawPhone : null, is_string($phoneCountryCode) ? $phoneCountryCode : null);

        if ($this->nullableString($rawPhone) !== null && $phone === null) {
            throw new InvalidArgumentException('Please provide a valid phone number in E.164 format.');
        }

        $address = $this->nullableString($data['address'] ?? $data['customer_address'] ?? null);
        $notes = $this->nullableString($data['notes'] ?? $data['customer_notes'] ?? null);

        $customFields = $data['custom_fields'] ?? null;
        if (is_array($customFields)) {
            $customFields = json_encode($customFields);
        }

        return [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'address' => $address,
            'notes' => $notes,
            'custom_fields' => is_string($customFields) && trim($customFields) !== '' ? $customFields : null,
        ];
    }

    private function findExistingCustomer(?string $email, ?string $firstName, ?string $lastName, ?int $preferredCustomerId): ?array
    {
        if ($email !== null) {
            $byEmail = $this->customers
                ->where('LOWER(TRIM(email))', $email)
                ->first();

            if (is_array($byEmail)) {
                return $byEmail;
            }
        }

        if ($preferredCustomerId !== null && $preferredCustomerId > 0) {
            $byId = $this->customers->find($preferredCustomerId);
            if (is_array($byId)) {
                return $byId;
            }
        }

        if ($email === null && $firstName !== null && $lastName !== null) {
            $byName = $this->customers
                ->where('LOWER(TRIM(first_name))', strtolower($firstName))
                ->where('LOWER(TRIM(last_name))', strtolower($lastName))
                ->first();

            if (is_array($byName)) {
                return $byName;
            }
        }

        return null;
    }

    private function buildInsertPayload(array $normalized): array
    {
        $insert = [];
        foreach ($normalized as $key => $value) {
            if ($value !== null) {
                $insert[$key] = $value;
            }
        }

        if (!isset($insert['first_name'])) {
            $insert['first_name'] = '';
        }

        // CustomerModel has useTimestamps=false. MySQL non-strict mode would store
        // 0000-00-00 00:00:00 if these are omitted. Always provide them on insert.
        $now = date('Y-m-d H:i:s');
        $insert['created_at'] ??= $now;
        $insert['updated_at'] ??= $now;

        return $insert;
    }

    private function buildUpdatePatch(array $existing, array $normalized): array
    {
        $patch = [];

        foreach ($normalized as $field => $newValue) {
            if ($newValue === null || $newValue === '') {
                continue;
            }

            $existingValue = $existing[$field] ?? null;
            if ((string) $existingValue !== (string) $newValue) {
                $patch[$field] = $newValue;
            }
        }

        return $patch;
    }

    private function normalizeEmail($email): ?string
    {
        if (!is_string($email)) {
            return null;
        }

        $email = strtolower(trim($email));
        return $email !== '' ? $email : null;
    }

    private function nullableString($value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        return $value !== '' ? $value : null;
    }

    private function logCustomerMutation(string $action, int $customerId, array $changedFields): void
    {
        helper('logging');

        log_structured('info', 'customer.upsert', [
            'action' => $action,
            'customer_id' => $customerId,
            'changed_fields' => array_values($changedFields),
        ]);

        $actorUserId = $this->resolveActorUserId();
        if ($actorUserId === null) {
            return;
        }

        try {
            $this->auditLogs->log($action, $actorUserId, 'customer', $customerId, null, [
                'changed_fields' => array_values($changedFields),
            ]);
        } catch (\Throwable $e) {
            log_structured('warning', 'customer.upsert_audit_failed', [
                'action' => $action,
                'customer_id' => $customerId,
                'actor_user_id' => $actorUserId,
                'exception_message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Insert a new customer record and log the creation.
     *
     * Use this for explicit admin creates where the payload has already been
     * validated and built by the caller.
     *
     * @throws RuntimeException when the insert fails
     */
    public function insertCustomer(array $data): int
    {
        $insertId = $this->customers->insert($data, true);

        if (!$insertId) {
            throw new RuntimeException('Unable to create customer record.');
        }

        $this->logCustomerMutation('customer_created', (int) $insertId, array_keys($data));

        return (int) $insertId;
    }

    /**
     * Update an existing customer by primary key and log the change.
     *
     * Use this for explicit admin updates where the payload has already been
     * validated and built by the caller.
     *
     * @throws RuntimeException when the update fails
     */
    public function updateCustomerById(int $id, array $data): void
    {
        $ok = $this->customers->update($id, $data);

        if ($ok === false) {
            throw new RuntimeException('Unable to update customer record.');
        }

        $this->logCustomerMutation('customer_updated', $id, array_keys($data));
    }

    private function resolveActorUserId(): ?int
    {
        try {
            if (function_exists('session')) {
                $userId = session()->get('user_id');
                if (is_numeric($userId) && (int) $userId > 0) {
                    return (int) $userId;
                }
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }
}