<?php

declare(strict_types=1);

use Docker\Docker;
use Eznix86\PestPluginTestContainers\Container\ContainerBuilder;
use Eznix86\PestPluginTestContainers\Container\WaitForPort;
use Testcontainers\Container\StartedTestContainer;
use Testcontainers\Container\StoppedTestContainer;
use Testcontainers\ContainerClient\DockerContainerClient;

final class ManualStartedTestContainer implements StartedTestContainer
{
    public int $calls = 0;

    public function __construct(
        private readonly string $host,
        private readonly int $openPort,
        private readonly int $transientFailures = 0,
        private readonly ?\Throwable $fatal = null,
        private readonly string $id = 'manual-container',
    ) {}

    public function exec(array $command): string
    {
        throw new \RuntimeException('Not used in this test.');
    }

    public function getBoundPorts(): iterable
    {
        return [];
    }

    public function getClient(): Docker
    {
        return DockerContainerClient::getDockerClient();
    }

    public function getFirstMappedPort(): int
    {
        return $this->getMappedPort(0);
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getIpAddress(string $networkName): string
    {
        throw new \RuntimeException('Not used in this test.');
    }

    public function getLabels(): array
    {
        return [];
    }

    public function logs(): string
    {
        return '';
    }

    public function getLastExecId(): ?string
    {
        return null;
    }

    public function getMappedPort(int $port): int
    {
        $this->calls++;

        if ($this->fatal instanceof \Throwable) {
            throw $this->fatal;
        }

        if ($this->calls <= $this->transientFailures) {
            throw new \Exception('foreach() argument must be of type array|object, null given');
        }

        return $this->openPort;
    }

    public function getName(): string
    {
        return 'manual';
    }

    public function getNetworkId(string $networkName): string
    {
        throw new \RuntimeException('Not used in this test.');
    }

    public function getNetworkNames(): array
    {
        return [];
    }

    public function restart(): StartedTestContainer
    {
        return $this;
    }

    public function stop(): StoppedTestContainer
    {
        throw new \RuntimeException('Not used in this test.');
    }
}

it('should tolerate transient docker inspect race errors while waiting for a port', function () {
    $server = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
    expect($server)->not->toBeFalse();

    $openPort = portFromSocketAddress((string) stream_socket_get_name($server, false));

    $container = new ManualStartedTestContainer('127.0.0.1', $openPort, transientFailures: 2);

    (new WaitForPort(port: 8080, timeout: 600, pollInterval: 10))->wait($container);

    expect($container->calls)->toBeGreaterThan(2);

    fclose($server);
});

it('should rethrow non-transient throwables while waiting for a port', function () {
    $container = new ManualStartedTestContainer(
        '127.0.0.1',
        65535,
        fatal: new \Exception('fatal wait failure')
    );

    expect(fn () => (new WaitForPort(port: 80, timeout: 100, pollInterval: 10))->wait($container))
        ->toThrow(\Exception::class, 'fatal wait failure');
});

it('should wrap container start failures for invalid images', function () {
    $builder = new ContainerBuilder(
        'nonexistent-image-for-coverage:latest',
        fn ($container) => $container,
        function (string $message): never {
            throw new \RuntimeException($message);
        },
    );

    expect(fn () => $builder->start())
        ->toThrow(\RuntimeException::class, 'Container startup issue:');
});

it('should expose configured reuse names with and without worker tokens', function () {
    $builder = new ContainerBuilder(
        'alpine:3.20',
        fn ($container) => $container,
        function (string $message): never {
            throw new \RuntimeException($message);
        },
    );

    expect($builder->configuredReuseName())->toBeNull();

    $builder->reuse('plain-reuse-name', perWorker: false);
    expect($builder->configuredReuseName())->toBe('plain-reuse-name');

    withTemporaryEnvironment(['TEST_TOKEN' => null], function () use ($builder): void {
        $builder->reuse('worker-reuse-name', perWorker: true);
        expect($builder->configuredReuseName())->toBe('worker-reuse-name');
    });
});

it('should swallow stop failures during teardown when containers are externally deleted', function () {
    $container = startIdleContainer($this);

    $containerId = $container->raw()->getId();
    DockerContainerClient::getDockerClient()->containerDelete($containerId, ['force' => true]);

    expect(true)->toBeTrue();
});

it('should support ipv6 host formatting while waiting for a port', function () {
    $server = @stream_socket_server('tcp://[::1]:0', $errorCode, $errorMessage);

    if ($server === false) {
        $this->markTestSkipped('IPv6 loopback is unavailable in this environment.');
    }

    $openPort = portFromSocketAddress((string) stream_socket_get_name($server, false));
    $container = new ManualStartedTestContainer('::1', $openPort);

    (new WaitForPort(port: 8080, timeout: 400, pollInterval: 10))->wait($container);

    fclose($server);

    expect($container->calls)->toBeGreaterThanOrEqual(1);
});
