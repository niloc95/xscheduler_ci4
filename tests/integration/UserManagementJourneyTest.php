<?php

namespace App\Tests\Integration;

use CodeIgniter\Database\Config as DatabaseConfig;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Controller-level regression coverage for the user-management mutation journey.
 */
final class UserManagementJourneyTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected $namespace = 'App';

    private int $adminId;
    private ?int $guardAdminId = null;
    private ?int $managedUserId = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureTestingDatabaseEnvironment();
        $this->ensureSetupFlag();
        $this->seedAdminUser();
    }

    protected function tearDown(): void
    {
        $db = \Config\Database::connect('tests');

        if ($this->managedUserId !== null) {
            $db->table('provider_staff_assignments')->where('provider_id', $this->managedUserId)->orWhere('staff_id', $this->managedUserId)->delete();
            $db->table('provider_schedules')->where('provider_id', $this->managedUserId)->delete();
            $db->table('audit_logs')->where('target_id', $this->managedUserId)->delete();
            $db->table('users')->where('id', $this->managedUserId)->delete();
        }

        if ($this->guardAdminId !== null) {
            $db->table('audit_logs')->where('user_id', $this->guardAdminId)->delete();
            $db->table('users')->where('id', $this->guardAdminId)->delete();
        }

        if (isset($this->adminId)) {
            $db->table('audit_logs')->where('user_id', $this->adminId)->delete();
            $db->table('users')->where('id', $this->adminId)->delete();
        }

        parent::tearDown();
    }

    public function testAdminCanCreateUpdateToggleAndDeleteProviderViaAjaxEndpoints(): void
    {
        $this->primeCsrfCookie();

        $create = $this->withSession($this->adminSession())
            ->withHeaders($this->ajaxHeaders())
            ->post('/user-management/store', [
                $this->csrfTokenName() => $this->csrfToken(),
                'name' => 'Journey Provider',
                'email' => 'journey-provider-' . uniqid('', true) . '@example.com',
                'phone' => '+15550101010',
                'role' => 'provider',
                'password' => 'password123',
                'password_confirm' => 'password123',
                'color' => '#118833',
                'schedule' => [
                    'monday' => [
                        'is_active' => '1',
                        'start_time' => '09:00',
                        'end_time' => '17:00',
                        'break_start' => '12:00',
                        'break_end' => '13:00',
                    ],
                ],
            ]);

        $create->assertOK();

        $createPayload = json_decode($create->getJSON(), true);
        $this->managedUserId = (int) ($createPayload['userId'] ?? 0);

        $this->assertGreaterThan(0, $this->managedUserId);
        $this->assertTrue((bool) ($createPayload['success'] ?? false));
        $this->assertSame('User created successfully. You can now manage assignments and schedules.', $createPayload['message'] ?? null);
        $this->assertStringContainsString('/user-management/edit/' . $this->managedUserId, $createPayload['redirect'] ?? '');

        $db = \Config\Database::connect('tests');
        $createdUser = $db->table('users')->where('id', $this->managedUserId)->get()->getRowArray();
        $this->assertNotNull($createdUser);
        $this->assertSame('provider', $createdUser['role'] ?? null);
        $this->assertSame('Journey Provider', $createdUser['name'] ?? null);
        $this->assertSame('#118833', $createdUser['color'] ?? null);
        $this->assertUserActiveState($createdUser, true);

        $scheduleRows = $db->table('provider_schedules')
            ->where('provider_id', $this->managedUserId)
            ->orderBy('day_of_week', 'ASC')
            ->get()
            ->getResultArray();

        $this->assertCount(1, $scheduleRows);
        $this->assertSame('monday', $scheduleRows[0]['day_of_week'] ?? null);
        $this->assertSame('09:00:00', $scheduleRows[0]['start_time'] ?? null);
        $this->assertSame('17:00:00', $scheduleRows[0]['end_time'] ?? null);
        $this->assertSame('12:00:00', $scheduleRows[0]['break_start'] ?? null);
        $this->assertSame('13:00:00', $scheduleRows[0]['break_end'] ?? null);

        $this->primeCsrfCookie();

        $update = $this->withSession($this->adminSession())
            ->withHeaders($this->ajaxHeaders())
            ->post('/user-management/update/' . $this->managedUserId, [
                $this->csrfTokenName() => $this->csrfToken(),
                'name' => 'Journey Provider Updated',
                'email' => $createdUser['email'],
                'phone' => '+15550999999',
                'role' => 'provider',
                'is_active' => '1',
                'color' => '#2255AA',
                'schedule' => [
                    'monday' => [
                        'is_active' => '0',
                    ],
                    'tuesday' => [
                        'is_active' => '1',
                        'start_time' => '10:00',
                        'end_time' => '18:00',
                        'break_start' => '14:00',
                        'break_end' => '14:30',
                    ],
                ],
            ]);

        $update->assertOK();

        $updatePayload = json_decode($update->getJSON(), true);
        $this->assertTrue((bool) ($updatePayload['success'] ?? false));
        $this->assertSame('User updated successfully.', $updatePayload['message'] ?? null);
        $this->assertStringContainsString('/user-management/edit/' . $this->managedUserId, $updatePayload['redirect'] ?? '');

        $updatedUser = $db->table('users')->where('id', $this->managedUserId)->get()->getRowArray();
        $this->assertSame('Journey Provider Updated', $updatedUser['name'] ?? null);
        $this->assertSame('+15550999999', $updatedUser['phone'] ?? null);
        $this->assertSame('#2255AA', $updatedUser['color'] ?? null);

        $updatedScheduleRows = $db->table('provider_schedules')
            ->where('provider_id', $this->managedUserId)
            ->orderBy('day_of_week', 'ASC')
            ->get()
            ->getResultArray();

        $this->assertCount(1, $updatedScheduleRows);
        $this->assertSame('tuesday', $updatedScheduleRows[0]['day_of_week'] ?? null);
        $this->assertSame('10:00:00', $updatedScheduleRows[0]['start_time'] ?? null);
        $this->assertSame('18:00:00', $updatedScheduleRows[0]['end_time'] ?? null);

        $this->primeCsrfCookie();

        $deactivate = $this->withSession($this->adminSession())
            ->withHeaders($this->ajaxHeaders())
            ->post('/user-management/deactivate/' . $this->managedUserId, [
                $this->csrfTokenName() => $this->csrfToken(),
            ]);

        $deactivate->assertOK();

        $deactivatePayload = json_decode($deactivate->getJSON(), true);
        $this->assertTrue((bool) ($deactivatePayload['success'] ?? false));
        $this->assertSame('User deactivated successfully.', $deactivatePayload['message'] ?? null);
        $this->assertStringContainsString('/user-management', $deactivatePayload['redirect'] ?? '');

        $deactivatedUser = $db->table('users')->where('id', $this->managedUserId)->get()->getRowArray();
        $this->assertUserActiveState($deactivatedUser, false);

        $this->primeCsrfCookie();

        $activate = $this->withSession($this->adminSession())
            ->withHeaders($this->ajaxHeaders())
            ->post('/user-management/activate/' . $this->managedUserId, [
                $this->csrfTokenName() => $this->csrfToken(),
            ]);

        $activate->assertOK();

        $activatePayload = json_decode($activate->getJSON(), true);
        $this->assertTrue((bool) ($activatePayload['success'] ?? false));
        $this->assertSame('User activated successfully.', $activatePayload['message'] ?? null);
        $this->assertStringContainsString('/user-management', $activatePayload['redirect'] ?? '');

        $reactivatedUser = $db->table('users')->where('id', $this->managedUserId)->get()->getRowArray();
        $this->assertUserActiveState($reactivatedUser, true);

        $preview = $this->withSession($this->adminSession())
            ->get('/user-management/delete-preview/' . $this->managedUserId);

        $preview->assertOK();

        $previewPayload = json_decode($preview->getJSON(), true);
        $this->assertTrue((bool) ($previewPayload['success'] ?? false));
        $this->assertTrue((bool) ($previewPayload['allowed'] ?? false));
        $this->assertTrue((bool) ($previewPayload['typedConfirmationRequired'] ?? false));
        $this->assertSame('provider', $previewPayload['target']['role'] ?? null);
        $this->assertSame('Journey Provider Updated', $previewPayload['target']['name'] ?? null);
        $this->assertSame(0, $previewPayload['impact']['appointmentsUpcoming'] ?? null);

        $this->primeCsrfCookie();

        $delete = $this->withSession($this->adminSession())
            ->withHeaders($this->ajaxHeaders())
            ->post('/user-management/delete/' . $this->managedUserId, [
                $this->csrfTokenName() => $this->csrfToken(),
            ]);

        $delete->assertOK();

        $deletePayload = json_decode($delete->getJSON(), true);
        $this->assertTrue((bool) ($deletePayload['success'] ?? false));
        $this->assertSame('User "Journey Provider Updated" deleted successfully.', $deletePayload['message'] ?? null);
        $this->assertStringContainsString('/user-management', $deletePayload['redirect'] ?? '');

        $this->assertNull($db->table('users')->where('id', $this->managedUserId)->get()->getRowArray());
        $this->assertSame([], $db->table('provider_schedules')->where('provider_id', $this->managedUserId)->get()->getResultArray());

        $this->managedUserId = null;
    }

    public function testAdminCannotDeleteOwnAccountViaPreviewOrDeleteEndpoints(): void
    {
        $preview = $this->withSession($this->adminSession())
            ->get('/user-management/delete-preview/' . $this->adminId);

        $preview->assertOK();

        $previewPayload = json_decode($preview->getJSON(), true);
        $this->assertTrue((bool) ($previewPayload['success'] ?? false));
        $this->assertFalse((bool) ($previewPayload['allowed'] ?? true));
        $this->assertSame('SELF_DELETE', $previewPayload['blockCode'] ?? null);
        $this->assertSame('admin', $previewPayload['target']['role'] ?? null);
        $this->assertSame($this->adminId, (int) ($previewPayload['target']['id'] ?? 0));

        $this->primeCsrfCookie();

        $delete = $this->withSession($this->adminSession())
            ->withHeaders($this->ajaxHeaders())
            ->post('/user-management/delete/' . $this->adminId, [
                $this->csrfTokenName() => $this->csrfToken(),
            ]);

        $delete->assertStatus(422);

        $deletePayload = json_decode($delete->getJSON(), true);
        $this->assertFalse((bool) ($deletePayload['success'] ?? true));
        $this->assertSame('You cannot delete this user.', $deletePayload['message'] ?? null);
        $this->assertSame('SELF_DELETE', $deletePayload['blockCode'] ?? null);

        $db = \Config\Database::connect('tests');
        $admin = $db->table('users')->where('id', $this->adminId)->get()->getRowArray();
        $this->assertNotNull($admin);
        $this->assertSame('admin', $admin['role'] ?? null);
        $this->assertUserActiveState($admin, true);
    }

    public function testAdminSessionCannotDeleteLastActiveAdminViaPreviewOrDeleteEndpoints(): void
    {
        $db = \Config\Database::connect('tests');
        $now = date('Y-m-d H:i:s');
        $otherActiveAdminsBuilder = $db->table('users')
            ->select('id')
            ->where('role', 'admin')
            ->where('id !=', $this->adminId);

        if ($db->fieldExists('is_active', 'users')) {
            $otherActiveAdminsBuilder->where('is_active', 1);
        } elseif ($db->fieldExists('status', 'users')) {
            $otherActiveAdminsBuilder->where('status', 'active');
        }

        $otherActiveAdmins = $otherActiveAdminsBuilder
            ->get()
            ->getResultArray();
        $otherActiveAdminIds = array_values(array_map(static fn(array $row): int => (int) ($row['id'] ?? 0), $otherActiveAdmins));

        if ($otherActiveAdminIds !== []) {
            $db->table('users')->whereIn('id', $otherActiveAdminIds)->update([
                'updated_at' => $now,
            ] + $this->activeUserColumns($db, false));
        }

        $db->table('users')->insert([
            'name' => 'Guard Admin',
            'email' => 'guard-admin-' . uniqid('', true) . '@example.com',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'admin',
            'created_at' => $now,
            'updated_at' => $now,
        ] + $this->activeUserColumns($db, false));
        $this->guardAdminId = (int) $db->insertID();

        try {
            $preview = $this->withSession($this->lastAdminGuardSession())
                ->get('/user-management/delete-preview/' . $this->adminId);

            $preview->assertOK();

            $previewPayload = json_decode($preview->getJSON(), true);
            $this->assertTrue((bool) ($previewPayload['success'] ?? false));
            $this->assertFalse((bool) ($previewPayload['allowed'] ?? true));
            $this->assertSame('LAST_ADMIN', $previewPayload['blockCode'] ?? null);
            $this->assertSame('admin', $previewPayload['target']['role'] ?? null);
            $this->assertSame(1, $previewPayload['impact']['adminCount'] ?? null);

            $this->primeCsrfCookie();

            $delete = $this->withSession($this->lastAdminGuardSession())
                ->withHeaders($this->ajaxHeaders())
                ->post('/user-management/delete/' . $this->adminId, [
                    $this->csrfTokenName() => $this->csrfToken(),
                ]);

            $delete->assertStatus(422);

            $deletePayload = json_decode($delete->getJSON(), true);
            $this->assertFalse((bool) ($deletePayload['success'] ?? true));
            $this->assertSame('LAST_ADMIN', $deletePayload['blockCode'] ?? null);
            $this->assertSame('Cannot delete the last active administrator. Promote another admin first.', $deletePayload['message'] ?? null);

            $admin = $db->table('users')->where('id', $this->adminId)->get()->getRowArray();
            $this->assertNotNull($admin);
            $this->assertSame('admin', $admin['role'] ?? null);
            $this->assertUserActiveState($admin, true);
        } finally {
            if ($otherActiveAdminIds !== []) {
                $db->table('users')->whereIn('id', $otherActiveAdminIds)->update([
                    'updated_at' => date('Y-m-d H:i:s'),
                ] + $this->activeUserColumns($db, true));
            }
        }
    }

    public function testNonAjaxProviderUpdateValidationFailureRedirectsBackToEditPage(): void
    {
        $db = \Config\Database::connect('tests');
        $now = date('Y-m-d H:i:s');
        $email = 'journey-provider-invalid-' . uniqid('', true) . '@example.com';

        $db->table('users')->insert([
            'name' => 'Provider With Invalid Schedule',
            'email' => $email,
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'provider',
            'created_at' => $now,
            'updated_at' => $now,
        ] + $this->activeUserColumns($db, true));

        $this->managedUserId = (int) $db->insertID();

        $this->primeCsrfCookie();

        $response = $this->withSession($this->adminSession())
            ->post('/user-management/update/' . $this->managedUserId, [
                $this->csrfTokenName() => $this->csrfToken(),
                'name' => 'Provider With Invalid Schedule Updated',
                'email' => $email,
                'phone' => '+15550001111',
                'role' => 'provider',
                'is_active' => '1',
                'schedule' => [
                    'monday' => [
                        'is_active' => '1',
                        'start_time' => '',
                        'end_time' => '17:00',
                    ],
                ],
            ]);

        $response->assertStatus(302);
        $response->assertRedirectTo('/user-management/edit/' . $this->managedUserId);

        $user = $db->table('users')->where('id', $this->managedUserId)->get()->getRowArray();
        $this->assertSame('Provider With Invalid Schedule', $user['name'] ?? null);
    }

    public function testAdminCanUpdateProviderNameWhenExistingScheduleTimesAreNotResubmitted(): void
    {
        $db = \Config\Database::connect('tests');
        $now = date('Y-m-d H:i:s');
        $email = 'journey-provider-partial-' . uniqid('', true) . '@example.com';

        $db->table('users')->insert([
            'name' => 'Provider Partial Schedule',
            'email' => $email,
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'provider',
            'created_at' => $now,
            'updated_at' => $now,
        ] + $this->activeUserColumns($db, true));

        $this->managedUserId = (int) $db->insertID();

        $db->table('provider_schedules')->insert([
            'provider_id' => $this->managedUserId,
            'day_of_week' => 'monday',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'break_start' => '12:00:00',
            'break_end' => '13:00:00',
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->primeCsrfCookie();

        $response = $this->withSession($this->adminSession())
            ->withHeaders($this->ajaxHeaders())
            ->post('/user-management/update/' . $this->managedUserId, [
                $this->csrfTokenName() => $this->csrfToken(),
                'name' => 'Provider Partial Schedule Updated',
                'email' => $email,
                'phone' => '+15557778888',
                'role' => 'provider',
                'is_active' => '1',
                'schedule' => [
                    'monday' => [
                        'is_active' => '1',
                    ],
                ],
            ]);

        $response->assertOK();

        $payload = json_decode($response->getJSON(), true);
        $this->assertTrue((bool) ($payload['success'] ?? false));
        $this->assertSame('User updated successfully.', $payload['message'] ?? null);

        $user = $db->table('users')->where('id', $this->managedUserId)->get()->getRowArray();
        $this->assertSame('Provider Partial Schedule Updated', $user['name'] ?? null);

        $schedule = $db->table('provider_schedules')->where('provider_id', $this->managedUserId)->where('day_of_week', 'monday')->get()->getRowArray();
        $this->assertNotNull($schedule);
        $this->assertSame('09:00:00', $schedule['start_time'] ?? null);
        $this->assertSame('17:00:00', $schedule['end_time'] ?? null);
        $this->assertSame('12:00:00', $schedule['break_start'] ?? null);
        $this->assertSame('13:00:00', $schedule['break_end'] ?? null);
    }

    public function testAdminCanUpdateProviderNameWhenExistingScheduleTimesAreSubmittedBlank(): void
    {
        $db = \Config\Database::connect('tests');
        $now = date('Y-m-d H:i:s');
        $email = 'journey-provider-blank-' . uniqid('', true) . '@example.com';

        $db->table('users')->insert([
            'name' => 'Provider Blank Schedule',
            'email' => $email,
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'provider',
            'created_at' => $now,
            'updated_at' => $now,
        ] + $this->activeUserColumns($db, true));

        $this->managedUserId = (int) $db->insertID();

        $db->table('provider_schedules')->insert([
            'provider_id' => $this->managedUserId,
            'day_of_week' => 'monday',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'break_start' => '12:00:00',
            'break_end' => '13:00:00',
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->primeCsrfCookie();

        $response = $this->withSession($this->adminSession())
            ->withHeaders($this->ajaxHeaders())
            ->post('/user-management/update/' . $this->managedUserId, [
                $this->csrfTokenName() => $this->csrfToken(),
                'name' => 'Provider Blank Schedule Updated',
                'email' => $email,
                'phone' => '+15554443333',
                'role' => 'provider',
                'is_active' => '1',
                'schedule' => [
                    'monday' => [
                        'is_active' => '1',
                        'start_time' => '',
                        'end_time' => '',
                        'break_start' => '',
                        'break_end' => '',
                    ],
                ],
            ]);

        $response->assertOK();

        $payload = json_decode($response->getJSON(), true);
        $this->assertTrue((bool) ($payload['success'] ?? false));
        $this->assertSame('User updated successfully.', $payload['message'] ?? null);

        $user = $db->table('users')->where('id', $this->managedUserId)->get()->getRowArray();
        $this->assertSame('Provider Blank Schedule Updated', $user['name'] ?? null);

        $schedule = $db->table('provider_schedules')->where('provider_id', $this->managedUserId)->where('day_of_week', 'monday')->get()->getRowArray();
        $this->assertNotNull($schedule);
        $this->assertSame('09:00:00', $schedule['start_time'] ?? null);
        $this->assertSame('17:00:00', $schedule['end_time'] ?? null);
        $this->assertSame('12:00:00', $schedule['break_start'] ?? null);
        $this->assertSame('13:00:00', $schedule['break_end'] ?? null);
    }

    public function testAdminCanUpdateStaffUserWithoutProviderScheduleData(): void
    {
        $db = \Config\Database::connect('tests');
        $now = date('Y-m-d H:i:s');
        $email = 'journey-staff-' . uniqid('', true) . '@example.com';

        $db->table('users')->insert([
            'name' => 'Journey Staff',
            'email' => $email,
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'staff',
            'created_at' => $now,
            'updated_at' => $now,
        ] + $this->activeUserColumns($db, true));

        $this->managedUserId = (int) $db->insertID();

        $this->primeCsrfCookie();

        $response = $this->withSession($this->adminSession())
            ->withHeaders($this->ajaxHeaders())
            ->post('/user-management/update/' . $this->managedUserId, [
                $this->csrfTokenName() => $this->csrfToken(),
                'name' => 'Journey Staff Updated',
                'email' => $email,
                'phone' => '+15556667777',
                'role' => 'staff',
                'is_active' => '1',
            ]);

        $response->assertOK();

        $payload = json_decode($response->getJSON(), true);
        $this->assertTrue((bool) ($payload['success'] ?? false));
        $this->assertSame('User updated successfully.', $payload['message'] ?? null);

        $user = $db->table('users')->where('id', $this->managedUserId)->get()->getRowArray();
        $this->assertSame('Journey Staff Updated', $user['name'] ?? null);
        $this->assertSame('+15556667777', $user['phone'] ?? null);
        $this->assertSame('staff', $user['role'] ?? null);
    }

    public function testLastAdminDeactivationReturns422WithBlockCode(): void
    {
        $db = \Config\Database::connect('tests');
        $now = date('Y-m-d H:i:s');

        // Deactivate any other active admins so only $this->adminId remains active
        $otherAdminIds = $db->table('users')
            ->select('id')
            ->where('role', 'admin')
            ->where('id !=', $this->adminId)
            ->get()
            ->getResultArray();
        $otherAdminIds = array_column($otherAdminIds, 'id');

        if ($otherAdminIds !== []) {
            $db->table('users')->whereIn('id', $otherAdminIds)->update(
                ['updated_at' => $now] + $this->activeUserColumns($db, false)
            );
        }

        $this->primeCsrfCookie();

        // Create a second admin (inactive) to act as the requesting session
        $db->table('users')->insert([
            'name' => 'Guard Admin',
            'email' => 'guard-deactivate-' . uniqid('', true) . '@example.com',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'admin',
            'created_at' => $now,
            'updated_at' => $now,
        ] + $this->activeUserColumns($db, false));
        $this->guardAdminId = (int) $db->insertID();

        try {
            $response = $this->withSession($this->lastAdminGuardSession())
                ->withHeaders($this->ajaxHeaders())
                ->post('/user-management/deactivate/' . $this->adminId, [
                    $this->csrfTokenName() => $this->csrfToken(),
                ]);

            $response->assertStatus(422);

            $payload = json_decode($response->getJSON(), true);
            $this->assertFalse((bool) ($payload['success'] ?? true));
            $this->assertSame('LAST_ADMIN', $payload['blockCode'] ?? null);

            // Confirm admin is still active in DB
            $admin = $db->table('users')->where('id', $this->adminId)->get()->getRowArray();
            $this->assertUserActiveState($admin, true);
        } finally {
            if ($otherAdminIds !== []) {
                $db->table('users')->whereIn('id', $otherAdminIds)->update(
                    ['updated_at' => date('Y-m-d H:i:s')] + $this->activeUserColumns($db, true)
                );
            }
        }
    }

    public function testLastAdminRoleDemotionReturns422WithBlockCode(): void
    {
        $db = \Config\Database::connect('tests');
        $now = date('Y-m-d H:i:s');

        // Deactivate any other active admins so only $this->adminId remains active
        $otherAdminIds = $db->table('users')
            ->select('id')
            ->where('role', 'admin')
            ->where('id !=', $this->adminId)
            ->get()
            ->getResultArray();
        $otherAdminIds = array_column($otherAdminIds, 'id');

        if ($otherAdminIds !== []) {
            $db->table('users')->whereIn('id', $otherAdminIds)->update(
                ['updated_at' => $now] + $this->activeUserColumns($db, false)
            );
        }

        $this->primeCsrfCookie();

        // Create an inactive second admin as the requesting session guard
        $db->table('users')->insert([
            'name' => 'Guard Admin',
            'email' => 'guard-demotion-' . uniqid('', true) . '@example.com',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'admin',
            'created_at' => $now,
            'updated_at' => $now,
        ] + $this->activeUserColumns($db, false));
        $this->guardAdminId = (int) $db->insertID();

        try {
            $response = $this->withSession($this->lastAdminGuardSession())
                ->withHeaders($this->ajaxHeaders())
                ->post('/user-management/update/' . $this->adminId, [
                    $this->csrfTokenName() => $this->csrfToken(),
                    'name' => 'Journey Admin',
                    'email' => 'journey-admin@example.com',
                    'role' => 'staff',
                    'is_active' => '1',
                ]);

            $response->assertStatus(422);

            $payload = json_decode($response->getJSON(), true);
            $this->assertFalse((bool) ($payload['success'] ?? true));
            $this->assertSame('LAST_ADMIN', $payload['blockCode'] ?? null);

            // Confirm role was NOT changed
            $admin = $db->table('users')->where('id', $this->adminId)->get()->getRowArray();
            $this->assertSame('admin', $admin['role'] ?? null);
        } finally {
            if ($otherAdminIds !== []) {
                $db->table('users')->whereIn('id', $otherAdminIds)->update(
                    ['updated_at' => date('Y-m-d H:i:s')] + $this->activeUserColumns($db, true)
                );
            }
        }
    }

    private function adminSession(): array
    {
        return [
            'isLoggedIn' => true,
            'user_id' => $this->adminId,
            'user' => [
                'id' => $this->adminId,
                'name' => 'Journey Admin',
                'email' => 'journey-admin@example.com',
                'role' => 'admin',
            ],
        ];
    }

    private function activeUserColumns($db, bool $active): array
    {
        $columns = [];

        if ($db->fieldExists('status', 'users')) {
            $columns['status'] = $active ? 'active' : 'inactive';
        }

        if ($db->fieldExists('is_active', 'users')) {
            $columns['is_active'] = $active ? 1 : 0;
        }

        return $columns;
    }

    private function assertUserActiveState(?array $user, bool $expected): void
    {
        $this->assertNotNull($user);

        if (array_key_exists('is_active', $user)) {
            $this->assertSame($expected ? '1' : '0', (string) ($user['is_active'] ?? ''));
            return;
        }

        if (array_key_exists('status', $user)) {
            $this->assertSame($expected ? 'active' : 'inactive', (string) ($user['status'] ?? ''));
            return;
        }

        $this->fail('Unable to assert active state; neither is_active nor status is available on users.');
    }

    private function lastAdminGuardSession(): array
    {
        return [
            'isLoggedIn' => true,
            'user_id' => $this->guardAdminId,
            'user' => [
                'id' => $this->guardAdminId,
                'name' => 'Guard Admin',
                'email' => 'guard-admin@example.com',
                'role' => 'admin',
            ],
        ];
    }

    private function ajaxHeaders(): array
    {
        return [
            'X-Requested-With' => 'XMLHttpRequest',
            'X-CSRF-TOKEN' => $this->csrfToken(),
        ];
    }

    private function csrfCookieName(): string
    {
        return config('Security')->cookieName;
    }

    private function csrfTokenName(): string
    {
        return config('Security')->tokenName;
    }

    private function csrfToken(): string
    {
        return csrf_hash();
    }

    private function primeCsrfCookie(): void
    {
        $_COOKIE[$this->csrfCookieName()] = $this->csrfToken();
        $_SERVER['HTTP_COOKIE'] = $this->csrfCookieName() . '=' . $this->csrfToken();
    }

    private function seedAdminUser(): void
    {
        $db = \Config\Database::connect('tests');
        $now = date('Y-m-d H:i:s');
        $email = 'journey-admin-' . uniqid('', true) . '@example.com';

        $db->table('users')->insert([
            'name' => 'Journey Admin',
            'email' => $email,
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'admin',
            'created_at' => $now,
            'updated_at' => $now,
        ] + $this->activeUserColumns($db, true));

        $this->adminId = (int) $db->insertID();
    }

    private function ensureSetupFlag(): void
    {
        $flagPath = WRITEPATH . 'setup_complete.flag';

        if (!is_file($flagPath)) {
            file_put_contents($flagPath, 'test');
        }
    }

    private function configureTestingDatabaseEnvironment(): void
    {
        $envPath = ROOTPATH . '.env';
        if (!is_file($envPath)) {
            return;
        }

        $values = [];
        foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#') || !str_contains($trimmed, '=')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode('=', $trimmed, 2));
            $values[$key] = trim($value, " \t\n\r\0\x0B\"'");
        }

        $mapping = [
            'database.tests.hostname' => $values['database.tests.hostname'] ?? $values['database.default.hostname'] ?? null,
            'database.tests.database' => $values['database.tests.database'] ?? $values['database.default.database'] ?? null,
            'database.tests.username' => $values['database.tests.username'] ?? $values['database.default.username'] ?? null,
            'database.tests.password' => $values['database.tests.password'] ?? $values['database.default.password'] ?? null,
            'database.tests.DBDriver' => $values['database.tests.DBDriver'] ?? $values['database.default.DBDriver'] ?? null,
            'database.tests.DBPrefix' => $values['database.tests.DBPrefix'] ?? $values['database.default.DBPrefix'] ?? 'xs_',
            'database.tests.port' => $values['database.tests.port'] ?? $values['database.default.port'] ?? '3306',
        ];

        foreach ($mapping as $key => $value) {
            if ($value === null) {
                continue;
            }

            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        $dbConfig = config(\Config\Database::class);
        foreach (['hostname', 'database', 'username', 'password', 'DBDriver', 'DBPrefix', 'port'] as $field) {
            $envKey = 'database.tests.' . $field;
            $value = $_ENV[$envKey] ?? $_SERVER[$envKey] ?? getenv($envKey);
            if ($value === false || $value === null || $value === '') {
                continue;
            }

            $dbConfig->tests[$field] = $field === 'port' ? (int) $value : $value;
            $dbConfig->default[$field] = $field === 'port' ? (int) $value : $value;
        }

        foreach (DatabaseConfig::getConnections() as $connection) {
            try {
                $connection->close();
            } catch (\Throwable) {
            }
        }

        $reflection = new \ReflectionClass(DatabaseConfig::class);
        $instances = $reflection->getProperty('instances');
        $instances->setAccessible(true);
        $instances->setValue([]);
    }
}