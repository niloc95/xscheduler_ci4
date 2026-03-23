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
            'typedConfirmationRequired' => ($targetUser['role'] ?? '') === 'provider',
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
        $db = $this->userModel->db;

        $db->transStart();

        if ($role === 'provider') {
            $this->cancelProviderUpcomingAppointments($userId, $currentUserId);

            $db->table($db->prefixTable('providers_services'))->where('provider_id', $userId)->delete();
            $db->table($db->prefixTable('provider_staff_assignments'))->where('provider_id', $userId)->delete();
            $db->table($db->prefixTable('provider_schedules'))->where('provider_id', $userId)->delete();

            if ($this->tableExists('receptionist_providers')) {
                $db->table($db->prefixTable('receptionist_providers'))->where('provider_id', $userId)->delete();
            }

            if ($this->tableExists('locations')) {
                $db->table($db->prefixTable('locations'))->where('provider_id', $userId)->delete();
            }

            $this->cleanupProviderNotificationLinks($userId);
        } elseif ($role === 'staff') {
            $db->table($db->prefixTable('provider_staff_assignments'))->where('staff_id', $userId)->delete();

            if ($this->tableExists('receptionist_providers')) {
                $db->table($db->prefixTable('receptionist_providers'))->where('receptionist_id', $userId)->delete();
            }
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

        $impact = [
            'role' => $role,
            'adminCount' => (int) $this->userModel->where('role', 'admin')->where('is_active', 1)->countAllResults(),
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

        if ($role === 'provider') {
            $impact['servicesLinked'] = (int) $db->table($db->prefixTable('providers_services'))->where('provider_id', $userId)->countAllResults();
            $impact['staffLinked'] = (int) $db->table($db->prefixTable('provider_staff_assignments'))->where('provider_id', $userId)->countAllResults();

            if ($this->tableExists('locations')) {
                $impact['locations'] = (int) $db->table($db->prefixTable('locations'))->where('provider_id', $userId)->countAllResults();
            }

            $appointmentsTable = $db->prefixTable('appointments');
            $nowUtc = gmdate('Y-m-d H:i:s');
            $impact['appointmentsTotal'] = (int) $db->table($appointmentsTable)->where('provider_id', $userId)->countAllResults();
            $impact['appointmentsUpcoming'] = (int) $db->table($appointmentsTable)
                ->where('provider_id', $userId)
                ->where('start_at >=', $nowUtc)
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
        } elseif ($role === 'staff') {
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
        } elseif ($role === 'customer') {
            $impact['upcomingCustomerAppointments'] = (int) $db->table($db->prefixTable('appointments'))
                ->where('customer_id', $userId)
                ->where('start_at >=', gmdate('Y-m-d H:i:s'))
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

        if (($targetUser['role'] ?? '') === 'admin' && ((int) ($impact['adminCount'] ?? 0)) <= 1) {
            return 'LAST_ADMIN';
        }

        return null;
    }

    private function cancelProviderUpcomingAppointments(int $providerId, int $actorId): void
    {
        $appointmentModel = new AppointmentModel();
        $nowUtc = gmdate('Y-m-d H:i:s');

        $appointments = $appointmentModel
            ->where('provider_id', $providerId)
            ->where('start_at >=', $nowUtc)
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

    private function tableExists(string $table): bool
    {
        $db = $this->userModel->db;
        return $db->tableExists($db->prefixTable($table)) || $db->tableExists($table);
    }
}