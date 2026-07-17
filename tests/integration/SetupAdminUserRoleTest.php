<?php

namespace App\Tests\Integration;

use App\Controllers\Setup;
use App\Database\Migrations\BackfillMissingUserRolePivotRows;
use App\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use ReflectionMethod;

/**
 * The setup wizard's admin user must exist in BOTH role layers:
 * xs_users.role (derived primary) and xs_user_roles (authoritative).
 * Installs created before this fix are healed by BackfillMissingUserRolePivotRows.
 */
final class SetupAdminUserRoleTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = 'App';
    protected $refresh = true;

    public function testCreateAdminUserWritesAuthoritativeRoleRow(): void
    {
        $db = \Config\Database::connect('tests');
        $email = 'setup-owner-' . uniqid('', true) . '@example.com';

        $method = new ReflectionMethod(Setup::class, 'createAdminUser');
        $method->setAccessible(true);

        $result = $method->invoke(new Setup(), [
            'name' => 'Setup Owner',
            'userid' => 'owner',
            'email' => $email,
            'password' => 'password123',
        ]);

        $this->assertTrue((bool) ($result['success'] ?? false), 'createAdminUser failed: ' . ($result['message'] ?? ''));

        $user = $db->table('users')->where('email', $email)->get()->getRowArray();
        $this->assertNotNull($user);
        $this->assertSame('admin', $user['role'] ?? null);

        $pivotRows = $db->table('user_roles')
            ->where('user_id', (int) $user['id'])
            ->get()
            ->getResultArray();
        $this->assertCount(1, $pivotRows);
        $this->assertSame('admin', $pivotRows[0]['role'] ?? null);

        // The real symptom guard: pure-pivot queries must see the setup admin.
        $adminIds = array_map(
            static fn(array $row): int => (int) $row['id'],
            (new UserModel())->whereHasRole('admin')->findAll()
        );
        $this->assertContains((int) $user['id'], $adminIds);
    }

    public function testBackfillMigrationHealsUsersWithNoPivotRows(): void
    {
        $db = \Config\Database::connect('tests');
        $now = date('Y-m-d H:i:s');

        // A pre-fix setup admin: primary role only, zero pivot rows.
        $db->table('users')->insert([
            'name' => 'Legacy Owner',
            'email' => 'legacy-owner-' . uniqid('', true) . '@example.com',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'admin',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $legacyId = (int) $db->insertID();
        $db->table('user_roles')->where('user_id', $legacyId)->delete();

        // A user whose provider role was deliberately removed via the UI:
        // still has a pivot row (staff), so the heal must not touch them.
        $db->table('users')->insert([
            'name' => 'Demoted Provider',
            'email' => 'demoted-' . uniqid('', true) . '@example.com',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'staff',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $demotedId = (int) $db->insertID();
        $db->table('user_roles')->where('user_id', $demotedId)->delete();
        $db->table('user_roles')->insert([
            'user_id' => $demotedId,
            'role' => 'staff',
            'created_at' => $now,
        ]);

        $migration = new BackfillMissingUserRolePivotRows();
        $migration->up();

        $legacyRows = $db->table('user_roles')->where('user_id', $legacyId)->get()->getResultArray();
        $this->assertCount(1, $legacyRows);
        $this->assertSame('admin', $legacyRows[0]['role'] ?? null);

        // Idempotent: running again adds nothing.
        $migration->up();
        $this->assertSame(1, $db->table('user_roles')->where('user_id', $legacyId)->countAllResults());

        // Untouched: users with existing pivot rows are not "healed".
        $demotedRows = $db->table('user_roles')->where('user_id', $demotedId)->get()->getResultArray();
        $this->assertCount(1, $demotedRows);
        $this->assertSame('staff', $demotedRows[0]['role'] ?? null);
    }
}
