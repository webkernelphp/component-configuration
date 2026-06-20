<?php

declare(strict_types=1);

namespace Webkernel\StdConf\Providers;

use Illuminate\Support\ServiceProvider;
use Webkernel\StdConf\Bootstrappers\ConfigInjectionDispatcher;
use Webkernel\StdConf\Bootstrappers\DatabaseBootstrapper;

class StdConfServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        // Register the dispatcher as a singleton so every module
        // gets the same instance and can push injections into it.
        $this->app->singleton(ConfigInjectionDispatcher::class, fn(array $app): \Webkernel\StdConf\Bootstrappers\ConfigInjectionDispatcher => new ConfigInjectionDispatcher($app['config']));
    }

    public function boot(): void
    {
        // Apply the installation-time database state first.
        // This makes 'webkernel_primary' available before any module boots.
        (new DatabaseBootstrapper($this->app['config']))->apply();

        // Apply all module injections collected during register() phases.
        // Module providers must call $dispatcher->add() in their register() method
        // so that all injections are queued before this fires.
        $this->app->make(ConfigInjectionDispatcher::class)->apply();
    }
}
