<?php

namespace Tests\Unit\Models;

use App\Models\CustomerModel;
use CodeIgniter\Test\CIUnitTestCase;
use ReflectionMethod;

/**
 * @internal
 */
final class CustomerModelTest extends CIUnitTestCase
{
    public function testHasCustomerColumnDoesNotProbeDoublePrefixedTableName(): void
    {
        $db = new class {
            public array $fieldDataCalls = [];

            public function getPrefix(): string
            {
                return 'xs_';
            }

            public function prefixTable(string $table): string
            {
                return 'xs_' . $table;
            }

            public function getFieldData(string $table): array
            {
                $this->fieldDataCalls[] = $table;

                if ($table === 'xs_customers') {
                    return [(object) ['name' => 'hash']];
                }

                return [];
            }
        };

        $model = new class ($db) extends CustomerModel {
            public function __construct(private object $fakeDb)
            {
                $this->db = $this->fakeDb;
            }
        };

        $method = new ReflectionMethod(CustomerModel::class, 'hasCustomerColumn');
        $method->setAccessible(true);

        $result = $method->invoke($model, 'hash');

        $this->assertTrue($result);
        $this->assertSame(['xs_customers'], $db->fieldDataCalls);
        $this->assertNotContains('xs_xs_customers', $db->fieldDataCalls);
    }
}