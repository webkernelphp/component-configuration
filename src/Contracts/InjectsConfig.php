<?php

declare(strict_types=1);

namespace Webkernel\Component\Config\Contracts;

interface InjectsConfig
{
    /**
     * The top-level config key this injection targets.
     * Examples: 'auth', 'database', 'mail'
     */
    public function configScope(): string;

    /**
     * Return a flat array of dot-notation key => value pairs to set.
     *
     * Example:
     * [
     *     'database.connections.module_db' => [...],
     *     'mail.mailers.module_mailer'     => [...],
     * ]
     *
     * @return array<string, mixed>
     */
    public function configValues(): array;
}
