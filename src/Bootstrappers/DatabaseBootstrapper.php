<?php

declare(strict_types=1);

namespace Webkernel\Component\Config\Bootstrappers;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Webkernel\Component\Config\Platform\PlatformConfigStore;

/**
 * Injects webkernel_primary from bootstrap/cache/webkernel/platform.json.
 *
 * Creates the configured sqlite file when missing and uses file-backed session,
 * cache, and queue drivers until the infrastructure tables exist.
 */
final readonly class DatabaseBootstrapper
{
    private const string LEGACY_STATE_FILE = 'webkernel/database.json';
    private const string CONNECTION_NAME = 'webkernel_primary';
    private const array SUPPORTED_DRIVERS = ['sqlite', 'mysql', 'pgsql'];

    public function __construct(
        private Repository $config,
        private ?PlatformConfigStore $platformConfig = null,
    ) {}

    public function apply(): void
    {
        $state = $this->resolveDatabaseState();

        if ($state === null) {
            $this->aliasDefaultConnection();
            $this->applyFileBackedDriversWhenNeeded();

            return;
        }

        $driver = $state['driver'] ?? null;

        if (! is_string($driver) || ! in_array($driver, self::SUPPORTED_DRIVERS, true)) {
            throw new RuntimeException('Webkernel: unsupported or missing database driver.');
        }

        if ($driver === 'sqlite') {
            $this->ensureSqliteFileExists($state);
        }

        $this->config->set(
            'database.connections.' . self::CONNECTION_NAME,
            $this->buildConnectionConfig($driver, $state),
        );

        if ($this->config->get('database.default') === 'sqlite') {
            $this->config->set('database.default', self::CONNECTION_NAME);
        }

        $this->applyFileBackedDriversWhenNeeded();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveDatabaseState(): ?array
    {
        $store = $this->platformConfig ?? new PlatformConfigStore();
        $database = $store->database();

        if (($database['driver'] ?? null) !== null) {
            return $database;
        }

        $legacyPath = storage_path(self::LEGACY_STATE_FILE);

        if (! is_file($legacyPath)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($legacyPath), true);

        return is_array($decoded) ? $decoded : null;
    }

    private function aliasDefaultConnection(): void
    {
        $default = $this->config->get('database.default');
        $defaultConfig = $this->config->get('database.connections.' . $default);

        if (! is_array($defaultConfig)) {
            return;
        }

        if (($defaultConfig['driver'] ?? null) === 'sqlite') {
            $this->ensureSqliteFileExists($defaultConfig);
        }

        $this->config->set(
            'database.connections.' . self::CONNECTION_NAME,
            $defaultConfig,
        );
    }

    private function applyFileBackedDriversWhenNeeded(): void
    {
        $this->applySessionDriver();
        $this->applyCacheDriver();
        $this->applyQueueDriver();
    }

    private function applySessionDriver(): void
    {
        if ($this->config->get('session.driver') !== 'database') {
            return;
        }

        if ($this->hasTable((string) $this->config->get('session.table', 'sessions'))) {
            return;
        }

        $this->config->set('session.driver', 'file');
    }

    private function applyCacheDriver(): void
    {
        if ($this->config->get('cache.default') !== 'database') {
            return;
        }

        $store = $this->config->get('cache.stores.database');

        if (! is_array($store)) {
            return;
        }

        $table = (string) ($store['table'] ?? 'cache');

        if ($this->hasTable($table, $this->resolveCacheConnection())) {
            return;
        }

        $this->config->set('cache.default', 'file');
    }

    private function applyQueueDriver(): void
    {
        if ($this->config->get('queue.default') !== 'database') {
            return;
        }

        $connection = $this->config->get('queue.connections.database');

        if (! is_array($connection)) {
            return;
        }

        $table = (string) ($connection['table'] ?? 'jobs');

        if ($this->hasTable($table, $this->resolveQueueConnection())) {
            return;
        }

        $this->config->set('queue.default', 'sync');
    }

    private function resolveCacheConnection(): string
    {
        $store = $this->config->get('cache.stores.database');

        if (! is_array($store)) {
            return self::CONNECTION_NAME;
        }

        $connection = $store['connection'] ?? null;

        if (is_string($connection) && $connection !== '') {
            return $connection;
        }

        return (string) ($this->config->get('database.default') ?? self::CONNECTION_NAME);
    }

    private function resolveQueueConnection(): string
    {
        $connection = $this->config->get('queue.connections.database');

        if (! is_array($connection)) {
            return self::CONNECTION_NAME;
        }

        $name = $connection['connection'] ?? null;

        if (is_string($name) && $name !== '') {
            return $name;
        }

        return (string) ($this->config->get('database.default') ?? self::CONNECTION_NAME);
    }

    private function hasTable(string $table, ?string $connection = null): bool
    {
        try {
            $connection ??= self::CONNECTION_NAME;

            if (! is_array($this->config->get('database.connections.' . $connection))) {
                return false;
            }

            return Schema::connection($connection)->hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function ensureSqliteFileExists(array $state): void
    {
        $path = $state['database'] ?? null;

        if (! is_string($path) || $path === '' || $path === ':memory:') {
            return;
        }

        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if (is_file($path)) {
            return;
        }

        touch($path);
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function buildConnectionConfig(string $driver, array $state): array
    {
        $base = [
            'driver' => $driver,
            'prefix' => '',
        ];

        return match ($driver) {
            'sqlite' => array_merge($base, [
                'database' => $state['database'],
                'foreign_key_constraints' => true,
            ]),
            'mysql' => array_merge($base, [
                'host' => $state['host'] ?? '127.0.0.1',
                'port' => $state['port'] ?? '3306',
                'database' => $state['database'],
                'username' => $state['username'] ?? '',
                'password' => $state['password'] ?? '',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'strict' => true,
                'engine' => null,
            ]),
            'pgsql' => array_merge($base, [
                'host' => $state['host'] ?? '127.0.0.1',
                'port' => $state['port'] ?? '5432',
                'database' => $state['database'],
                'username' => $state['username'] ?? '',
                'password' => $state['password'] ?? '',
                'charset' => 'utf8',
                'search_path' => 'public',
                'sslmode' => 'prefer',
            ]),
        };
    }
}
