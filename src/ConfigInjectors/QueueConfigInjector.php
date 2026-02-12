<?php

declare(strict_types=1);

namespace Eznix86\PestPluginTestContainers\ConfigInjectors;

use Eznix86\PestPluginTestContainers\StartedContainer;

final class QueueConfigInjector
{
    public static function inject(
        StartedContainer $container,
        string $driver,
        int $defaultPort,
        ?string $connection = null
    ): void {
        $connection ??= 'testcontainer';

        $config = match ($driver) {
            'redis' => [
                'driver' => 'redis',
                'connection' => 'testcontainer',
                'queue' => 'default',
                'retry_after' => 90,
            ],
            'database' => [
                'driver' => 'database',
                'connection' => 'testcontainer',
                'table' => 'jobs',
                'queue' => 'default',
                'retry_after' => 90,
            ],
            default => [
                'driver' => $driver,
                'host' => $container->host(),
                'port' => $container->mappedPort($defaultPort),
            ],
        };

        $queueConfig = [
            "queue.connections.{$connection}" => $config,
            'queue.default' => $connection,
        ];

        if ($driver === 'redis') {
            $queueConfig['database.redis.testcontainer'] = [
                'host' => $container->host(),
                'port' => $container->mappedPort($defaultPort),
                'database' => 0,
            ];
        }

        config($queueConfig);
    }
}
