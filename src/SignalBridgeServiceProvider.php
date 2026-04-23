<?php

namespace Nugsoft\SignalBridge;

use Illuminate\Support\ServiceProvider;
use Nugsoft\SignalBridge\Contracts\SignalBridgeClientInterface;

class SignalBridgeServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/signalbridge.php' => config_path('signalbridge.php'),
        ], 'signalbridge-config');

        if ($this->app->runningInConsole() && empty(config('signalbridge.token'))) {
            $this->app->make('log')->warning('SignalBridge: SIGNALBRIDGE_TOKEN is not set. The SDK will throw on first use.');
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/signalbridge.php',
            'signalbridge'
        );

        $this->app->singleton('signalbridge', function ($app) {
            return new SignalBridgeClient(
                config('signalbridge.token'),
                config('signalbridge.url'),
                (int) config('signalbridge.timeout', 30)
            );
        });

        $this->app->alias('signalbridge', SignalBridgeClient::class);
        $this->app->alias('signalbridge', SignalBridgeClientInterface::class);
    }

    public function provides(): array
    {
        return ['signalbridge', SignalBridgeClient::class, SignalBridgeClientInterface::class];
    }
}
