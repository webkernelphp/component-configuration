<?php

declare(strict_types=1);

namespace Webkernel\StdConf\Bootstrappers;

use Illuminate\Contracts\Config\Repository;
use RuntimeException;

/**
 * Reads the installation state written by the Webkernel installer screen
 * and injects the chosen database connection into the Laravel config at runtime.
 *
 * The installer writes storage/webkernel/database.json with this structure:
 * {
 *     "driver":   "sqlite|mysql|pgsql",
 *     "database": "/absolute/path/or/db-name",
 *     "host":     "127.0.0.1",
 *     "port":     "3306",
 *     "username": "root",
 *     "password": ""
 * }
 *
 * This bootstrapper is called from StdConfServiceProvider::boot()
 * before any query runs, giving modules a single stable connection name:
 * 'webkernel_primary'.
 */
final class DatabaseBootstrapper
{
    private const STATE_FILE = 'webkernel/database.json';
    private const CONNECTION_NAME = 'webkernel_primary';
    private const SUPPORTED_DRIVERS = ['sqlite', 'mysql', 'pgsql'];

    public function __construct(private readonly Repository $config) {}

    public function apply(): void
    {
        $path = storage_path(self::STATE_FILE);

        if (!file_exists($path)) {
            // Not installed yet. The installer screen handles this state.
            return;
        }

        $raw = file_get_contents($path);

        if ($raw === false) {
            throw new RuntimeException('Webkernel: cannot read ' . $path);
        }

        $state = json_decode($raw, true);

        if (!is_array($state) || empty($state['driver'])) {
            throw new RuntimeException('Webkernel: database.json is malformed.');
        }

        $driver = $state['driver'];

        if (!in_array($driver, self::SUPPORTED_DRIVERS, true)) {
            throw new RuntimeException('Webkernel: unsupported driver "' . $driver . '".');
        }

        $this->config->set(
            'database.connections.' . self::CONNECTION_NAME,
            $this->buildConnectionConfig($driver, $state)
        );

        // Only override the default connection when the app has not already
        // set a non-sqlite default (so dev environments using .env are not disturbed).
        if ($this->config->get('database.default') === 'sqlite') {
            $this->config->set('database.default', self::CONNECTION_NAME);
        }
    }

    /** @param $state */
    private function buildConnectionConfig(string $driver, array $state): array
    {
        $base = [
            'driver' => $driver,
            'prefix' => '',
        ];

        return match ($driver) {
            'sqlite' => array_merge($base, [
                'database'             => $state['database'],
                'foreign_key_constraints' => true,
            ]),
            'mysql' => array_merge($base, [
                'host'      => $state['host'] ?? '127.0.0.1',
                'port'      => $state['port'] ?? '3306',
                'database'  => $state['database'],
                'username'  => $state['username'] ?? '',
                'password'  => $state['password'] ?? '',
                'charset'   => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'strict'    => true,
                'engine'    => null,
            ]),
            'pgsql' => array_merge($base, [
                'host'        => $state['host'] ?? '127.0.0.1',
                'port'        => $state['port'] ?? '5432',
                'database'    => $state['database'],
                'username'    => $state['username'] ?? '',
                'password'    => $state['password'] ?? '',
                'charset'     => 'utf8',
                'search_path' => 'public',
                'sslmode'     => 'prefer',
            ]),
        };
    }
}
