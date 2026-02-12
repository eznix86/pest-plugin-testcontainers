<?php

declare(strict_types=1);

namespace Eznix86\PestPluginTestContainers\ConfigInjectors;

use Eznix86\PestPluginTestContainers\StartedContainer;

final class DatabaseConfigInjector
{
    public static function inject(
        StartedContainer $container,
        string $driver,
        int $port,
        string $databaseName,
        string $username,
        string $password
    ): void {
        $connection = 'testcontainer';

        config([
            "database.connections.{$connection}" => [
                'driver' => $driver,
                'host' => $container->host(),
                'port' => $container->mappedPort($port),
                'database' => $databaseName,
                'username' => $username,
                'password' => $password,
            ],
            'database.default' => $connection,
        ]);
    }
}
