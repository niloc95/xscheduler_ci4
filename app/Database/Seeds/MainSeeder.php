<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class MainSeeder extends Seeder
{
    public function run()
    {
        // Seed default services
        $this->call('DefaultServicesSeeder');
    }
}
