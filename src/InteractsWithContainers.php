<?php

declare(strict_types=1);

namespace Eznix86\PestPluginTestContainers;

use Eznix86\PestPluginTestContainers\Container\ContainerBuilder;
use Eznix86\PestPluginTestContainers\Container\StartedContainer;
use Throwable;

trait InteractsWithContainers
{
    /**
     * @var array<class-string, array<int, StartedContainer>>
     */
    protected static array $startedContainersByClass = [];

    private bool $teardownCleanupRegistered = false;

    public function container(string $image): ContainerBuilder
    {
        $this->registerTeardownCleanup();

        return new ContainerBuilder(
            $image,
            fn (StartedContainer $container): StartedContainer => $this->registerContainer($container),
            fn (string $message): never => $this->markTestSkipped($message),
        );
    }

    protected function registerContainer(StartedContainer $container): StartedContainer
    {
        if ($container->shouldSkipAutoCleanup()) {
            return $container;
        }

        $container->onStop(function (StartedContainer $stoppedContainer): void {
            $this->removeRegisteredContainer($stoppedContainer);
        });

        self::$startedContainersByClass[static::class] ??= [];
        self::$startedContainersByClass[static::class][] = $container;

        return $container;
    }

    protected function removeRegisteredContainer(StartedContainer $container): void
    {
        $containers = self::$startedContainersByClass[static::class] ?? [];
        $index = array_search($container, $containers, true);

        if ($index === false) {
            return;
        }

        unset($containers[$index]);
        self::$startedContainersByClass[static::class] = array_values($containers);
    }

    protected static function stopRegisteredContainersForClass(string $class): void
    {
        self::$startedContainersByClass[$class] ??= [];

        while ($container = array_pop(self::$startedContainersByClass[$class])) {
            try {
                $container->stop();
            } catch (Throwable) {
                // Ignore cleanup failures to avoid masking test assertions.
            }
        }

        unset(self::$startedContainersByClass[$class]);
    }

    private function registerTeardownCleanup(): void
    {
        if ($this->teardownCleanupRegistered || ! method_exists($this, 'beforeApplicationDestroyed')) {
            return;
        }

        $this->teardownCleanupRegistered = true;

        $this->beforeApplicationDestroyed(function (): void {
            self::stopRegisteredContainersForClass(static::class);
        });
    }
}
