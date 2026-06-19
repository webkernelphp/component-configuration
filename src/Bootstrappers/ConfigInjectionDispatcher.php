<?php

declare(strict_types=1);

namespace Webkernel\StdConf\Bootstrappers;

use Illuminate\Contracts\Config\Repository;
use Webkernel\StdConf\Contracts\InjectsConfig;

final class ConfigInjectionDispatcher
{
    /** @var InjectsConfig[] */
    private array $injections = [];

    public function __construct(private readonly Repository $config) {}

    /**
     * Register an injection from a module.
     * Called by modules in their service provider register() or boot() phase.
     */
    public function add(InjectsConfig $injection): void
    {
        $this->injections[] = $injection;
    }

    /**
     * Apply all registered injections to the config repository.
     * Called once by StdConfServiceProvider after all providers have booted.
     */
    public function apply(): void
    {
        foreach ($this->injections as $injection) {
            foreach ($injection->configValues() as $key => $value) {
                $this->config->set($key, $value);
            }
        }
    }
}
