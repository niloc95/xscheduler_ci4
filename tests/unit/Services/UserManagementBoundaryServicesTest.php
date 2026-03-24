<?php

namespace Tests\Unit\Services;

use App\Models\AuditLogModel;
use App\Models\BusinessHourModel;
use App\Models\ProviderScheduleModel;
use App\Models\ProviderStaffModel;
use App\Models\UserModel;
use App\Models\UserPermissionModel;
use App\Services\LocalizationSettingsService;
use App\Services\ScheduleValidationService;
use App\Services\UserManagementContextService;
use App\Services\UserManagementMutationService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class UserManagementBoundaryServicesTest extends CIUnitTestCase
{
    public function testUserManagementContextServiceReturnsAvailableRolesFromPermissions(): void
    {
        $permissionModel = $this->createMock(UserPermissionModel::class);
        $permissionModel->method('hasPermission')->willReturnCallback(
            static fn (int $userId, string $permission): bool => in_array($permission, ['create_admin', 'create_staff'], true)
        );

        $service = new UserManagementContextService(
            $this->createMock(UserModel::class),
            $permissionModel,
            $this->createMock(ProviderStaffModel::class),
            $this->createMock(ProviderScheduleModel::class),
            $this->createMock(LocalizationSettingsService::class),
            $this->createMock(ScheduleValidationService::class)
        );

        $roles = $service->getAvailableRolesForUser(9);

        $this->assertSame(['admin', 'staff'], $roles);
        $this->assertTrue($service->canCreateRole(9, 'admin'));
        $this->assertFalse($service->canCreateRole(9, 'provider'));
    }

    public function testUserManagementContextServiceBuildsValidationRules(): void
    {
        $service = new UserManagementContextService(
            $this->createMock(UserModel::class),
            $this->createMock(UserPermissionModel::class),
            $this->createMock(ProviderStaffModel::class),
            $this->createMock(ProviderScheduleModel::class),
            $this->createMock(LocalizationSettingsService::class),
            $this->createMock(ScheduleValidationService::class)
        );

        $storeRules = $service->getStoreValidationRules();
        $updateRules = $service->getUpdateValidationRules(17, true, false);

        $this->assertSame('required|valid_email|is_unique[xs_users.email]', $storeRules['email']);
        $this->assertArrayHasKey('password', $updateRules);
        $this->assertArrayNotHasKey('role', $updateRules);
        $this->assertSame('required|valid_email|is_unique[xs_users.email,id,17]', $updateRules['email']);
    }

    public function testUserManagementContextServiceResolveManageableUserReturnsNotFoundAndForbiddenShapes(): void
    {
        $userModel = $this->createMock(UserModel::class);
        $userModel->expects($this->exactly(2))
            ->method('find')
            ->willReturnCallback(static function (int $userId): ?array {
                return match ($userId) {
                    11 => null,
                    12 => ['id' => 12, 'role' => 'staff'],
                    default => null,
                };
            });
        $userModel->expects($this->once())
            ->method('canManageUser')
            ->with(5, 12)
            ->willReturn(false);

        $service = new UserManagementContextService(
            $userModel,
            $this->createMock(UserPermissionModel::class),
            $this->createMock(ProviderStaffModel::class),
            $this->createMock(ProviderScheduleModel::class),
            $this->createMock(LocalizationSettingsService::class),
            $this->createMock(ScheduleValidationService::class)
        );

        $missing = $service->resolveManageableUser(5, 11);
        $forbidden = $service->resolveManageableUser(5, 12);

        $this->assertFalse($missing['success']);
        $this->assertSame(404, $missing['statusCode']);
        $this->assertFalse($forbidden['success']);
        $this->assertSame(403, $forbidden['statusCode']);
    }

    public function testUserManagementMutationServiceRejectsCreateWhenRolePermissionMissing(): void
    {
        $contextService = $this->createMock(UserManagementContextService::class);
        $contextService->expects($this->once())
            ->method('canCreateRole')
            ->with(7, 'admin')
            ->willReturn(false);

        $service = new UserManagementMutationService(
            $this->createMock(UserModel::class),
            $this->createMock(ProviderStaffModel::class),
            $this->createMock(ProviderScheduleModel::class),
            $this->createMock(AuditLogModel::class),
            $this->createMock(ScheduleValidationService::class),
            $contextService,
            $this->createMock(BusinessHourModel::class)
        );

        $result = $service->createUser(7, ['role' => 'provider'], ['role' => 'admin']);

        $this->assertFalse($result['success']);
        $this->assertSame(403, $result['statusCode']);
    }

    public function testUserManagementMutationServiceDeactivateGuardsAgainstSelfDeactivation(): void
    {
        $service = new UserManagementMutationService(
            $this->createMock(UserModel::class),
            $this->createMock(ProviderStaffModel::class),
            $this->createMock(ProviderScheduleModel::class),
            $this->createMock(AuditLogModel::class),
            $this->createMock(ScheduleValidationService::class),
            $this->createMock(UserManagementContextService::class),
            $this->createMock(BusinessHourModel::class)
        );

        $result = $service->deactivateUser(22, 22);

        $this->assertFalse($result['success']);
        $this->assertSame(400, $result['statusCode']);
        $this->assertSame('You cannot deactivate your own account.', $result['message']);
    }

    public function testUserManagementMutationServiceActivateChecksManageabilityBeforeUpdating(): void
    {
        $userModel = $this->createMock(UserModel::class);
        $userModel->expects($this->once())
            ->method('find')
            ->with(33)
            ->willReturn(['id' => 33, 'role' => 'staff']);
        $userModel->expects($this->once())
            ->method('canManageUser')
            ->with(4, 33)
            ->willReturn(false);
        $userModel->expects($this->never())
            ->method('update');

        $service = new UserManagementMutationService(
            $userModel,
            $this->createMock(ProviderStaffModel::class),
            $this->createMock(ProviderScheduleModel::class),
            $this->createMock(AuditLogModel::class),
            $this->createMock(ScheduleValidationService::class),
            $this->createMock(UserManagementContextService::class),
            $this->createMock(BusinessHourModel::class)
        );

        $result = $service->activateUser(4, 33);

        $this->assertFalse($result['success']);
        $this->assertSame(403, $result['statusCode']);
    }

    public function testUserManagementMutationServiceActivateReturnsSuccessShape(): void
    {
        $userModel = $this->createMock(UserModel::class);
        $userModel->expects($this->once())
            ->method('find')
            ->with(33)
            ->willReturn(['id' => 33, 'role' => 'staff']);
        $userModel->expects($this->once())
            ->method('canManageUser')
            ->with(4, 33)
            ->willReturn(true);
        $userModel->expects($this->once())
            ->method('update')
            ->with(33, ['is_active' => true])
            ->willReturn(true);

        $auditModel = $this->createMock(AuditLogModel::class);
        $auditModel->expects($this->once())
            ->method('log')
            ->with(
                'user_activated',
                4,
                'user',
                33,
                null,
                ['role' => 'staff']
            );

        $service = new UserManagementMutationService(
            $userModel,
            $this->createMock(ProviderStaffModel::class),
            $this->createMock(ProviderScheduleModel::class),
            $auditModel,
            $this->createMock(ScheduleValidationService::class),
            $this->createMock(UserManagementContextService::class),
            $this->createMock(BusinessHourModel::class)
        );

        $result = $service->activateUser(4, 33);

        $this->assertTrue($result['success']);
        $this->assertSame('User activated successfully.', $result['message']);
        $this->assertStringContainsString('/user-management', $result['redirect']);
    }

    public function testUserManagementMutationServiceCreateProviderSyncsBusinessHoursFromSchedule(): void
    {
        $contextService = $this->createMock(UserManagementContextService::class);
        $contextService->expects($this->once())
            ->method('canCreateRole')
            ->with(7, 'provider')
            ->willReturn(true);

        $schedule = [
            'monday' => [
                'is_active' => 1,
                'start_time' => '08:00:00',
                'end_time' => '16:30:00',
                'break_start' => null,
                'break_end' => null,
            ],
        ];

        $userModel = $this->createMock(UserModel::class);
        $userModel->expects($this->once())
            ->method('getAvailableProviderColor')
            ->willReturn('#123456');
        $userModel->expects($this->once())
            ->method('createUser')
            ->willReturn(44);

        $scheduleValidation = $this->createMock(ScheduleValidationService::class);
        $scheduleValidation->expects($this->once())
            ->method('validateProviderSchedule')
            ->with($schedule)
            ->willReturn([$schedule, []]);

        $providerScheduleModel = $this->createMock(ProviderScheduleModel::class);
        $providerScheduleModel->expects($this->once())
            ->method('saveSchedule')
            ->with(44, $schedule)
            ->willReturn(true);

        $businessHourModel = $this->createMock(BusinessHourModel::class);
        $businessHourModel->expects($this->once())
            ->method('syncFromProviderSchedule')
            ->with(44, $schedule)
            ->willReturn(true);

        $auditModel = $this->createMock(AuditLogModel::class);
        $auditModel->expects($this->once())
            ->method('log')
            ->with(
                'user_created',
                7,
                'user',
                44,
                null,
                ['role' => 'provider', 'email' => 'provider@example.com']
            );

        $service = new UserManagementMutationService(
            $userModel,
            $this->createMock(ProviderStaffModel::class),
            $providerScheduleModel,
            $auditModel,
            $scheduleValidation,
            $contextService,
            $businessHourModel
        );

        $result = $service->createUser(7, ['role' => 'admin'], [
            'role' => 'provider',
            'name' => 'Provider Example',
            'email' => 'provider@example.com',
            'schedule' => $schedule,
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame(44, $result['userId']);
    }
}