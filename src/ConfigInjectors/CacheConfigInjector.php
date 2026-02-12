<?php

declare(strict_types=1);

namespace Eznix86\PestPluginTestContainers\ConfigInjectors;

use Eznix86\PestPluginTestContainers\Container\StartedContainer;

final class CacheConfigInjector
{
    public static function inject(StartedContainer $container, int $defaultPort, ?string $connection = null): void
    {
        $connection ??= sprintf('testcontainer_%s', bin2hex(random_bytes(6)));

        config([
            'cache.default' => $connection,
            "cache.stores.{$connection}" => [
                'driver' => 'redis',
                'connection' => $connection,
            ],
            "database.redis.{$connection}" => [
                'host' => $container->host(),
                'port' => $container->mappedPort($defaultPort),
                'database' => 0,
            ],
        ]);
    }

    public static function injectDatabase(string $store, string $connection): void
    {
        config([
            'cache.default' => $store,
            "cache.stores.{$store}" => [
                'driver' => 'database',
                'connection' => $connection,
                'table' => 'cache',
                'lock_connection' => $connection,
                'lock_table' => 'cache_locks',
            ],
        ]);
    }
}
