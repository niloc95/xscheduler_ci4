<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\SettingModel;

/**
 * Check Booking Settings
 * 
 * Displays all booking-related settings from the database
 */
class CheckBookingSettings extends BaseCommand
{
    protected $group       = 'Diagnostics';
    protected $name        = 'check:booking';
    protected $description = 'Display all booking settings from database';

    public function run(array $params)
    {
        $model = new SettingModel();
        
        // Get all booking settings
        $settings = $model->getByPrefix('booking.');
        
        CLI::write('Booking Settings from Database:', 'yellow');
        CLI::newLine();
        
        if (empty($settings)) {
            CLI::error('No booking settings found in database!');
            return;
        }
        
        // Sort by key for readability
        ksort($settings);
        
        foreach ($settings as $key => $value) {
            $displayValue = is_array($value) ? json_encode($value) : (string) $value;
            CLI::write(sprintf('%-50s : %s', $key, $displayValue), 'green');
        }
        
        CLI::newLine();
        CLI::write('Total settings: ' . count($settings), 'cyan');
    }
}
