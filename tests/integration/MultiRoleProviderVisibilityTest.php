<?php

namespace App\Tests\Integration;

use App\Models\UserModel;
use App\Services\UserManagementContextService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use ReflectionMethod;

/**
 * A single-owner business runs with one user holding both admin and provider
 * roles (xs_user_roles is authoritative; xs_users.role stores the derived
 * primary, 'admin'). Role-aware queries must include that user everywhere a
 * provider is expected.
 */
final class MultiRoleProviderVisibilityTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = 'App';
    protected $refresh = true;

    private int $ownerId = 0;
    private int $pureProviderId = 0;
    private int $pureAdminId = 0;
    private int $staffId = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $db = \Config\Database::connect('tests');
        $now = date('Y-m-d H:i:s');

        $this->ownerId = $this->seedUser($db, 'Owner Dual', 'admin', '#3B82F6');
        $this->pureProviderId = $this->seedUser($db, 'Pure Provider', 'provider', '#EF4444');
        $this->pureAdminId = $this->seedUser($db, 'Pure Admin', 'admin', null);
        $this->staffId = $this->seedUser($db, 'Staff Member', 'staff', null);

        $pivot = [
            ['user_id' => $this->ownerId, 'role' => 'admin'],
            ['user_id' => $this->ownerId, 'role' => 'provider'],
            ['user_id' => $this->pureProviderId, 'role' => 'provider'],
            ['user_id' => $this->pureAdminId, 'role' => 'admin'],
            ['user_id' => $this->staffId, 'role' => 'staff'],
        ];
        foreach ($pivot as $row) {
            $db->table('user_roles')->insert($row + ['created_at' => $now]);
        }
    }

    public function testGetStatsCountsRoleMembership(): void
    {
        $stats = (new UserModel())->getStats();

        $this->assertSame(2, $stats['providers'], 'Dual-role owner must count as a provider');
        $this->assertSame(2, $stats['admins']);
        $this->assertSame(1, $stats['staff']);
        $this->assertSame(4, $stats['total']);
    }

    public function testAvailableProviderColorSkipsDualRoleOwnersColor(): void
    {
        $color = (new UserModel())->getAvailableProviderColor();

        $this->assertNotSame('#3B82F6', $color, "Dual-role owner's color must stay in the used pool");
        $this->assertNotSame('#EF4444', $color);
    }

    public function testActiveUsersByRoleIncludesDualRoleOwner(): void
    {
        $service = new UserManagementContextService();
        $method = new ReflectionMethod(UserManagementContextService::class, 'getActiveUsersByRole');
        $method->setAccessible(true);

        $providerIds = array_map(
            static fn(array $row): int => (int) $row['id'],
            $method->invoke($service, 'provider')
        );

        $this->assertContains($this->ownerId, $providerIds);
        $this->assertContains($this->pureProviderId, $providerIds);
        $this->assertNotContains($this->pureAdminId, $providerIds);
    }

    public function testUserStatsForAdminCountMembershipWithoutDoubleCountingTotal(): void
    {
        $stats = (new UserManagementContextService())->getUserStats($this->pureAdminId);

        $this->assertSame(4, $stats['total'], 'Dual-role user is one person in total');
        $this->assertSame(2, $stats['admins']);
        $this->assertSame(2, $stats['providers']);
        $this->assertSame(1, $stats['staff']);
    }

    public function testApiUsersRoleFilterMatchesRoleMembership(): void
    {
        $result = (new UserManagementContextService())->getApiUsers($this->pureAdminId, 'provider');

        $ids = array_map(static fn(array $row): int => (int) $row['id'], $result['items']);
        $this->assertContains($this->ownerId, $ids, 'Dual-role owner must match role=provider filter');
        $this->assertContains($this->pureProviderId, $ids);
        $this->assertNotContains($this->pureAdminId, $ids);
        $this->assertSame(2, $result['total']);
    }

    public function testAssignmentEnrichmentUsesRoleMembership(): void
    {
        $db = \Config\Database::connect('tests');
        $db->table('provider_staff_assignments')->insert([
            'provider_id' => $this->ownerId,
            'staff_id' => $this->staffId,
            'status' => 'active',
            'assigned_at' => date('Y-m-d H:i:s'),
        ]);

        $result = (new UserManagementContextService())->getApiUsers($this->pureAdminId);

        $byId = [];
        foreach ($result['items'] as $row) {
            $byId[(int) $row['id']] = $row;
        }

        $this->assertSame(
            'Staff Member',
            $byId[$this->ownerId]['assignments'] ?? null,
            "Dual-role owner's staff assignments must be resolved via provider membership"
        );
        $this->assertSame('Owner Dual', $byId[$this->staffId]['assignments'] ?? null);
    }

    public function testDualRoleOwnerCreatingStaffGetsAutoAssigned(): void
    {
        $db = \Config\Database::connect('tests');

        $result = (new \App\Services\UserManagementMutationService())->createUser(
            $this->ownerId,
            [
                'id' => $this->ownerId,
                'role' => 'admin',
                'roles' => ['admin', 'provider'],
            ],
            [
                'name' => 'New Staffer',
                'email' => 'new-staffer-' . uniqid('', true) . '@example.com',
                'roles' => ['staff'],
                'password' => 'password123',
            ]
        );

        $this->assertTrue((bool) ($result['success'] ?? false), 'createUser failed: ' . ($result['message'] ?? ''));

        $assignment = $db->table('provider_staff_assignments')
            ->where('provider_id', $this->ownerId)
            ->where('staff_id', (int) $result['userId'])
            ->get()
            ->getRowArray();
        $this->assertNotNull($assignment, 'Dual-role owner must get their new staff auto-assigned');
    }

    public function testPureAdminCreatingStaffGetsNoAutoAssignment(): void
    {
        $db = \Config\Database::connect('tests');

        $result = (new \App\Services\UserManagementMutationService())->createUser(
            $this->pureAdminId,
            [
                'id' => $this->pureAdminId,
                'role' => 'admin',
                'roles' => ['admin'],
            ],
            [
                'name' => 'Unassigned Staffer',
                'email' => 'unassigned-staffer-' . uniqid('', true) . '@example.com',
                'roles' => ['staff'],
                'password' => 'password123',
            ]
        );

        $this->assertTrue((bool) ($result['success'] ?? false), 'createUser failed: ' . ($result['message'] ?? ''));

        $count = $db->table('provider_staff_assignments')
            ->where('staff_id', (int) $result['userId'])
            ->countAllResults();
        $this->assertSame(0, $count, 'Pure admin holds no provider role; no auto-assignment expected');
    }

    private function seedUser($db, string $name, string $primaryRole, ?string $color): int
    {
        $now = date('Y-m-d H:i:s');
        $data = [
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)) . '-' . uniqid('', true) . '@example.com',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => $primaryRole,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if ($color !== null && $db->fieldExists('color', 'users')) {
            $data['color'] = $color;
        }

        if ($db->fieldExists('is_active', 'users')) {
            $data['is_active'] = 1;
        }
        if ($db->fieldExists('status', 'users')) {
            $data['status'] = 'active';
        }

        $db->table('users')->insert($data);

        return (int) $db->insertID();
    }
}
