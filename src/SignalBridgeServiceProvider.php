<?php

namespace Nugsoft\SignalBridge;

use Illuminate\Support\ServiceProvider;

class SignalBridgeServiceProvider extends ServiceProvider
{
  /**
   * Bootstrap any application services.
   */
  public function boot(): void
  {
    // Publish configuration file
    $this->publishes([
      __DIR__ . '/../config/signalbridge.php' => config_path('signalbridge.php'),
    ], 'signalbridge-config');
  }

  /**
   * Register any application services.
   */
  public function register(): void
  {
    // Merge package config with app config
    $this->mergeConfigFrom(
      __DIR__ . '/../config/signalbridge.php',
      'signalbridge'
    );

    // Register the main class to use with the facade
    $this->app->singleton('signalbridge', function ($app) {
      return new SignalBridgeClient(
        config('signalbridge.token'),
        config('signalbridge.url'),
        config('signalbridge.timeout', 30)
      );
    });

    // Alias for dependency injection
    $this->app->alias('signalbridge', SignalBridgeClient::class);
  }

  /**
   * Get the services provided by the provider.
   */
  public function provides(): array
  {
    return ['signalbridge', SignalBridgeClient::class];
  }
}
