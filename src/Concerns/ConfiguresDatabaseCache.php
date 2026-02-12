<?php

declare(strict_types=1);

namespace Eznix86\PestPluginTestContainers\Concerns;

use Eznix86\PestPluginTestContainers\ConfigInjectors\CacheConfigInjector;
use Eznix86\PestPluginTestContainers\Container\StartedContainer;

trait ConfiguresDatabaseCache
{
    public function asCache(): static
    {
        $this->asDatabase();

        $this->addConfigInjector(static function (StartedContainer $container): void {
            $connection = $container->resolvedConnectionName();

            CacheConfigInjector::injectDatabase($connection, $connection);
        });

        return $this;
    }

    abstract public function asDatabase(?string $databaseName = null): static;
}
