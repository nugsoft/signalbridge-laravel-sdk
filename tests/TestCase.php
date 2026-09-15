<?php

namespace Nugsoft\SignalBridge\Tests;

use Nugsoft\SignalBridge\SignalBridgeServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [SignalBridgeServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('signalbridge.url', 'https://gateway.test/api');
        $app['config']->set('signalbridge.token', 'test-token');
        $app['config']->set('signalbridge.logging', false);
    }
}
