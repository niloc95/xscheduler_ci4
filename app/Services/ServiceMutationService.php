<?php

namespace App\Services;

use App\Models\AuditLogModel;
use App\Models\CategoryModel;
use App\Models\ServiceModel;
use RuntimeException;

/**
 * Owns all write operations for the service catalog and service categories.
 *
 * Controllers build and validate the payload, then delegate to this service
 * for model persistence, provider assignment, and audit logging.
 */
class ServiceMutationService
{
    private ServiceModel $services;
    private CategoryModel $categories;
    private AuditLogModel $auditLogs;

    public function __construct(
        ?ServiceModel $services = null,
        ?CategoryModel $categories = null,
        ?AuditLogModel $auditLogs = null,
    ) {
        $this->services   = $services   ?? new ServiceModel();
        $this->categories = $categories ?? new CategoryModel();
        $this->auditLogs  = $auditLogs  ?? new AuditLogModel();
    }

    // -------------------------------------------------------------------------
    // Services
    // -------------------------------------------------------------------------

    /**
     * Insert a new service, assign providers, and log the creation.
     *
     * @param  array $data        Already-validated service payload.
     * @param  array $providerIds Provider IDs to link.
     * @return int                New service ID.
     * @throws RuntimeException   On model failure.
     */
    public function createService(array $data, array $providerIds): int
    {
        if (!$this->services->insert($data)) {
            $errors = $this->services->errors();
            throw new RuntimeException('Service validation failed: ' . implode(', ', $errors));
        }

        $serviceId = (int) $this->services->getInsertID();

        $this->assignProviders($serviceId, $providerIds);
        $this->auditService('service_created', $serviceId, array_keys($data), count($providerIds));

        return $serviceId;
    }

    /**
     * Update an existing service, sync providers, and log the change.
     *
     * @throws RuntimeException On model failure or service not found.
     */
    public function updateService(int $id, array $data, array $providerIds): void
    {
        if (!$this->services->update($id, $data)) {
            $errors = $this->services->errors();
            throw new RuntimeException('Service validation failed: ' . implode(', ', $errors));
        }

        $this->assignProviders($id, $providerIds);
        $this->auditService('service_updated', $id, array_keys($data), count($providerIds));
    }

    /**
     * Remove provider links then delete the service, and log the deletion.
     */
    public function deleteService(int $id): void
    {
        $pivotTable = $this->services->db->prefixTable('providers_services');
        $this->services->db->table($pivotTable)->delete(['service_id' => $id]);
        $this->services->delete($id);

        $this->auditService('service_deleted', $id, [], 0);
    }

    // -------------------------------------------------------------------------
    // Categories
    // -------------------------------------------------------------------------

    /**
     * Insert a new category and log the creation.
     *
     * @throws RuntimeException On model failure.
     */
    public function createCategory(array $data): int
    {
        $id = $this->categories->insert($data, true);

        if (!$id) {
            $errors = $this->categories->errors();
            throw new RuntimeException('Category validation failed: ' . implode(', ', $errors));
        }

        $this->auditCategory('service_category_created', (int) $id, array_keys($data));

        return (int) $id;
    }

    /**
     * Update a category and log the change.
     *
     * @throws RuntimeException On model failure.
     */
    public function updateCategory(int $id, array $data): void
    {
        if (!$this->categories->update($id, $data)) {
            $errors = $this->categories->errors();
            throw new RuntimeException('Category validation failed: ' . implode(', ', $errors));
        }

        $this->auditCategory('service_category_updated', $id, array_keys($data));
    }

    /**
     * Detach all services from the category, delete it, and log the deletion.
     *
     * @throws RuntimeException On model failure.
     */
    public function deleteCategory(int $id): void
    {
        $this->services->where('category_id', $id)->set('category_id', null)->update();

        if (!$this->categories->delete($id)) {
            $errors = $this->categories->errors();
            throw new RuntimeException('Unable to delete category: ' . implode(', ', $errors));
        }

        $this->auditCategory('service_category_deleted', $id, []);
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    private function assignProviders(int $serviceId, array $providerIds): void
    {
        $db = $this->services->db;
        $db->transStart();
        $this->services->setProviders($serviceId, $providerIds);
        $db->transComplete();

        if (!$db->transStatus()) {
            log_message('error', "ServiceMutationService — setProviders failed for service #{$serviceId}.");
        }
    }

    private function auditService(string $action, int $serviceId, array $fields, int $providerCount): void
    {
        $actorId = $this->resolveActorUserId();
        if ($actorId === null) {
            return;
        }

        try {
            $this->auditLogs->log($action, $actorId, 'service', $serviceId, null, [
                'changed_fields'   => $fields,
                'provider_count'   => $providerCount,
            ]);
        } catch (\Throwable $e) {
            log_message('warning', "ServiceMutationService — audit log failed for {$action} service #{$serviceId}: " . $e->getMessage());
        }
    }

    private function auditCategory(string $action, int $categoryId, array $fields): void
    {
        $actorId = $this->resolveActorUserId();
        if ($actorId === null) {
            return;
        }

        try {
            $this->auditLogs->log($action, $actorId, 'service_category', $categoryId, null, [
                'changed_fields' => $fields,
            ]);
        } catch (\Throwable $e) {
            log_message('warning', "ServiceMutationService — audit log failed for {$action} category #{$categoryId}: " . $e->getMessage());
        }
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
        } catch (\Throwable) {
            return null;
        }

        return null;
    }
}
