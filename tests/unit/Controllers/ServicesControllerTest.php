<?php

namespace Tests\Unit\Controllers;

use App\Controllers\Services;
use App\Models\CategoryModel;
use App\Models\ServiceModel;
use App\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use ReflectionProperty;

final class ServicesControllerTest extends CIUnitTestCase
{
    public function testConstructorUsesProvidedDependencies(): void
    {
        $users = $this->createMock(UserModel::class);
        $services = $this->createMock(ServiceModel::class);
        $categories = $this->createMock(CategoryModel::class);

        $controller = new Services($users, $services, $categories);

        $this->assertSame($users, $this->readProperty($controller, 'userModel'));
        $this->assertSame($services, $this->readProperty($controller, 'serviceModel'));
        $this->assertSame($categories, $this->readProperty($controller, 'categoryModel'));
    }

    private function readProperty(object $instance, string $property): mixed
    {
        $reflection = new ReflectionProperty($instance, $property);
        $reflection->setAccessible(true);

        return $reflection->getValue($instance);
    }
}