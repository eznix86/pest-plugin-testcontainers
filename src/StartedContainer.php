<?php

declare(strict_types=1);

namespace Eznix86\PestPluginTestContainers;

use Closure;
use Docker\API\Model\ExecIdJsonGetResponse200;
use RuntimeException;
use Testcontainers\Container\StartedGenericContainer as UnpatchedGenericContainer;
use Throwable;

final class StartedContainer
{
    /**
     * @var Closure(self): void|null
     */
    private ?Closure $onStop = null;

    private bool $stopped = false;

    public function __construct(
        UnpatchedGenericContainer $container,
    ) {
        $this->container = $container instanceof StartedGenericContainer
            ? $container
            : new StartedGenericContainer($container->getId(), $container->getClient());
    }

    private readonly StartedGenericContainer $container;

    public function host(): string
    {
        return $this->container->getHost();
    }

    public function getGeneratedPortFor(int $containerPort): int
    {
        $attempts = 0;
        $maxAttempts = 30;
        $lastException = null;

        while ($attempts < $maxAttempts) {
            try {
                return $this->container->getMappedPort($containerPort);
            } catch (Throwable $exception) {
                $message = $exception->getMessage();

                $isTransientDockerInspectRace = str_contains($message, 'foreach() argument must be of type array|object, null given');
                $isTransientPortBindingRace = str_contains($message, 'No host port left to assign for mapped container ports.');

                if (! $isTransientDockerInspectRace && ! $isTransientPortBindingRace) {
                    throw $exception;
                }

                $lastException = $exception;
                usleep(100_000);
                $attempts++;
            }
        }

        throw new RuntimeException(
            sprintf('Mapped port for container port %d was not available after %d attempts.', $containerPort, $maxAttempts),
            previous: $lastException,
        );
    }

    public function mappedPort(int $port): int
    {
        return $this->getGeneratedPortFor($port);
    }

    public function logs(): string
    {
        return $this->container->logs();
    }

    public function rawLogs(): string
    {
        return $this->container->logsRaw();
    }

    public function stop(): void
    {
        if ($this->stopped) {
            return;
        }

        $containerId = $this->container->getId();
        $client = $this->container->getClient();

        $client->containerStop($containerId, ['t' => 0]);
        $client->containerDelete($containerId, ['force' => true]);

        $this->stopped = true;
        $this->onStop?->__invoke($this);
    }

    public function raw(): StartedGenericContainer
    {
        return $this->container;
    }

    /**
     * @param  list<string>  $command
     */
    public function exec(array $command): ExecResult
    {
        return $this->execResult($command, false);
    }

    /**
     * @param  list<string>  $command
     */
    public function execRaw(array $command): ExecResult
    {
        return $this->execResult($command, true);
    }

    /**
     * @param  list<string>  $command
     */
    private function execResult(array $command, bool $rawOutput): ExecResult
    {
        $output = $rawOutput
            ? $this->container->execRaw($command)
            : $this->container->exec($command);

        $execId = $this->container->getLastExecId();

        $execInspect = $execId !== null
            ? $this->container->getClient()->execInspect($execId)
            : null;

        $exitCode = $execInspect instanceof ExecIdJsonGetResponse200
            ? $execInspect->getExitCode()
            : null;

        throw_unless(is_int($exitCode), RuntimeException::class, 'Unable to determine exit code for container exec command.');

        return new ExecResult($command, $output, $exitCode);
    }

    /**
     * @param  string|list<string>  $subject
     */
    public function expect(string|array $subject): ContainerExpectation
    {
        return new ContainerExpectation($this, $subject);
    }

    /**
     * @param  callable(self): void  $callback
     */
    public function onStop(callable $callback): self
    {
        $this->onStop = Closure::fromCallable($callback);

        return $this;
    }
}
