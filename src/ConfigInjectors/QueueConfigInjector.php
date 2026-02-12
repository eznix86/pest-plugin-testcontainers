<?php

declare(strict_types=1);

namespace Eznix86\PestPluginTestContainers\ConfigInjectors;

use Eznix86\PestPluginTestContainers\Container\StartedContainer;

final class QueueConfigInjector
{
    public static function inject(
        StartedContainer $container,
        string $driver,
        int $defaultPort,
        ?string $connection = null
    ): void {
        $connection ??= sprintf('testcontainer_%s', bin2hex(random_bytes(6)));
        $host = $container->host();
        $mappedPort = $container->mappedPort($defaultPort);

        $config = match ($driver) {
            'redis' => [
                'driver' => 'redis',
                'connection' => $connection,
                'queue' => 'default',
                'retry_after' => 90,
            ],
            'database' => [
                'driver' => 'database',
                'connection' => $connection,
                'table' => 'jobs',
                'queue' => 'default',
                'retry_after' => 90,
            ],
            default => [
                'driver' => $driver,
                'host' => $host,
                'port' => $mappedPort,
            ],
        };

        $queueConfig = [
            "queue.connections.{$connection}" => $config,
            'queue.default' => $connection,
        ];

        if ($driver === 'redis') {
            $queueConfig["database.redis.{$connection}"] = [
                'host' => $host,
                'port' => $mappedPort,
                'database' => 0,
            ];
        }

        config($queueConfig);
    }
}
