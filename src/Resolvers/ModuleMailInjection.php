<?php

declare(strict_types=1);

namespace Webkernel\Component\Config\Resolvers;

use Webkernel\Component\Config\Contracts\InjectsConfig;

/**
 * Example: a module that needs its own mailer (e.g. Resend for transactional email).
 *
 * Register in your module's service provider:
 *
 *   $dispatcher = app(ConfigInjectionDispatcher::class);
 *   $dispatcher->add(new ModuleMailInjection());
 *
 * Then in your Mailable or Notification:
 *
 *   Mail::mailer('module_resend')->to($user)->send(new SomeMail());
 */
final class ModuleMailInjection implements InjectsConfig
{
    public function configScope(): string
    {
        return 'mail';
    }

    public function configValues(): array
    {
        return [
            'mail.mailers.module_resend' => [
                'transport' => 'resend',
                'key'       => env('MODULE_RESEND_KEY', ''),
            ],
        ];
    }
}
