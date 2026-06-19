<?php

declare(strict_types=1);

namespace Webkernel\StdConf\Resolvers;

use Webkernel\StdConf\Contracts\InjectsConfig;

/**
 * Example: a module that needs a dedicated database connection.
 *
 * Copy this pattern into your module's src/Config/ directory and register it
 * in your module's service provider:
 *
 *   $dispatcher = app(ConfigInjectionDispatcher::class);
 *   $dispatcher->add(new ModuleDatabaseInjection());
 */
final class ModuleDatabaseInjection implements InjectsConfig
{
    public function configScope(): string
    {
        return 'database';
    }

    public function configValues(): array
    {
        return [
            'database.connections.module_example' => [
                'driver'   => env('MODULE_DB_DRIVER', 'sqlite'),
                'database' => env('MODULE_DB_DATABASE', database_path('module_example.sqlite')),
                'prefix'   => 'me_',
            ],
        ];
    }
}
