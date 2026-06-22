<?php

declare(strict_types=1);

namespace Webkernel\Component\Config\Platform;

use Illuminate\Support\Arr;

final class PlatformConfigStore
{
    public function directory(): string
    {
        $directory = function_exists('webkernel_platform_dir')
            ? webkernel_platform_dir()
            : base_path('bootstrap/cache/webkernel');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return $directory;
    }

    public function path(): string
    {
        return $this->directory() . '/platform.json';
    }

    /**
     * @return array<string, mixed>
     */
    public function read(): array
    {
        $this->ensureExists();

        $decoded = json_decode((string) file_get_contents($this->path()), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function write(array $config): void
    {
        file_put_contents(
            $this->path(),
            json_encode($config, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT),
        );
    }

    public function ensureExists(): void
    {
        if (is_file($this->path())) {
            return;
        }

        $this->write($this->defaults());
    }

    /**
     * @return array<string, mixed>
     */
    public function database(): array
    {
        return Arr::get($this->read(), 'database', []);
    }

    /**
     * @return array<string, mixed>
     */
    public function bootstrap(): array
    {
        return Arr::get($this->read(), 'bootstrap', []);
    }

    /**
     * @return array<string, mixed>
     */
    public function routes(): array
    {
        return Arr::get($this->read(), 'routes', []);
    }

    /**
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        return [
            'version' => 1,
            'database' => [
                'driver' => env('WEBKERNEL_DB_DRIVER', env('DB_CONNECTION', 'sqlite')),
                'database' => env('WEBKERNEL_DB_DATABASE', env('DB_DATABASE', database_path('database.sqlite'))),
                'host' => env('WEBKERNEL_DB_HOST', env('DB_HOST', '127.0.0.1')),
                'port' => env('WEBKERNEL_DB_PORT', env('DB_PORT', '3306')),
                'username' => env('WEBKERNEL_DB_USERNAME', env('DB_USERNAME', '')),
                'password' => env('WEBKERNEL_DB_PASSWORD', env('DB_PASSWORD', '')),
            ],
            'bootstrap' => [
                'ensure_owner' => false,
                'owner' => [],
            ],
            'installed' => false,
            'paths' => [
                'manifest' => 'bootstrap/cache/webkernel/access-manifest.json',
                'row_data' => 'bootstrap/cache/webkernel/data',
            ],
            'routes' => [
                'home' => 'system',
                'home_mode' => 'redirect',
                'modules' => [],
                'panels' => [],
                'sites' => [],
                'layout' => [
                    'nodes' => [],
                ],
            ],
        ];
    }
}
