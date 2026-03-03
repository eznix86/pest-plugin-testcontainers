<?php

declare(strict_types=1);

namespace Eznix86\PestPluginTestContainers\Concerns;

use Eznix86\PestPluginTestContainers\ConfigInjectors\StorageConfigInjector;
use Eznix86\PestPluginTestContainers\Container\StartedContainer;
use Eznix86\PestPluginTestContainers\Values\StorageAccessMode;

trait ConfiguresStorage
{
    private StorageAccessMode $storageAccessMode = StorageAccessMode::Private;

    public function asStorage(?string $disk = null): static
    {
        $bucket = 'test';

        $this->addConfigInjector(function (StartedContainer $container) use ($disk, $bucket): void {
            $resolvedDisk = $disk ?? $container->resolvedConnectionName();

            StorageConfigInjector::inject(
                $container,
                $this->getDefaultPort(),
                $this->accessKey(),
                $this->secretKey(),
                $resolvedDisk,
                $bucket,
                $this->storageRegion(),
                $this->storageAccessMode,
            );
        });

        $this->addPostStartHook(function (StartedContainer $container) use ($bucket): void {
            $this->configureStorageBackend($container, $bucket, $this->storageAccessMode);
        });

        return $this;
    }

    public function public(): static
    {
        $this->storageAccessMode = StorageAccessMode::Public;

        return $this;
    }

    public function private(): static
    {
        $this->storageAccessMode = StorageAccessMode::Private;

        return $this;
    }

    protected function configureStorageBackend(StartedContainer $container, string $bucket, StorageAccessMode $mode): void {}

    protected function storageRegion(): string
    {
        return 'us-east-1';
    }

    abstract protected function addPostStartHook(callable $hook): void;

    abstract protected function getDefaultPort(): int;

    abstract public function accessKey(): string;

    abstract public function secretKey(): string;
}
