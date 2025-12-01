<?php

namespace App\Commands;

use App\Models\CustomerModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Quick diagnostics for the customer search feature used on the appointments form.
 */
class TestCustomerSearch extends BaseCommand
{
    /**
     * @var string Command group shown in `php spark list`
     */
    protected $group = 'diagnostics';

    /**
     * @var string The actual CLI command (php spark diagnostics:customer-search)
     */
    protected $name = 'diagnostics:customer-search';

    /**
     * @var string Short description displayed in the CLI help.
     */
    protected $description = 'Runs a few quick checks to confirm the customer search pipeline is healthy.';

    /**
     * Executes the command.
     *
     * @param array<int, string> $params optional search term as the first parameter
     */
    public function run(array $params)
    {
        $term = $params[0] ?? 'test';
        $model = new CustomerModel();

        CLI::newLine();
        CLI::write('Customer Search Diagnostics', 'yellow');
        CLI::write('============================', 'yellow');

        $totalCustomers = $model->countAll();
        CLI::write("Total customers in database: {$totalCustomers}");

        $recentCustomers = $model->orderBy('created_at', 'DESC')->limit(3)->find();
        CLI::newLine();
        CLI::write('Most recent customers:');
        if (empty($recentCustomers)) {
            CLI::write('  (no customers found)', 'red');
        } else {
            foreach ($recentCustomers as $customer) {
                $name = trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '')) ?: 'Unknown';
                $email = $customer['email'] ?? 'no email';
                CLI::write("  - {$name} ({$email})");
            }
        }
    CLI::newLine();
    CLI::write("Searching for: \"{$term}\"");
        $results = $model->search(['q' => $term, 'limit' => 5]);

        if (empty($results)) {
            CLI::write('  No customers matched this term.', 'red');
        } else {
            foreach ($results as $customer) {
                $name = trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '')) ?: 'Unknown';
                $email = $customer['email'] ?? 'no email';
                $phone = $customer['phone'] ?? ($customer['phone_number'] ?? 'no phone');
                CLI::write("  - {$name} | {$email} | {$phone}");
            }
        }
    CLI::newLine();
    CLI::write('Run `php spark diagnostics:customer-search "<term>"` to test other search phrases.');
    CLI::newLine();
    }
}
