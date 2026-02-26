<?php

/**
 * =============================================================================
 * Migration: Add buffer_before and buffer_after to xs_services
 * =============================================================================
 *
 * Audit finding D1: Currently buffer time between appointments is only
 * available as a global setting. This migration adds per-service buffer
 * columns so individual services can override the global default.
 *
 * Columns added to xs_services:
 * - buffer_before  INT (minutes) : Buffer before appointment starts
 * - buffer_after   INT (minutes) : Buffer after appointment ends (cleanup)
 *
 * NULL = use global booking.buffer_minutes setting (backward compatible)
 *
 * @package App\Database\Migrations
 */

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class AddServiceBufferColumns extends MigrationBase
{
    public function up(): void
    {
        if (!$this->db->fieldExists('buffer_before', 'services')) {
            $fields = $this->sanitiseFields([
                'buffer_before' => [
                    'type'     => 'INT',
                    'unsigned' => true,
                    'null'     => true,
                    'default'  => null,
                    'comment'  => 'Minutes of buffer before appointment. NULL = use global setting.',
                ],
                'buffer_after' => [
                    'type'     => 'INT',
                    'unsigned' => true,
                    'null'     => true,
                    'default'  => null,
                    'comment'  => 'Minutes of buffer after appointment. NULL = use global setting.',
                ],
            ]);

            $this->forge->addColumn('services', $fields);
        }
    }

    public function down(): void
    {
        if ($this->db->fieldExists('buffer_before', 'services')) {
            $this->forge->dropColumn('services', ['buffer_before', 'buffer_after']);
        }
    }
}
