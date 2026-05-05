<?php

namespace App\Tests\Integration;

use App\Services\CustomerService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

final class CustomerServiceUpsertTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = 'App';
    protected $refresh = true;

    public function testUpsertUpdatesPhoneForExistingEmailWithoutOverwritingWithEmpty(): void
    {
        $db = \Config\Database::connect('tests');
        $now = date('Y-m-d H:i:s');

        $db->table('customers')->insert([
            'first_name' => 'Nilo',
            'last_name' => 'Cara',
            'email' => 'Nilo@Test.co.za',
            'phone' => '+27111222333',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $customerId = (int) $db->insertID();

        $svc = new CustomerService();

        $upsert1 = $svc->upsertCustomer([
            'first_name' => 'Nilo',
            'last_name' => 'Cara',
            'email' => ' nilo@test.co.za ',
            'phone' => '+27835556666',
        ]);

        $this->assertFalse($upsert1['wasCreated']);
        $this->assertSame($customerId, (int) $upsert1['id']);

        $row1 = $db->table('customers')->where('id', $customerId)->get()->getRowArray();
        $this->assertSame('nilo@test.co.za', $row1['email'] ?? null);
        $this->assertSame('+27835556666', $row1['phone'] ?? null);

        $upsert2 = $svc->upsertCustomer([
            'first_name' => 'Nilo',
            'last_name' => 'Cara',
            'email' => 'nilo@test.co.za',
            'phone' => '',
        ]);

        $this->assertFalse($upsert2['wasCreated']);
        $row2 = $db->table('customers')->where('id', $customerId)->get()->getRowArray();
        $this->assertSame('+27835556666', $row2['phone'] ?? null);
    }

    public function testUpsertCreatesDifferentCustomerForSameNameDifferentEmail(): void
    {
        $db = \Config\Database::connect('tests');
        $now = date('Y-m-d H:i:s');

        $db->table('customers')->insert([
            'first_name' => 'Sam',
            'last_name' => 'Smith',
            'email' => 'sam.one@example.com',
            'phone' => '+15550001111',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $svc = new CustomerService();
        $upsert = $svc->upsertCustomer([
            'first_name' => 'Sam',
            'last_name' => 'Smith',
            'email' => 'sam.two@example.com',
            'phone' => '+15550002222',
        ]);

        $this->assertTrue($upsert['wasCreated']);

        $count = $db->table('customers')
            ->where('first_name', 'Sam')
            ->where('last_name', 'Smith')
            ->countAllResults();

        $this->assertSame(2, $count);
    }
}
