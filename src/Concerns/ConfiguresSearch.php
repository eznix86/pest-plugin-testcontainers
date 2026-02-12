<?php

declare(strict_types=1);

namespace Eznix86\PestPluginTestContainers\Concerns;

use Eznix86\PestPluginTestContainers\ConfigInjectors\SearchConfigInjector;
use Eznix86\PestPluginTestContainers\StartedContainer;

trait ConfiguresSearch
{
    public function asSearch(): static
    {
        $port = $this->getDefaultPort();
        $config = $this->getSearchConfig();

        $this->addConfigInjector(function (StartedContainer $container) use ($port, $config): void {
            SearchConfigInjector::inject(
                $container,
                $this->getDriverName(),
                $port,
                $config
            );
        });

        return $this;
    }

    abstract protected function getDriverName(): string;

    abstract protected function getDefaultPort(): int;

    /**
     * @return array<string, mixed>
     */
    protected function getSearchConfig(): array
    {
        return [];
    }
}
