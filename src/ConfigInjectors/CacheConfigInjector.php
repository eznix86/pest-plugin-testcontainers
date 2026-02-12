<?php

declare(strict_types=1);

namespace Eznix86\PestPluginTestContainers\ConfigInjectors;

use Eznix86\PestPluginTestContainers\StartedContainer;

final class CacheConfigInjector
{
    public static function inject(StartedContainer $container, int $defaultPort): void
    {
        config([
            'cache.default' => 'redis',
            'cache.stores.redis.connection' => 'testcontainer',
            'database.redis.testcontainer' => [
                'host' => $container->host(),
                'port' => $container->mappedPort($defaultPort),
                'database' => 0,
            ],
        ]);
    }
}
