<?php

declare(strict_types=1);

namespace Eznix86\PestPluginTestContainers\Container;

use Closure;
use Docker\API\Model\ExecIdJsonGetResponse200;
use RuntimeException;
use Testcontainers\Container\StartedGenericContainer as UnpatchedGenericContainer;
use Throwable;

final class StartedContainer
{
    private const int MAPPED_PORT_MAX_ATTEMPTS = 6;

    private const int MAPPED_PORT_RETRY_BASE_DELAY_MICROSECONDS = 100_000;

    private const int MAPPED_PORT_RETRY_MAX_DELAY_MICROSECONDS = 2_000_000;

    private const string DOCKER_INSPECT_RACE_ERROR = 'foreach() argument must be of type array|object, null given';

    private const string PORT_BINDING_RACE_ERROR = 'No host port left to assign for mapped container ports.';

    /**
     * @var Closure(self): void|null
     */
    private ?Closure $onStop = null;

    private bool $stopped = false;

    private bool $skipAutoCleanup = false;

    private ?string $connectionName = null;

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
        $lastException = null;

        while ($attempts < self::MAPPED_PORT_MAX_ATTEMPTS) {
            try {
                return $this->container->getMappedPort($containerPort);
            } catch (Throwable $exception) {
                if (! $this->isTransientMappedPortRace($exception)) {
                    throw $exception;
                }

                $lastException = $exception;
                usleep($this->mappedPortRetryDelayForAttempt($attempts));
                $attempts++;
            }
        }

        throw new RuntimeException(
            sprintf('Mapped port for container port %d was not available after %d attempts.', $containerPort, self::MAPPED_PORT_MAX_ATTEMPTS),
            previous: $lastException,
        );
    }

    private function isTransientMappedPortRace(Throwable $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, self::DOCKER_INSPECT_RACE_ERROR)
            || str_contains($message, self::PORT_BINDING_RACE_ERROR);
    }

    private function mappedPortRetryDelayForAttempt(int $attempt): int
    {
        $delay = self::MAPPED_PORT_RETRY_BASE_DELAY_MICROSECONDS * (1 << $attempt);

        return min($delay, self::MAPPED_PORT_RETRY_MAX_DELAY_MICROSECONDS);
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

    public function skipAutoCleanup(): self
    {
        $this->skipAutoCleanup = true;

        return $this;
    }

    public function shouldSkipAutoCleanup(): bool
    {
        return $this->skipAutoCleanup;
    }

    public function withConnectionName(string $connectionName): self
    {
        $this->connectionName = $connectionName;

        return $this;
    }

    public function connectionName(): ?string
    {
        return $this->connectionName;
    }

    public function resolvedConnectionName(): string
    {
        if (! is_string($this->connectionName) || $this->connectionName === '') {
            throw new RuntimeException('Connection name was not initialized on the started container.');
        }

        return $this->connectionName;
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
