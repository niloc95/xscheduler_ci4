<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class AddProviderPublicProfileFields extends MigrationBase
{
    public function up(): void
    {
        $table = $this->db->prefixTable('users');
        $this->db->resetDataCache();

        if (!$this->db->fieldExists('title', $table)) {
            $this->forge->addColumn('users', [
                'title' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'null'       => true,
                    'after'      => 'name',
                ],
            ]);
        }

        if (!$this->db->fieldExists('bio', $table)) {
            $this->forge->addColumn('users', [
                'bio' => [
                    'type' => 'TEXT',
                    'null' => true,
                    'after' => 'profile_image',
                ],
            ]);
        }

        if (!$this->db->fieldExists('education', $table)) {
            $this->forge->addColumn('users', [
                'education' => [
                    'type' => 'TEXT',
                    'null' => true,
                    'after' => 'bio',
                ],
            ]);
        }

        if (!$this->db->fieldExists('qualifications', $table)) {
            $this->forge->addColumn('users', [
                'qualifications' => [
                    'type' => 'TEXT',
                    'null' => true,
                    'after' => 'education',
                ],
            ]);
        }

        if (!$this->db->fieldExists('slug', $table)) {
            $this->forge->addColumn('users', [
                'slug' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 150,
                    'null'       => true,
                    'after'      => 'qualifications',
                ],
            ]);
        }

        $this->backfillProviderSlugs();
        $this->ensureNamedUniqueIndex($table, 'slug', 'idx_xs_users_slug_unique');
    }

    public function down(): void
    {
        $table = $this->db->prefixTable('users');
        $this->dropNamedIndexIfExists($table, 'idx_xs_users_slug_unique');

        foreach (['slug', 'qualifications', 'education', 'bio', 'title'] as $column) {
            if ($this->db->fieldExists($column, $table)) {
                $this->forge->dropColumn('users', $column);
            }
        }
    }

    private function backfillProviderSlugs(): void
    {
        $table = $this->db->prefixTable('users');

        $rows = $this->db->table($table)
            ->select('id, name, slug')
            ->where('role', 'provider')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        $used = [];
        foreach ($rows as $row) {
            $existing = trim((string) ($row['slug'] ?? ''));
            if ($existing !== '') {
                $used[$existing] = true;
            }
        }

        foreach ($rows as $row) {
            $existing = trim((string) ($row['slug'] ?? ''));
            if ($existing !== '') {
                continue;
            }

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

            $this->db->table($table)
                ->where('id', (int) $row['id'])
                ->update(['slug' => $candidate]);
        }
    }

    private function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        return trim($value, '-');
    }

    private function ensureNamedUniqueIndex(string $table, string $column, string $indexName): void
    {
        if ($this->namedIndexExists($table, $indexName)) {
            return;
        }

        $this->db->query(sprintf('CREATE UNIQUE INDEX %s ON %s (%s)', $indexName, $table, $column));
    }

    private function dropNamedIndexIfExists(string $table, string $indexName): void
    {
        if (!$this->namedIndexExists($table, $indexName)) {
            return;
        }

        $this->db->query(sprintf('DROP INDEX %s ON %s', $indexName, $table));
    }

    private function namedIndexExists(string $table, string $indexName): bool
    {
        $result = $this->db->query(sprintf('SHOW INDEX FROM %s WHERE Key_name = %s', $table, $this->db->escape($indexName)));
        return !empty($result->getResultArray());
    }
}
