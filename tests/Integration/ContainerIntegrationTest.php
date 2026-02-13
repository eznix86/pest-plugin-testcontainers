<?php

declare(strict_types=1);

use Eznix86\PestPluginTestContainers\ConfigInjectors\QueueConfigInjector;
use Eznix86\PestPluginTestContainers\Container\PortMapping\ProtocolAwareRandomUniquePortAllocator;
use Eznix86\PestPluginTestContainers\Container\StartedContainer;

use function Eznix86\PestPluginTestContainers\postgres;

it('should support specialized builder operation chaining without starting', function () {
    $fixturePath = tempnam(sys_get_temp_dir(), 'testcontainers-specialized-');

    if (! is_string($fixturePath)) {
        throw new RuntimeException('Unable to create temporary fixture file.');
    }

    file_put_contents($fixturePath, 'specialized-builder');

    $builder = postgres('16')
        ->ports([5432])
        ->env(['TZ' => 'UTC'])
        ->labels(['suite' => 'heavy-coverage'])
        ->volume($fixturePath, '/tmp/fixture.txt')
        ->reuse('specialized-heavy-'.str_replace('.', '-', uniqid('', true)))
        ->command(['postgres'])
        ->healthcheck(['CMD-SHELL', 'pg_isready -U postgres'])
        ->waitForLog('database system is ready to accept connections')
        ->waitForHttp(path: '/', port: 5432, timeoutSeconds: 1)
        ->waitForPort(5432)
        ->waitForCommand(['sh', '-lc', 'test -d /var/lib/postgresql/data'])
        ->asDatabase('heavy_ops_db');

    expect($builder)->toBeInstanceOf(\Eznix86\PestPluginTestContainers\Builders\PostgresContainerBuilder::class);

    @unlink($fixturePath);
});

it('should cover started container connection-name lifecycle and stop callback', function () {
    /** @var StartedContainer $container */
    $container = startIdleContainer($this);

    expect(fn () => $container->resolvedConnectionName())
        ->toThrow(RuntimeException::class, 'Connection name was not initialized on the started container.');

    $stops = 0;

    $container
        ->withConnectionName('heavy_connection')
        ->onStop(function () use (&$stops): void {
            $stops++;
        });

    expect($container->connectionName())->toBe('heavy_connection')
        ->and($container->resolvedConnectionName())->toBe('heavy_connection');

    $container->stop();
    $container->stop();

    expect($stops)->toBe(1);
});

it('should inject queue config with custom, redis and database driver variants', function () {
    $nginx = $this->container('nginx:alpine')
        ->ports([80 => '28080'])
        ->waitForPort(80)
        ->start();

    QueueConfigInjector::inject($nginx, 'beanstalkd', 80, 'queue_custom');

    expect(config('queue.default'))->toBe('queue_custom')
        ->and(config('queue.connections.queue_custom.driver'))->toBe('beanstalkd')
        ->and(config('queue.connections.queue_custom.host'))->toBe($nginx->host())
        ->and(config('queue.connections.queue_custom.port'))->toBe(28080);

    $redis = $this->container('redis:alpine')
        ->ports([6379])
        ->waitForPort(6379)
        ->start();

    QueueConfigInjector::inject($redis, 'redis', 6379);

    $redisConnection = (string) config('queue.default');

    expect($redisConnection)->toStartWith('testcontainer_')
        ->and(config("queue.connections.{$redisConnection}.driver"))->toBe('redis')
        ->and(config("queue.connections.{$redisConnection}.connection"))->toBe($redisConnection)
        ->and(config("database.redis.{$redisConnection}.host"))->toBe($redis->host())
        ->and(config("database.redis.{$redisConnection}.port"))->toBe($redis->mappedPort(6379));

    QueueConfigInjector::inject($redis, 'database', 6379);

    $databaseConnection = (string) config('queue.default');

    expect($databaseConnection)->toStartWith('testcontainer_')
        ->and(config("queue.connections.{$databaseConnection}.driver"))->toBe('database')
        ->and(config("queue.connections.{$databaseConnection}.table"))->toBe('jobs')
        ->and(config("queue.connections.{$databaseConnection}.connection"))->toBe($databaseConnection)
        ->and(config("database.redis.{$databaseConnection}"))->toBeNull();
});

it('should allocate protocol-aware random unique ports for tcp and udp', function () {
    $allocator = new ProtocolAwareRandomUniquePortAllocator;

    $tcpPort = $allocator->allocateForContainerPort(8080);
    $udpPort = $allocator->allocateForContainerPort('5353/udp');

    expect($tcpPort)->toBeInt()->toBeGreaterThanOrEqual(10000)->toBeLessThanOrEqual(65535)
        ->and($udpPort)->toBeInt()->toBeGreaterThanOrEqual(10000)->toBeLessThanOrEqual(65535)
        ->and($udpPort)->not->toBe($tcpPort);
});

it('should use worker-based port allocation when parallel mode is enabled', function () {
    withEnvironmentSnapshot(['PEST_PARALLEL', 'TEST_TOKEN'], function (): void {
        setEnvironmentValue('PEST_PARALLEL', '1');
        setEnvironmentValue('TEST_TOKEN', '4');

        $container = $this->container('nginx:alpine')
            ->ports([80])
            ->waitForPort(80)
            ->start();

        expect($container->mappedPort(80))->toBe(49552);
    });
});

it('should accept https and insecure flags in waitForHttp builder configuration', function () {
    $builder = $this->container('nginx:alpine')
        ->ports([80])
        ->waitForHttp(path: '/', port: 80, https: true, allowInsecure: true, timeoutSeconds: 1);

    expect($builder)->toBeInstanceOf(\Eznix86\PestPluginTestContainers\Container\ContainerBuilder::class);
});
