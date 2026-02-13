<?php

declare(strict_types=1);

namespace Eznix86\PestPluginTestContainers\Container;

use Closure;
use Eznix86\PestPluginTestContainers\Container\PortMapping\FixedPortSequenceGenerator;
use Eznix86\PestPluginTestContainers\Container\Reuse\ReusableContainerResolver;
use Eznix86\PestPluginTestContainers\Container\Reuse\ReuseOptions;
use Eznix86\PestPluginTestContainers\Container\Reuse\WorkerTokenResolver;
use InvalidArgumentException;
use Testcontainers\Container\GenericContainer;
use Testcontainers\Container\HttpMethod;
use Testcontainers\ContainerClient\DockerContainerClient;
use Testcontainers\Wait\WaitForExec;
use Testcontainers\Wait\WaitForHttp;
use Testcontainers\Wait\WaitForLog;
use Throwable;

final readonly class ContainerBuilder
{
    private const string MANAGED_BY_LABEL_KEY = 'pest-plugin-testcontainers.managed';

    private const string MANAGED_BY_LABEL_VALUE = '1';

    private const int START_MAX_ATTEMPTS = 6;

    private const int START_RETRY_BASE_DELAY_MICROSECONDS = 500_000;

    private const int START_RETRY_MAX_DELAY_MICROSECONDS = 5_000_000;

    private GenericContainer $container;

    private ReuseOptions $reuseOptions;

    private ReusableContainerResolver $reusableContainerResolver;

    private WorkerTokenResolver $workerTokenResolver;

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
            ->withLabels([
                self::MANAGED_BY_LABEL_KEY => self::MANAGED_BY_LABEL_VALUE,
            ]);

        $this->reuseOptions = new ReuseOptions;
        $this->reusableContainerResolver = new ReusableContainerResolver;
        $this->workerTokenResolver = new WorkerTokenResolver;
        $this->registerContainer = Closure::fromCallable($registerContainer);
        $this->skipTest = Closure::fromCallable($skipTest);
    }

    /**
     * @param  list<int|string>|array<int|string, int|string>  $ports
     */
    public function ports(array $ports): self
    {
        if (array_is_list($ports)) {
            /** @var list<int|string> $containerPorts */
            $containerPorts = array_map($this->normalizeContainerPort(...), $ports);

            $this->container->withExposedPorts(...$containerPorts);

            return $this;
        }

        /** @var list<int|string> $containerPorts */
        $containerPorts = [];
        /** @var list<int> $hostPorts */
        $hostPorts = [];

        foreach ($ports as $containerPort => $hostPort) {
            $normalizedContainerPort = $this->normalizeContainerPort($containerPort);

            $containerPorts[] = $normalizedContainerPort;
            $hostPorts[] = $this->normalizeHostPort($hostPort, $normalizedContainerPort);
        }

        $this->configurePortMapping($containerPorts, $hostPorts);

        return $this;
    }

    /**
     * @param  list<int|string>  $containerPorts
     * @param  list<int>  $hostPorts
     */
    private function configurePortMapping(array $containerPorts, array $hostPorts): void
    {
        $this->container
            ->withExposedPorts(...$containerPorts)
            ->withPortGenerator(new FixedPortSequenceGenerator($hostPorts));
    }

    private function normalizeContainerPort(int|string $port): int|string
    {
        return is_numeric($port) ? (int) $port : $port;
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

    public function reuse(string $name, bool $perWorker = false): self
    {
        if ($name === '') {
            throw new InvalidArgumentException('Reuse container name cannot be empty.');
        }

        $this->reuseOptions->name = $name;
        $this->reuseOptions->perWorker = $perWorker;
        $this->container->withName($this->resolveReuseName());

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
            if ($this->reuseOptions->name !== null) {
                $reuseName = $this->resolveReuseName();
                $reusedContainer = $this->reusableContainerResolver->resolveRunning($reuseName);

                if ($reusedContainer instanceof StartedContainer) {
                    return ($this->registerContainer)($reusedContainer);
                }
            }

            $startedContainer = $this->startContainerWithRetry();

            if ($this->reuseOptions->name !== null) {
                $startedContainer->skipAutoCleanup();
            }

            return ($this->registerContainer)($startedContainer);
        } catch (Throwable $exception) {
            if ($this->reuseOptions->name !== null && $this->reusableContainerResolver->isNameConflict($exception)) {
                $reusedContainer = $this->reusableContainerResolver->waitUntilRunning($this->resolveReuseName());

                if ($reusedContainer instanceof StartedContainer) {
                    return ($this->registerContainer)($reusedContainer);
                }

                try {
                    $recreatedContainer = $this->startContainerWithRetry();
                    $recreatedContainer->skipAutoCleanup();

                    return ($this->registerContainer)($recreatedContainer);
                } catch (Throwable $recreateException) {
                    $exception = $recreateException;
                }
            }

            ($this->skipTest)('Container startup issue: '.$this->describeThrowable($exception));
        }
    }

    private function startContainerWithRetry(): StartedContainer
    {
        $lastException = null;

        for ($attempt = 0; $attempt < self::START_MAX_ATTEMPTS; $attempt++) {
            try {
                return new StartedContainer($this->container->start());
            } catch (Throwable $exception) {
                $this->cleanupFailedContainerAfterStartError();
                $lastException = $exception;

                if ($attempt < self::START_MAX_ATTEMPTS - 1) {
                    usleep($this->startRetryDelayForAttempt($attempt));
                }
            }
        }
        throw $lastException;
    }

    private function describeThrowable(Throwable $exception): string
    {
        $descriptions = [];

        for ($current = $exception; $current instanceof Throwable; $current = $current->getPrevious()) {
            $message = trim($current->getMessage());

            if (method_exists($current, 'getErrorResponse')) {
                $errorResponse = $current->getErrorResponse();

                if (is_object($errorResponse) && method_exists($errorResponse, 'getMessage')) {
                    $dockerMessage = $errorResponse->getMessage();

                    if (is_string($dockerMessage) && $dockerMessage !== '' && ! str_contains($message, $dockerMessage)) {
                        $message = trim($message.' | docker: '.$dockerMessage);
                    }
                }
            }

            if (method_exists($current, 'getResponse')) {
                $response = $current->getResponse();

                if (is_object($response) && method_exists($response, 'getStatusCode')) {
                    $statusCode = $response->getStatusCode();
                    $reason = method_exists($response, 'getReasonPhrase') ? $response->getReasonPhrase() : '';

                    if (is_int($statusCode)) {
                        $httpPart = sprintf('HTTP %d%s', $statusCode, is_string($reason) && $reason !== '' ? ' '.$reason : '');
                        $message = trim($message.' | '.$httpPart);
                    }
                }
            }

            $descriptions[] = sprintf('%s: %s', $current::class, $message === '' ? '(empty message)' : $message);
        }

        return implode(' <- ', $descriptions);
    }

    private function startRetryDelayForAttempt(int $attempt): int
    {
        $delay = self::START_RETRY_BASE_DELAY_MICROSECONDS * (1 << $attempt);

        return min($delay, self::START_RETRY_MAX_DELAY_MICROSECONDS);
    }

    private function cleanupFailedContainerAfterStartError(): void
    {
        try {
            $containerId = $this->container->getId();
        } catch (Throwable) {
            return;
        }

        if ($containerId === '') {
            return;
        }

        try {
            DockerContainerClient::getDockerClient()->containerDelete($containerId, ['force' => true]);
        } catch (Throwable) {
            // Ignore cleanup failures and keep retrying.
        }
    }

    public function configuredReuseName(): ?string
    {
        if ($this->reuseOptions->name === null) {
            return null;
        }

        return $this->resolveReuseName();
    }

    private function resolveReuseName(): string
    {
        $name = $this->reuseOptions->name;

        if (! is_string($name) || $name === '') {
            throw new InvalidArgumentException('Reuse container name cannot be empty.');
        }

        if (! $this->reuseOptions->perWorker) {
            return $name;
        }

        $workerToken = $this->resolveWorkerToken();

        return $workerToken === null ? $name : sprintf('%s-worker-%s', $name, $workerToken);
    }

    private function resolveWorkerToken(): ?string
    {
        return $this->workerTokenResolver->resolve();
    }
}
