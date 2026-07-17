<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

/**
 * Heal xs_user_roles rows missing for users created after the 2026-04-08 backfill.
 *
 * The setup wizard's createAdminUser() inserted the initial admin into xs_users
 * without writing the authoritative xs_user_roles pivot row, so installs set up
 * since then have an owner invisible to whereHasRole()-based queries (provider
 * pickers, role-aware stats). getRolesForUser()'s primary-role fallback masked
 * this for login/RBAC, but pure-pivot queries silently skipped the user.
 *
 * up(): for each user whose role is admin/provider/staff AND who has ZERO rows
 * in xs_user_roles, insert one row from their primary role. The zero-rows
 * predicate targets exactly the setup-wizard gap and can never resurrect a role
 * that an admin deliberately removed via the UI (the UI sync always rewrites
 * both layers, leaving at least one pivot row). Idempotent under uq_user_role.
 *
 * down(): no-op — healed rows are indistinguishable from legitimate ones.
 */
class BackfillMissingUserRolePivotRows extends MigrationBase
{
    public function up(): void
    {
        $usersTable     = $this->db->prefixTable('users');
        $userRolesTable = $this->db->prefixTable('user_roles');

        $users = $this->db->table($usersTable . ' u')
            ->select('u.id, u.role')
            ->join($userRolesTable . ' ur', 'ur.user_id = u.id', 'left')
            ->whereIn('u.role', ['admin', 'provider', 'staff'])
            ->where('ur.id IS NULL')
            ->get()
            ->getResultArray();

        if (empty($users)) {
            return;
        }

        $now  = date('Y-m-d H:i:s');
        $rows = [];
        foreach ($users as $u) {
            $rows[] = [
                'user_id'    => (int) $u['id'],
                'role'       => $u['role'],
                'created_at' => $now,
            ];
        }

        $this->db->table($userRolesTable)->insertBatch($rows);
        log_message('info', '[BackfillMissingUserRolePivotRows] Healed ' . count($rows) . ' users with no xs_user_roles rows.');
    }

    public function down(): void
    {
        // Intentionally a no-op: healed rows carry no marker distinguishing them
        // from rows written by setup or the user-management UI, and removing role
        // membership on rollback would break logins. up() is safely re-runnable
        // thanks to the zero-rows predicate and the uq_user_role unique key.
    }
}
