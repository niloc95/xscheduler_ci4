<?php

namespace App\Tests\Integration;

use App\Services\AppointmentBookingService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use ReflectionMethod;

/**
 * The public booking CREATE path resolves customers through
 * AppointmentBookingService::resolveCustomer(). A public-channel booking with a
 * shared email but a different name must not rename the stored customer, and
 * the submitted name must surface on the appointment ("Booked for: …") so
 * staff can see who it is for. Internal channels keep overwrite behavior.
 */
final class PublicBookingCustomerPreservationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = 'App';
    protected $refresh = true;

    public function testPublicChannelPreservesCustomerNameAndReportsSkippedFields(): void
    {
        $db = \Config\Database::connect('tests');
        $customerId = $this->seedCustomer($db, 'Pat', 'Parent', 'family-create@example.com');

        $result = $this->invokeResolveCustomer([
            'booking_channel' => 'public',
            'customer_first_name' => 'Charlie',
            'customer_last_name' => 'Child',
            'customer_email' => 'family-create@example.com',
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame($customerId, (int) $result['customerId']);
        $this->assertSame(['first_name', 'last_name'], $result['skippedFields']);

        $row = $db->table('customers')->where('id', $customerId)->get()->getRowArray();
        $this->assertSame('Pat', $row['first_name'] ?? null);
        $this->assertSame('Parent', $row['last_name'] ?? null);
    }

    public function testInternalChannelStillOverwritesCustomerName(): void
    {
        $db = \Config\Database::connect('tests');
        $customerId = $this->seedCustomer($db, 'Old', 'Name', 'internal-create@example.com');

        $result = $this->invokeResolveCustomer([
            'booking_channel' => 'internal',
            'customer_first_name' => 'Corrected',
            'customer_last_name' => 'Name',
            'customer_email' => 'internal-create@example.com',
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame($customerId, (int) $result['customerId']);
        $this->assertSame([], $result['skippedFields']);

        $row = $db->table('customers')->where('id', $customerId)->get()->getRowArray();
        $this->assertSame('Corrected', $row['first_name'] ?? null);
    }

    public function testAppendBookedForNoteAddsSubmittedNameOnlyWhenNamesWereSkipped(): void
    {
        $method = new ReflectionMethod(AppointmentBookingService::class, 'appendBookedForNote');
        $method->setAccessible(true);
        $service = new AppointmentBookingService();

        $data = ['customer_first_name' => 'Charlie', 'customer_last_name' => 'Child'];

        $this->assertSame(
            "Please use side entrance\nBooked for: Charlie Child",
            $method->invoke($service, 'Please use side entrance', $data, ['first_name', 'last_name'])
        );
        $this->assertSame(
            'Booked for: Charlie Child',
            $method->invoke($service, '', $data, ['first_name'])
        );
        $this->assertSame(
            'Existing note',
            $method->invoke($service, 'Existing note', $data, []),
            'No skipped names means no note change'
        );
        $this->assertSame(
            '',
            $method->invoke($service, '', ['customer_first_name' => '  '], ['first_name']),
            'Blank submitted name adds nothing'
        );
    }

    private function invokeResolveCustomer(array $data): array
    {
        $method = new ReflectionMethod(AppointmentBookingService::class, 'resolveCustomer');
        $method->setAccessible(true);

        return $method->invoke(new AppointmentBookingService(), $data);
    }

    private function seedCustomer($db, string $first, string $last, string $email): int
    {
        $now = date('Y-m-d H:i:s');
        $db->table('customers')->insert([
            'first_name' => $first,
            'last_name' => $last,
            'email' => $email,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $db->insertID();
    }
}
