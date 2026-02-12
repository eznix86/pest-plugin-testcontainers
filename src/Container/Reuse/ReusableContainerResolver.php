<?php

declare(strict_types=1);

namespace Eznix86\PestPluginTestContainers\Container\Reuse;

use Docker\API\Exception\ContainerInspectNotFoundException;
use Docker\API\Exception\ContainerCreateConflictException;
use Docker\API\Model\ContainersIdJsonGetResponse200;
use Eznix86\PestPluginTestContainers\Container\StartedContainer;
use Eznix86\PestPluginTestContainers\Container\StartedGenericContainer;
use Testcontainers\ContainerClient\DockerContainerClient;
use Throwable;

final class ReusableContainerResolver
{
    private const int MAX_WAIT_ATTEMPTS = 100;

    private const int WAIT_DELAY_MICROSECONDS = 100_000;

    public function resolveRunning(string $name): ?StartedContainer
    {
        $docker = DockerContainerClient::getDockerClient();

        try {
            /** @var ContainersIdJsonGetResponse200|null $inspect */
            $inspect = $docker->containerInspect($name);
        } catch (ContainerInspectNotFoundException) {
            return null;
        }

        if ($inspect?->getState()?->getStatus() !== 'running') {
            return null;
        }

        $containerId = $inspect->getId();

        if (! is_string($containerId) || $containerId === '') {
            return null;
        }

        return (new StartedContainer(new StartedGenericContainer($containerId, $docker)))->skipAutoCleanup();
    }

    public function waitUntilRunning(string $name): ?StartedContainer
    {
        $attempts = 0;

        while ($attempts < self::MAX_WAIT_ATTEMPTS) {
            $container = $this->resolveRunningOrStart($name);

            if ($container instanceof StartedContainer) {
                return $container;
            }

            usleep(self::WAIT_DELAY_MICROSECONDS);
            $attempts++;
        }

        return null;
    }

    public function resolveRunningOrStart(string $name): ?StartedContainer
    {
        $docker = DockerContainerClient::getDockerClient();

        try {
            /** @var ContainersIdJsonGetResponse200|null $inspect */
            $inspect = $docker->containerInspect($name);
        } catch (ContainerInspectNotFoundException) {
            return null;
        }

        if ($inspect?->getState()?->getStatus() === 'running') {
            $containerId = $inspect->getId();

            if (! is_string($containerId) || $containerId === '') {
                return null;
            }

            return (new StartedContainer(new StartedGenericContainer($containerId, $docker)))->skipAutoCleanup();
        }

        $containerId = $inspect?->getId();

        if (! is_string($containerId) || $containerId === '') {
            return null;
        }

        try {
            $docker->containerStart($containerId);
        } catch (Throwable) {
            return null;
        }

        return $this->resolveRunning($name);
    }

    public function isNameConflict(Throwable $exception): bool
    {
        for ($current = $exception; $current instanceof Throwable; $current = $current->getPrevious()) {
            $message = strtolower($current->getMessage());

            if ($current instanceof ContainerCreateConflictException
                || str_contains($message, 'is already in use by container')
                || str_contains($message, 'conflict')) {
                return true;
            }
        }

        return false;
    }
}
