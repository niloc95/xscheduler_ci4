<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class AddUniqueEmailToCustomers extends MigrationBase
{
    private const INDEX_NAME = 'idx_customers_email_unique';

    public function up()
    {
        if (!$this->db->tableExists($this->db->prefixTable('customers'))) {
            return;
        }

        if (!$this->db->fieldExists('email', 'customers')) {
            return;
        }

        $this->normalizeEmails();
        $this->nullifyDuplicateEmails();

        if (!$this->indexExists('customers', self::INDEX_NAME)) {
            $table = $this->db->prefixTable('customers');
            $this->db->query('ALTER TABLE `' . $table . '` ADD UNIQUE INDEX `' . self::INDEX_NAME . '` (`email`)');
        }
    }

    public function down()
    {
        $this->dropIndexIfExists('customers', self::INDEX_NAME);
    }

    private function normalizeEmails(): void
    {
        $table = $this->db->prefixTable('customers');
        $this->db->query(
            'UPDATE `' . $table . '` SET `email` = LOWER(TRIM(`email`)) WHERE `email` IS NOT NULL AND TRIM(`email`) != ""'
        );
    }

    private function nullifyDuplicateEmails(): void
    {
        $table = $this->db->prefixTable('customers');
        $duplicates = $this->db->query(
            'SELECT LOWER(TRIM(`email`)) AS normalized_email, MIN(`id`) AS keep_id
             FROM `' . $table . '` 
             WHERE `email` IS NOT NULL AND TRIM(`email`) != ""
             GROUP BY LOWER(TRIM(`email`))
             HAVING COUNT(*) > 1'
        )->getResultArray();

        foreach ($duplicates as $row) {
            $normalizedEmail = (string) ($row['normalized_email'] ?? '');
            $keepId = (int) ($row['keep_id'] ?? 0);

            if ($normalizedEmail === '' || $keepId <= 0) {
                continue;
            }

            $this->db->query(
                'UPDATE `' . $table . '`
                 SET `email` = NULL
                 WHERE LOWER(TRIM(`email`)) = ? AND `id` != ?',
                [$normalizedEmail, $keepId]
            );
        }
    }
}