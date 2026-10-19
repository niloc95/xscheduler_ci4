<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

/**
 * Heal providers with a NULL/empty slug created after the 2026-04-23 backfill.
 *
 * The public booking SPA keys provider selection on `slug` (option values and
 * GET /api/v1/providers/slug/{slug}/services). A provider without a slug renders
 * as <option value="">, so selecting it loads no services. SchedulingSampleDataSeeder
 * inserted providers without a slug, leaving them unbookable.
 *
 * up(): for each provider (primary role OR xs_user_roles pivot) with no slug,
 * assign a unique slug derived from their name, using the same algorithm as
 * AddProviderPublicProfileFields::backfillProviderSlugs(). Uniqueness is checked
 * against ALL users because idx_xs_users_slug_unique spans the whole table.
 * Idempotent: rows that already have a slug are never touched.
 *
 * down(): no-op — healed slugs are indistinguishable from admin-assigned ones,
 * and removing them would break public booking links.
 */
class BackfillMissingProviderSlugs extends MigrationBase
{
    public function up(): void
    {
        $usersTable     = $this->db->prefixTable('users');
        $userRolesTable = $this->db->prefixTable('user_roles');

        if (!$this->db->fieldExists('slug', $usersTable)) {
            return;
        }

        $pivotProviderIds = array_map(
            static fn (array $row): int => (int) $row['user_id'],
            $this->db->table($userRolesTable)
                ->select('user_id')
                ->where('role', 'provider')
                ->get()
                ->getResultArray()
        );

        $builder = $this->db->table($usersTable)
            ->select('id, name')
            ->groupStart()
                ->where('slug IS NULL')
                ->orWhere('slug', '')
            ->groupEnd()
            ->groupStart()
                ->where('role', 'provider');
        if (!empty($pivotProviderIds)) {
            $builder->orWhereIn('id', $pivotProviderIds);
        }
        $rows = $builder->groupEnd()
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        if (empty($rows)) {
            return;
        }

        $used = [];
        $existing = $this->db->table($usersTable)
            ->select('slug')
            ->where('slug IS NOT NULL')
            ->where('slug !=', '')
            ->get()
            ->getResultArray();
        foreach ($existing as $row) {
            $used[(string) $row['slug']] = true;
        }

        foreach ($rows as $row) {
            $base = $this->slugify((string) ($row['name'] ?? ''));
            if ($base === '') {
                $base = 'provider';
            }

            $candidate = $base;
            $suffix = 2;
            while (isset($used[$candidate])) {
                $candidate = $base . '-' . $suffix;
                $suffix++;
            }

            $used[$candidate] = true;

            $this->db->table($usersTable)
                ->where('id', (int) $row['id'])
                ->update(['slug' => $candidate]);
        }

        log_message('info', '[BackfillMissingProviderSlugs] Healed ' . count($rows) . ' providers with no slug.');
    }

    public function down(): void
    {
        // Intentionally a no-op: see class docblock.
    }

    private function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        return trim($value, '-');
    }
}
