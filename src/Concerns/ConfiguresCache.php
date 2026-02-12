<?php

declare(strict_types=1);

namespace Eznix86\PestPluginTestContainers\Concerns;

use Eznix86\PestPluginTestContainers\ConfigInjectors\CacheConfigInjector;
use Eznix86\PestPluginTestContainers\StartedContainer;

trait ConfiguresCache
{
    public function asCache(): static
    {
        $this->addConfigInjector(function (StartedContainer $container): void {
            CacheConfigInjector::inject(
                $container,
                $this->getDefaultPort()
            );
        });

        return $this;
    }

    abstract protected function getDefaultPort(): int;
}
