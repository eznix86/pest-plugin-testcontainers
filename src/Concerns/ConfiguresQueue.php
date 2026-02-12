<?php

declare(strict_types=1);

namespace Eznix86\PestPluginTestContainers\Concerns;

use Eznix86\PestPluginTestContainers\ConfigInjectors\QueueConfigInjector;
use Eznix86\PestPluginTestContainers\Container\StartedContainer;

trait ConfiguresQueue
{
    public function asQueue(?string $connection = null): static
    {
        $this->addConfigInjector(function (StartedContainer $container) use ($connection): void {
            QueueConfigInjector::inject(
                $container,
                $this->getQueueDriverName(),
                $this->getDefaultPort(),
                $connection
            );
        });

        return $this;
    }

    abstract protected function getQueueDriverName(): string;

    abstract protected function getDefaultPort(): int;
}
