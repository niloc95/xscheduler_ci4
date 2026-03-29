<?php

namespace Tests\Unit\Controllers;

use App\Controllers\Search;
use App\Services\GlobalSearchService;
use CodeIgniter\Test\CIUnitTestCase;
use ReflectionProperty;

final class SearchControllerTest extends CIUnitTestCase
{
    public function testConstructorUsesProvidedDependency(): void
    {
        $searchService = $this->createMock(GlobalSearchService::class);

        $controller = new Search($searchService);

        $this->assertSame($searchService, $this->readProperty($controller, 'globalSearchService'));
    }

    private function readProperty(object $instance, string $property): mixed
    {
        $reflection = new ReflectionProperty($instance, $property);
        $reflection->setAccessible(true);

        return $reflection->getValue($instance);
    }
}