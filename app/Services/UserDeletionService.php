<?php

namespace App\Services;

use App\Models\AppointmentModel;
use App\Models\AuditLogModel;
use App\Models\UserModel;

class UserDeletionService
{
    private UserModel $userModel;
    private AuditLogModel $auditModel;

    public function __construct(?UserModel $userModel = null, ?AuditLogModel $auditModel = null)
    {
        $this->userModel = $userModel ?? new UserModel();
        $this->auditModel = $auditModel ?? new AuditLogModel();
    }

    public function buildPreview(int $currentUserId, array $targetUser): array
    {
        $impact = $this->buildDeleteImpact($targetUser);
        $blockCode = $this->getDeleteBlockCode($currentUserId, $targetUser, $impact);

        return [
            'allowed' => $blockCode === null,
            'blockCode' => $blockCode,
            // §4.4: use authoritative roles for confirmation gate
            'typedConfirmationRequired' => in_array('provider', $this->getUserRoles($targetUser)),
            'target' => [
                'id' => (int) ($targetUser['id'] ?? 0),
                'name' => $targetUser['name'] ?? 'Unknown',
                'email' => $targetUser['email'] ?? null,
                'role' => $targetUser['role'] ?? null,
            ],
            'impact' => $impact,
        ];
    }

    public function buildPreviewForUserId(int $currentUserId, int $targetUserId): array
    {
        $targetUser = $this->userModel->find($targetUserId);
        if (!$targetUser) {
            return [
                'success' => false,
                'statusCode' => 404,
                'message' => 'User not found.',
            ];
        }

        return array_merge([
            'success' => true,
        ], $this->buildPreview($currentUserId, $targetUser));
    }

    public function deleteUser(int $currentUserId, array $targetUser): array
    {
        $impact = $this->buildDeleteImpact($targetUser);
        $blockCode = $this->getDeleteBlockCode($currentUserId, $targetUser, $impact);

        if ($blockCode !== null) {
            return [
                'success' => false,
                'blockCode' => $blockCode,
                'impact' => $impact,
                'message' => $blockCode === 'LAST_ADMIN'
                    ? 'Cannot delete the last active administrator. Promote another admin first.'
                    : 'You cannot delete this user.',
            ];
        }

        $userId = (int) ($targetUser['id'] ?? 0);
        $role = $targetUser['role'] ?? '';
        // §4.4: use authoritative roles to handle multi-role users correctly
        $roles = $this->getUserRoles($targetUser);
        $db = $this->userModel->db;

        $db->transStart();

        // Use separate if blocks (not elseif) so multi-role users get full cleanup
        if (in_array('provider', $roles)) {
            $this->cancelProviderUpcomingAppointments($userId, $currentUserId);

            $db->table($db->prefixTable('providers_services'))->where('provider_id', $userId)->delete();
            $db->table($db->prefixTable('provider_staff_assignments'))->where('provider_id', $userId)->delete();
            $db->table($db->prefixTable('provider_schedules'))->where('provider_id', $userId)->delete();

            if ($this->tableExists('locations')) {
                $db->table($db->prefixTable('locations'))->where('provider_id', $userId)->delete();
            }

            $this->cleanupProviderNotificationLinks($userId);
        }
        if (in_array('staff', $roles)) {
            $db->table($db->prefixTable('provider_staff_assignments'))->where('staff_id', $userId)->delete();
        }

        $deleted = $this->userModel->delete($userId);
        $db->transComplete();

        if (!$deleted || !$db->transStatus()) {
            return [
                'success' => false,
                'impact' => $impact,
                'message' => 'Failed to delete user. Please try again.',
            ];
        }

        $this->auditModel->log(
            'user_deleted',
            $currentUserId,
            'user',
            $userId,
            null,
            [
                'name' => $targetUser['name'] ?? null,
                'role' => $role,
                'impact' => $impact,
            ]
        );

        return [
            'success' => true,
            'impact' => $impact,
            'message' => 'User "' . ($targetUser['name'] ?? 'Unknown') . '" deleted successfully.',
        ];
    }

    public function deleteUserById(int $currentUserId, int $targetUserId): array
    {
        $targetUser = $this->userModel->find($targetUserId);
        if (!$targetUser) {
            return [
                'success' => false,
                'statusCode' => 404,
                'message' => 'User not found.',
            ];
        }

        return $this->deleteUser($currentUserId, $targetUser);
    }

    private function buildDeleteImpact(array $targetUser): array
    {
        $db = $this->userModel->db;
        $userId = (int) ($targetUser['id'] ?? 0);
        $role = $targetUser['role'] ?? '';
        // §4.4: use authoritative roles
        $roles = $this->getUserRoles($targetUser);

        // Count active admins via xs_users.role (compatibility primary, kept in sync by migration).
        // xs_users.role is appropriate for bulk counting; per-user role decisions use xs_user_roles.
        $adminCountBuilder = $this->userModel->builder();
        $adminCountBuilder->where('role', 'admin');

        if ($this->hasUsersColumn('is_active')) {
            $adminCountBuilder->where('is_active', 1);
        } elseif ($this->hasUsersColumn('status')) {
            $adminCountBuilder->where('status', 'active');
        }

        $impact = [
            'role' => $role,
            'adminCount' => (int) $adminCountBuilder->countAllResults(),
            'servicesLinked' => 0,
            'staffLinked' => 0,
            'appointmentsTotal' => 0,
            'appointmentsUpcoming' => 0,
            'appointmentsPast' => 0,
            'locations' => 0,
            'notificationsQueued' => 0,
            'notificationsDelivered' => 0,
            'providerLinks' => 0,
            'providerNames' => [],
            'upcomingCustomerAppointments' => 0,
        ];

        // Use in_array checks (not elseif) to handle multi-role users correctly
        if (in_array('provider', $roles)) {
            $impact['servicesLinked'] = (int) $db->table($db->prefixTable('providers_services'))->where('provider_id', $userId)->countAllResults();
            $impact['staffLinked'] = (int) $db->table($db->prefixTable('provider_staff_assignments'))->where('provider_id', $userId)->countAllResults();

            if ($this->tableExists('locations')) {
                $impact['locations'] = (int) $db->table($db->prefixTable('locations'))->where('provider_id', $userId)->countAllResults();
            }

            $appointmentsTable = $db->prefixTable('appointments');
            $appointmentStartColumn = $this->resolveAppointmentStartColumn();
            $nowUtc = gmdate('Y-m-d H:i:s');
            $impact['appointmentsTotal'] = (int) $db->table($appointmentsTable)->where('provider_id', $userId)->countAllResults();
            $impact['appointmentsUpcoming'] = (int) $db->table($appointmentsTable)
                ->where('provider_id', $userId)
                ->where($appointmentStartColumn . ' >=', $nowUtc)
                ->countAllResults();
            $impact['appointmentsPast'] = max(0, $impact['appointmentsTotal'] - $impact['appointmentsUpcoming']);

            if ($this->tableExists('notification_queue')) {
                $impact['notificationsQueued'] = (int) $db->table($db->prefixTable('notification_queue') . ' nq')
                    ->join($appointmentsTable . ' a', 'a.id = nq.appointment_id', 'inner')
                    ->where('a.provider_id', $userId)
                    ->countAllResults();
            }

            if ($this->tableExists('notification_delivery_logs')) {
                $impact['notificationsDelivered'] = (int) $db->table($db->prefixTable('notification_delivery_logs') . ' ndl')
                    ->join($appointmentsTable . ' a', 'a.id = ndl.appointment_id', 'inner')
                    ->where('a.provider_id', $userId)
                    ->countAllResults();
            }
        }
        if (in_array('staff', $roles)) {
            $assignments = $db->table($db->prefixTable('provider_staff_assignments') . ' psa')
                ->select('p.name')
                ->join($db->prefixTable('users') . ' p', 'p.id = psa.provider_id', 'left')
                ->where('psa.staff_id', $userId)
                ->get()
                ->getResultArray();

            $impact['providerLinks'] = count($assignments);
            $impact['providerNames'] = array_values(array_filter(array_map(static function ($row) {
                return $row['name'] ?? null;
            }, $assignments)));
        }
        if ($role === 'customer') {
            $appointmentStartColumn = $this->resolveAppointmentStartColumn();
            $impact['upcomingCustomerAppointments'] = (int) $db->table($db->prefixTable('appointments'))
                ->where('customer_id', $userId)
                ->where($appointmentStartColumn . ' >=', gmdate('Y-m-d H:i:s'))
                ->countAllResults();
        }

        return $impact;
    }

    private function getDeleteBlockCode(int $currentUserId, array $targetUser, array $impact): ?string
    {
        $targetUserId = (int) ($targetUser['id'] ?? 0);
        if ($currentUserId === $targetUserId) {
            return 'SELF_DELETE';
        }

        // §4.4: use authoritative roles for last-admin guard
        $targetRoles = $this->getUserRoles($targetUser);
        if (in_array('admin', $targetRoles) && ((int) ($impact['adminCount'] ?? 0)) <= 1) {
            return 'LAST_ADMIN';
        }

        return null;
    }

    /**
     * Get authoritative roles for a user via xs_user_roles.
     * Falls back to xs_users.role for compatibility (§4.4 Canonical RBAC Pattern).
     */
    private function getUserRoles(array $targetUser): array
    {
        $userId = (int) ($targetUser['id'] ?? 0);
        if ($userId > 0) {
            return $this->userModel->getRolesForUser($userId);
        }
        $role = $targetUser['role'] ?? '';
        return $role ? [$role] : [];
    }

    private function cancelProviderUpcomingAppointments(int $providerId, int $actorId): void
    {
        $appointmentModel = new AppointmentModel();
        $nowUtc = gmdate('Y-m-d H:i:s');
        $appointmentStartColumn = $this->resolveAppointmentStartColumn();

        $appointments = $appointmentModel
            ->where('provider_id', $providerId)
            ->where($appointmentStartColumn . ' >=', $nowUtc)
            ->whereNotIn('status', ['cancelled', 'completed', 'no-show'])
            ->findAll();

        foreach ($appointments as $appointment) {
            $existingNotes = trim((string) ($appointment['notes'] ?? ''));
            $deletionNote = '[system] Appointment cancelled due to provider account deletion.';
            $newNotes = $existingNotes !== '' ? ($existingNotes . PHP_EOL . $deletionNote) : $deletionNote;

            $appointmentModel->update((int) $appointment['id'], [
                'status' => 'cancelled',
                'notes' => $newNotes,
            ]);
        }

        if (!empty($appointments)) {
            $this->auditModel->log(
                'provider_delete_cancelled_appointments',
                $actorId,
                'user',
                $providerId,
                null,
                ['count' => count($appointments)]
            );
        }
    }

    private function cleanupProviderNotificationLinks(int $providerId): void
    {
        $db = $this->userModel->db;
        $appointmentIds = $db->table($db->prefixTable('appointments'))
            ->select('id')
            ->where('provider_id', $providerId)
            ->get()
            ->getResultArray();

        $appointmentIds = array_values(array_map(static function ($row) {
            return (int) ($row['id'] ?? 0);
        }, $appointmentIds));
        $appointmentIds = array_values(array_filter($appointmentIds));

        if (empty($appointmentIds)) {
            return;
        }

        if ($this->tableExists('notification_queue')) {
            $db->table($db->prefixTable('notification_queue'))->whereIn('appointment_id', $appointmentIds)->delete();
        }

        if ($this->tableExists('notification_delivery_logs')) {
            $db->table($db->prefixTable('notification_delivery_logs'))->whereIn('appointment_id', $appointmentIds)->delete();
        }
    }

    private function hasUsersColumn(string $column): bool
    {
        $db = $this->userModel->db;

        if (!method_exists($db, 'fieldExists')) {
            return true;
        }

        return $db->fieldExists($column, $db->prefixTable('users')) || $db->fieldExists($column, 'users');
    }

    private function resolveAppointmentStartColumn(): string
    {
        if ($this->hasAppointmentsColumn('start_at')) {
            return 'start_at';
        }

        return 'start_time';
    }

    private function hasAppointmentsColumn(string $column): bool
    {
        $db = $this->userModel->db;

        if (!method_exists($db, 'fieldExists')) {
            return true;
        }

        return $db->fieldExists($column, $db->prefixTable('appointments')) || $db->fieldExists($column, 'appointments');
    }

    private function tableExists(string $table): bool
    {
        $db = $this->userModel->db;
        return $db->tableExists($db->prefixTable($table)) || $db->tableExists($table);
    }
}