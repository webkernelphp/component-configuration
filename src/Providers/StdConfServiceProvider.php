<?php

declare(strict_types=1);

namespace Webkernel\Component\Config\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Container\Container;
use Webkernel\Component\Config\Bootstrappers\ConfigInjectionDispatcher;
use Webkernel\Component\Config\Bootstrappers\DatabaseBootstrapper;

class StdConfServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        /**
         * Register the dispatcher as a singleton so every module
         * interacts with the same instance to queue their injections.
         * typed as 'Container' to match Laravel's container dependency injection.
         */
        $this->app->singleton(ConfigInjectionDispatcher::class, function (Container $app): ConfigInjectionDispatcher {
            return new ConfigInjectionDispatcher($app['config']);
        });
    }

    public function boot(): void
    {
        /**
         * Apply the installation-time database state first.
         * This ensures 'webkernel_primary' is available before any module boots.
         */
        (new DatabaseBootstrapper($this->app['config']))->apply();

        /**
         * Apply all module injections collected during the register() phase.
         * Module providers must call $dispatcher->add() in their own register() method
         * to ensure all injections are properly queued before this execution.
         */
        $this->app->make(ConfigInjectionDispatcher::class)->apply();
    }
}
