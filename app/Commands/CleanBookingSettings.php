<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\SettingModel;

/**
 * Clean Duplicate Booking Settings
 * 
 * Removes duplicate booking settings with incorrect "booking.booking_*" prefix
 * These were created due to a bug where settings were processed twice.
 */
class CleanBookingSettings extends BaseCommand
{
    protected $group       = 'Maintenance';
    protected $name        = 'clean:booking';
    protected $description = 'Remove duplicate booking settings with incorrect prefix';

    public function run(array $params)
    {
        $model = new SettingModel();
        $db = \Config\Database::connect();
        
        CLI::write('Cleaning duplicate booking settings...', 'yellow');
        CLI::newLine();
        
        // Find all settings with incorrect "booking.booking_*" prefix
        $duplicates = $db->table('xs_settings')
            ->like('setting_key', 'booking.booking_', 'after')
            ->get()
            ->getResultArray();
        
        if (empty($duplicates)) {
            CLI::write('✓ No duplicate settings found. Database is clean!', 'green');
            return;
        }
        
        CLI::write('Found ' . count($duplicates) . ' duplicate settings:', 'red');
        CLI::newLine();
        
        foreach ($duplicates as $setting) {
            $key = $setting['setting_key'];
            $value = $setting['setting_value'];
            CLI::write("  - {$key} = {$value}");
        }
        
        CLI::newLine();
        
        $confirm = CLI::prompt('Delete these duplicate settings?', ['y', 'n']);
        
        if ($confirm !== 'y') {
            CLI::write('Cancelled. No changes made.', 'yellow');
            return;
        }
        
        // Delete all duplicate settings
        $deleted = $db->table('xs_settings')
            ->like('setting_key', 'booking.booking_', 'after')
            ->delete();
        
        if ($deleted) {
            CLI::write("✓ Deleted {$deleted} duplicate settings successfully!", 'green');
        } else {
            CLI::error('Failed to delete duplicate settings.');
        }
        
        CLI::newLine();
        CLI::write('Database cleanup complete.', 'cyan');
    }
}
