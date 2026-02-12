<?php

declare(strict_types=1);

namespace Eznix86\PestPluginTestContainers\Concerns;

use Eznix86\PestPluginTestContainers\ConfigInjectors\StorageConfigInjector;
use Eznix86\PestPluginTestContainers\Container\StartedContainer;

trait ConfiguresStorage
{
    public function asStorage(?string $disk = null): static
    {
        $this->addConfigInjector(function (StartedContainer $container) use ($disk): void {
            $resolvedDisk = $disk ?? $container->resolvedConnectionName();

            StorageConfigInjector::inject(
                $container,
                $this->getDefaultPort(),
                $this->username(),
                $this->password(),
                $resolvedDisk
            );
        });

        return $this;
    }

    abstract protected function getDefaultPort(): int;

    abstract public function username(): string;

    abstract public function password(): string;
}
