<?php

namespace Tests\Support\PHPUnit;

use App\Models\SettingModel;
use App\Services\SchemaInspector;
use PHPUnit\Event\Test\PreparationStarted;
use PHPUnit\Event\Test\PreparationStartedSubscriber;
use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;

/**
 * Clears the app's process-static caches and request-scoped shared services
 * before every test.
 *
 * In production these caches live for one HTTP request. Under PHPUnit the whole
 * suite shares one process, so settings written by one test (often via raw DB
 * inserts) stayed invisible to later tests and results depended on test order.
 */
final class ResetStaticCachesExtension implements Extension
{
    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        $facade->registerSubscriber(new class implements PreparationStartedSubscriber {
            public function notify(PreparationStarted $event): void
            {
                if (class_exists(SettingModel::class)) {
                    SettingModel::clearRequestCache();
                }
                if (class_exists(SchemaInspector::class)) {
                    SchemaInspector::reset();
                }
                // A Bearer-token test leaves its identity on the shared service,
                // making later session-based tests act as the token's user.
                \Config\Services::resetSingle('apiIdentity');
            }
        });
    }
}
