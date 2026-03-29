<?php

namespace Tests\Unit\Controllers;

use App\Controllers\Settings;
use App\Services\Settings\GeneralSettingsService;
use App\Services\Settings\NotificationSettingsService;
use App\Services\Settings\SettingsPageService;
use CodeIgniter\Test\CIUnitTestCase;
use ReflectionProperty;

final class SettingsControllerTest extends CIUnitTestCase
{
    public function testConstructorUsesProvidedDependencies(): void
    {
        $general = $this->createMock(GeneralSettingsService::class);
        $notifications = $this->createMock(NotificationSettingsService::class);
        $page = $this->createMock(SettingsPageService::class);

        $controller = new Settings($general, $notifications, $page);

        $this->assertSame($general, $this->readProperty($controller, 'generalSettingsService'));
        $this->assertSame($notifications, $this->readProperty($controller, 'notificationSettingsService'));
        $this->assertSame($page, $this->readProperty($controller, 'settingsPageService'));
    }

    private function readProperty(object $instance, string $property): mixed
    {
        $reflection = new ReflectionProperty($instance, $property);
        $reflection->setAccessible(true);

        return $reflection->getValue($instance);
    }
}