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

    public function testPreserveNamesKeepsExistingNameOnSharedEmail(): void
    {
        $db = \Config\Database::connect('tests');
        $now = date('Y-m-d H:i:s');

        $db->table('customers')->insert([
            'first_name' => 'Pat',
            'last_name' => 'Parent',
            'email' => 'family@example.com',
            'phone' => '+27111222333',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $customerId = (int) $db->insertID();

        $svc = new CustomerService();

        // Parent books again for their child: same email, different name.
        $upsert = $svc->upsertCustomer([
            'first_name' => 'Charlie',
            'last_name' => 'Child',
            'email' => 'family@example.com',
            'phone' => '+27835556666',
        ], ['preserveNames' => true]);

        $this->assertFalse($upsert['wasCreated']);
        $this->assertSame($customerId, (int) $upsert['id']);
        $this->assertSame(['first_name', 'last_name'], $upsert['skippedFields']);

        $row = $db->table('customers')->where('id', $customerId)->get()->getRowArray();
        $this->assertSame('Pat', $row['first_name'] ?? null);
        $this->assertSame('Parent', $row['last_name'] ?? null);
        $this->assertSame('+27835556666', $row['phone'] ?? null);
    }

    public function testPreserveNamesFillsBlankName(): void
    {
        $db = \Config\Database::connect('tests');
        $now = date('Y-m-d H:i:s');

        $db->table('customers')->insert([
            'first_name' => '',
            'last_name' => null,
            'email' => 'blank-name@example.com',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $customerId = (int) $db->insertID();

        $svc = new CustomerService();
        $upsert = $svc->upsertCustomer([
            'first_name' => 'Filled',
            'last_name' => 'In',
            'email' => 'blank-name@example.com',
        ], ['preserveNames' => true]);

        $this->assertFalse($upsert['wasCreated']);
        $this->assertSame([], $upsert['skippedFields']);

        $row = $db->table('customers')->where('id', $customerId)->get()->getRowArray();
        $this->assertSame('Filled', $row['first_name'] ?? null);
        $this->assertSame('In', $row['last_name'] ?? null);
    }

    public function testDefaultUpsertStillOverwritesNames(): void
    {
        $db = \Config\Database::connect('tests');
        $now = date('Y-m-d H:i:s');

        $db->table('customers')->insert([
            'first_name' => 'Old',
            'last_name' => 'Name',
            'email' => 'admin-edit@example.com',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $customerId = (int) $db->insertID();

        // Admin/staff paths (no options) keep overwrite-for-typo-fixes behavior.
        $svc = new CustomerService();
        $upsert = $svc->upsertCustomer([
            'first_name' => 'New',
            'last_name' => 'Name',
            'email' => 'admin-edit@example.com',
        ]);

        $this->assertFalse($upsert['wasCreated']);
        $this->assertSame([], $upsert['skippedFields']);

        $row = $db->table('customers')->where('id', $customerId)->get()->getRowArray();
        $this->assertSame('New', $row['first_name'] ?? null);
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
