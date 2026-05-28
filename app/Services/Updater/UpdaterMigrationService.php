<?php

namespace App\Services\Updater;

class UpdaterMigrationService
{
    /**
     * Run all pending migrations using a non-shared DB connection.
     * Mirrors the pattern in Setup.php:444 exactly so stale shared-service
     * state from before file replacement does not affect the runner.
     */
    public function run(): array
    {
        try {
            $db      = \Config\Database::connect('default', false);
            $migrate = \Config\Services::migrations(config('Migrations'), $db, false);

            $result = $migrate->latest();

            if ($result === false) {
                return [
                    'success' => false,
                    'message' => implode("\n", $migrate->getCliMessages()),
                ];
            }

            return [
                'success' => true,
                'ran'     => count($migrate->getCliMessages()),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
