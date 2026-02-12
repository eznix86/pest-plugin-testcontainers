<?php

declare(strict_types=1);

namespace Eznix86\PestPluginTestContainers\ConfigInjectors;

use Eznix86\PestPluginTestContainers\Container\StartedContainer;

final class SearchConfigInjector
{
    /**
     * @param  array<string, mixed>  $additionalConfig
     */
    public static function inject(
        StartedContainer $container,
        string $driver,
        int $port,
        array $additionalConfig = []
    ): void {
        $config = array_merge([
            'host' => $container->host(),
            'port' => $container->mappedPort($port),
        ], $additionalConfig);

        config([
            'scout.driver' => $driver,
            "scout.{$driver}" => $config,
        ]);
    }
}
