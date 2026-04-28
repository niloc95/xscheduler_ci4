<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class AvatarHelperTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper('app');
    }

    public function testAvatarDisplayNamePrefersNameThenFirstLast(): void
    {
        $this->assertSame('Dr Ada Lovelace', avatar_display_name(['name' => 'Dr Ada Lovelace']));
        $this->assertSame('Ada Lovelace', avatar_display_name(['first_name' => 'Ada', 'last_name' => 'Lovelace']));
        $this->assertSame('', avatar_display_name([]));
    }

    public function testAvatarInitialsUsesCanonicalRules(): void
    {
        $cases = [
            ['name' => 'Ana Silva', 'expected' => 'AS'],
            ['name' => 'Dr. Ana Silva PhD', 'expected' => 'AS'],
            ['name' => 'Jean', 'expected' => 'JE'],
            ['name' => '', 'expected' => 'U'],
            ['name' => null, 'expected' => 'U'],
            ['name' => 'Mr John', 'expected' => 'JO'],
            ['name' => 'Maria de Souza', 'expected' => 'MS'],
        ];

        foreach ($cases as $case) {
            $this->assertSame($case['expected'], avatar_initials($case['name'], 'U'));
        }
    }

    public function testAvatarInitialsSupportsContextDefaults(): void
    {
        $this->assertSame('C', avatar_initials('', 'C'));
        $this->assertSame('?', avatar_initials(null, '?'));
    }
}
