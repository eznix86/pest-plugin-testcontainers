<?php

declare(strict_types=1);

namespace Eznix86\PestPluginTestContainers;

use Closure;
use InvalidArgumentException;
use Testcontainers\Container\GenericContainer;
use Testcontainers\Container\HttpMethod;
use Testcontainers\Wait\WaitForExec;
use Testcontainers\Wait\WaitForHttp;
use Testcontainers\Wait\WaitForLog;
use Throwable;

final readonly class ContainerBuilder
{
    private GenericContainer $container;

    private ProtocolAwareRandomUniquePortAllocator $portAllocator;

    /**
     * @var Closure(StartedContainer): StartedContainer
     */
    private Closure $registerContainer;

    /**
     * @var Closure(string): never
     */
    private Closure $skipTest;

    /**
     * @param  callable(StartedContainer): StartedContainer  $registerContainer
     * @param  callable(string): never  $skipTest
     */
    public function __construct(
        string $image,
        callable $registerContainer,
        callable $skipTest,
    ) {
        $this->container = (new GenericContainer($image))
            ->withPortGenerator(new SaferRandomUniquePortGenerator);
        $this->portAllocator = new ProtocolAwareRandomUniquePortAllocator;
        $this->registerContainer = Closure::fromCallable($registerContainer);
        $this->skipTest = Closure::fromCallable($skipTest);
    }

    /**
     * @param  list<int|string>|array<int|string, int|string>  $ports
     */
    public function ports(array $ports): self
    {
        if (array_is_list($ports)) {
            $containerPorts = array_map(
                static fn (int|string $port): int|string => is_numeric($port) ? (int) $port : $port,
                $ports,
            );

            $hostPorts = array_map(
                $this->portAllocator->allocateForContainerPort(...),
                $containerPorts,
            );

            $this->container
                ->withExposedPorts(...$containerPorts)
                ->withPortGenerator(new FixedPortSequenceGenerator($hostPorts));

            return $this;
        }

        $containerPorts = [];
        $hostPorts = [];

        foreach ($ports as $containerPort => $hostPort) {
            $normalizedContainerPort = is_numeric($containerPort)
                ? (int) $containerPort
                : $containerPort;

            $containerPorts[] = $normalizedContainerPort;
            $hostPorts[] = $this->normalizeHostPort($hostPort, $normalizedContainerPort);
        }

        $this->container
            ->withExposedPorts(...$containerPorts)
            ->withPortGenerator(new FixedPortSequenceGenerator($hostPorts));

        return $this;
    }

    private function normalizeHostPort(mixed $hostPort, int|string $containerPort): int
    {
        if (is_int($hostPort)) {
            return $hostPort;
        }

        if (is_string($hostPort) && ctype_digit($hostPort)) {
            return (int) $hostPort;
        }

        throw new InvalidArgumentException(sprintf(
            'Host port must be an integer (for example ports([%s => 8080])).',
            var_export($containerPort, true),
        ));
    }

    /**
     * @param  array<string, string>  $env
     */
    public function env(array $env): self
    {
        $this->container->withEnvironment($env);

        return $this;
    }

    /**
     * @param  array<string, string>  $labels
     */
    public function labels(array $labels): self
    {
        $this->container->withLabels($labels);

        return $this;
    }

    public function volume(string $sourcePath, string $containerPath): self
    {
        $this->container->withMount($sourcePath, $containerPath);

        return $this;
    }

    /**
     * @param  list<string>  $command
     */
    public function command(array $command): self
    {
        $this->container->withCommand($command);

        return $this;
    }

    /**
     * @param  list<string>  $command
     */
    public function healthcheck(array $command): self
    {
        $this->container->withWait(new WaitForExec($command));

        return $this;
    }

    public function waitForLog(
        string $message,
        bool $regex = false,
        int $timeoutSeconds = 30,
        int $pollIntervalMilliseconds = 500,
    ): self {
        $this->container->withWait(new WaitForLog(
            $message,
            $regex,
            $timeoutSeconds * 1000,
            $pollIntervalMilliseconds,
        ));

        return $this;
    }

    /**
     * @param  HttpMethod|value-of<HttpMethod>  $method
     * @param  array<string, string>  $headers
     */
    public function waitForHttp(
        string $path = '/',
        ?int $port = null,
        int $expectedStatusCode = 200,
        HttpMethod|string $method = HttpMethod::GET,
        bool $https = false,
        bool $allowInsecure = false,
        int $timeoutSeconds = 30,
        int $pollIntervalMilliseconds = 500,
        int $readTimeoutMilliseconds = 1000,
        array $headers = [],
    ): self {
        $wait = (new WaitForHttp(
            $port,
            $timeoutSeconds * 1000,
            $pollIntervalMilliseconds,
        ))
            ->withMethod($method)
            ->withPath($path)
            ->withExpectedStatusCode($expectedStatusCode)
            ->withReadTimeout($readTimeoutMilliseconds)
            ->withHeaders($headers);

        if ($https) {
            $wait->usingHttps();
        }

        if ($allowInsecure) {
            $wait->allowInsecure();
        }

        $this->container->withWait($wait);

        return $this;
    }

    public function waitForPort(
        ?int $port = null,
        int $timeoutSeconds = 30,
        int $pollIntervalMilliseconds = 500,
    ): self {
        $this->container->withWait(new WaitForPort(
            $port,
            $timeoutSeconds * 1000,
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
    ): self {
        $this->container->withWait(new WaitForExec(
            $command,
            null,
            $timeoutSeconds * 1000,
            $pollIntervalMilliseconds,
        ));

        return $this;
    }

    public function start(): StartedContainer
    {
        try {
            $startedContainer = new StartedContainer($this->container->start());

            return ($this->registerContainer)($startedContainer);
        } catch (Throwable $exception) {
            ($this->skipTest)('Docker is unavailable for container test: '.$exception->getMessage());
        }
    }
}
