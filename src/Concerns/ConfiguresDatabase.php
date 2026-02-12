<?php

declare(strict_types=1);

namespace Eznix86\PestPluginTestContainers\Concerns;

use Eznix86\PestPluginTestContainers\ConfigInjectors\DatabaseConfigInjector;
use Eznix86\PestPluginTestContainers\Container\StartedContainer;

trait ConfiguresDatabase
{
    protected ?string $databaseName = null;

    public function asDatabase(?string $databaseName = null): static
    {
        if ($databaseName !== null) {
            $this->databaseName = $databaseName;
        }

        $this->addConfigInjector(function (StartedContainer $container): void {
            DatabaseConfigInjector::inject(
                $container,
                $this->getDriverName(),
                $this->getDefaultPort(),
                $this->databaseName(),
                $this->username(),
                $this->password()
            );
        });

        return $this;
    }

    public function databaseName(): string
    {
        return $this->databaseName ??= $this->generateDatabaseName();
    }

    protected function generateDatabaseName(): string
    {
        return 'test_'.bin2hex(random_bytes(6));
    }

    abstract protected function getDriverName(): string;

    abstract protected function getDefaultPort(): int;
}
