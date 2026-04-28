<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class AddSlugToServices extends MigrationBase
{
    public function up(): void
    {
        $table = $this->db->prefixTable('services');

        if (!$this->db->fieldExists('slug', $table)) {
            $this->forge->addColumn('services', [
                'slug' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 150,
                    'null'       => true,
                    'after'      => 'name',
                ],
            ]);
        }

        $this->backfillServiceSlugs();
        $this->ensureNamedUniqueIndex($table, 'slug', 'idx_xs_services_slug_unique');
    }

    public function down(): void
    {
        $table = $this->db->prefixTable('services');
        $this->dropNamedIndexIfExists($table, 'idx_xs_services_slug_unique');

        if ($this->db->fieldExists('slug', $table)) {
            $this->forge->dropColumn('services', 'slug');
        }
    }

    private function backfillServiceSlugs(): void
    {
        $table = $this->db->prefixTable('services');

        $rows = $this->db->table($table)
            ->select('id, name, slug')
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
                $base = 'service';
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
