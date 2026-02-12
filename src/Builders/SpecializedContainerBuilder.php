<?php

declare(strict_types=1);

namespace Eznix86\PestPluginTestContainers\Builders;

use Closure;
use Eznix86\PestPluginTestContainers\Concerns\HasCustomImage;
use Eznix86\PestPluginTestContainers\ContainerBuilder;
use Eznix86\PestPluginTestContainers\StartedContainer;

abstract class SpecializedContainerBuilder
{
    use HasCustomImage;

    /**
     * @var list<Closure(ContainerBuilder): mixed>
     */
    private array $builderOperations = [];

    /**
     * @var list<callable(StartedContainer): void>
     */
    protected array $configInjectors = [];

    public function __construct(protected ContainerBuilder $builder, private string $helperName) {}

    protected function setImage(string $image): void
    {
        $this->builder = \Eznix86\PestPluginTestContainers\resolveBaseContainerBuilder($this->helperName, $image);

        foreach ($this->builderOperations as $operation) {
            $operation($this->builder);
        }
    }

    /**
     * @param  callable(StartedContainer): void  $injector
     */
    protected function addConfigInjector(callable $injector): void
    {
        /** @var Closure(StartedContainer): void $closure */
        $closure = Closure::fromCallable($injector);
        $this->configInjectors[] = $closure;
    }

    /**
     * @param  list<int|string>|array<int|string, int|string>  $ports
     */
    public function ports(array $ports): static
    {
        $this->recordBuilderOperation(static fn (ContainerBuilder $builder): mixed => $builder->ports($ports));

        return $this;
    }

    /**
     * @param  array<string, string>  $env
     */
    public function env(array $env): static
    {
        $this->recordBuilderOperation(static fn (ContainerBuilder $builder): mixed => $builder->env($env));

        return $this;
    }

    /**
     * @param  array<string, string>  $labels
     */
    public function labels(array $labels): static
    {
        $this->recordBuilderOperation(static fn (ContainerBuilder $builder): mixed => $builder->labels($labels));

        return $this;
    }

    public function volume(string $sourcePath, string $containerPath): static
    {
        $this->recordBuilderOperation(static fn (ContainerBuilder $builder): mixed => $builder->volume($sourcePath, $containerPath));

        return $this;
    }

    /**
     * @param  list<string>  $command
     */
    public function command(array $command): static
    {
        $this->recordBuilderOperation(static fn (ContainerBuilder $builder): mixed => $builder->command($command));

        return $this;
    }

    /**
     * @param  list<string>  $command
     */
    public function healthcheck(array $command): static
    {
        $this->recordBuilderOperation(static fn (ContainerBuilder $builder): mixed => $builder->healthcheck($command));

        return $this;
    }

    public function waitForLog(
        string $message,
        bool $regex = false,
        int $timeoutSeconds = 30,
        int $pollIntervalMilliseconds = 500,
    ): static {
        $this->recordBuilderOperation(static fn (ContainerBuilder $builder): mixed => $builder->waitForLog(
            $message,
            $regex,
            $timeoutSeconds,
            $pollIntervalMilliseconds,
        ));

        return $this;
    }

    /**
     * @param  array<string, string>  $headers
     * @param  'DELETE'|'GET'|'HEAD'|'OPTIONS'|'POST'|'PUT'|\Testcontainers\Container\HttpMethod  $method
     */
    public function waitForHttp(
        string $path = '/',
        ?int $port = null,
        int $expectedStatusCode = 200,
        string|object $method = 'GET',
        bool $https = false,
        bool $allowInsecure = false,
        int $timeoutSeconds = 30,
        int $pollIntervalMilliseconds = 500,
        int $readTimeoutMilliseconds = 1000,
        array $headers = [],
    ): static {
        $this->recordBuilderOperation(static fn (ContainerBuilder $builder): mixed => $builder->waitForHttp(
            $path,
            $port,
            $expectedStatusCode,
            $method,
            $https,
            $allowInsecure,
            $timeoutSeconds,
            $pollIntervalMilliseconds,
            $readTimeoutMilliseconds,
            $headers,
        ));

        return $this;
    }

    public function waitForPort(
        ?int $port = null,
        int $timeoutSeconds = 30,
        int $pollIntervalMilliseconds = 500,
    ): static {
        $this->recordBuilderOperation(static fn (ContainerBuilder $builder): mixed => $builder->waitForPort(
            $port,
            $timeoutSeconds,
            $pollIntervalMilliseconds,
        ));

        return $this;
    }

    /**
     * @param  list<string>  $command
     */
    public function waitForCommand(
        array $command,
        int $timeoutSeconds = 30,
        int $pollIntervalMilliseconds = 500,
    ): static {
        $this->recordBuilderOperation(static fn (ContainerBuilder $builder): mixed => $builder->waitForCommand(
            $command,
            $timeoutSeconds,
            $pollIntervalMilliseconds,
        ));

        return $this;
    }

    public function start(): StartedContainer
    {
        $this->prepareContainer();

        $container = $this->builder->start();

        foreach ($this->configInjectors as $injector) {
            $injector($container);
        }

        return $container;
    }

    /**
     * @param  callable(ContainerBuilder): mixed  $operation
     */
    private function recordBuilderOperation(callable $operation): void
    {
        /** @var Closure(ContainerBuilder): mixed $operationClosure */
        $operationClosure = Closure::fromCallable($operation);
        $this->builderOperations[] = $operationClosure;
        $operationClosure($this->builder);
    }

    protected function prepareContainer(): void {}
}
