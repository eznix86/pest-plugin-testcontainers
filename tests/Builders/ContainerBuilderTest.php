<?php

declare(strict_types=1);

use Eznix86\PestPluginTestContainers\Tests\TestCase;
use Testcontainers\ContainerClient\DockerContainerClient;

it('should start a container and run expectation helpers', function () {
    /** @var TestCase $testCase */
    $testCase = $this;

    $container = startIdleContainer($testCase);

    $container->expect('/bin')->toExist()->toBeDirectory()->toBeReadable();

    $container
        ->expect('echo hello-container')
        ->toRunSuccessfully()
        ->toContain('hello-container');

    $container
        ->expect('exit 7')
        ->toFail();

    $container
        ->expect('touch /tmp/container-dsl-test && rm /tmp/container-dsl-test')
        ->toRunSuccessfully();

    $container->expect('/tmp/container-dsl-test')->toNotExist();

    $container
        ->expect('echo hello-container > /proc/1/fd/1')
        ->toRunSuccessfully();

    $container->expect('hello-container')->toBeInLogs();
});

it('should reject mapped host ports with protocol suffixes', function () {
    /** @var TestCase $testCase */
    $testCase = $this;

    expect(fn () => $testCase->container('nginx:alpine')
        ->ports(['8080/udp' => '8080/udp']))
        ->toThrow(InvalidArgumentException::class, 'Host port must be an integer');
});

it('should mount a host file as a container volume', function () {
    /** @var TestCase $testCase */
    $testCase = $this;

    if (getenv('ACT') !== false) {
        $this->markTestSkipped('Host bind mount paths are not portable in act local runner setup.');
    }

    $fixturePath = tempnam(sys_get_temp_dir(), 'testcontainers-volume-');

    if (! is_string($fixturePath)) {
        throw new RuntimeException('Unable to create temporary fixture file.');
    }

    file_put_contents($fixturePath, 'hello-from-host');

    $container = startIdleContainer($testCase, configure: static function ($builder) use ($fixturePath): void {
        $builder->volume($fixturePath, '/tmp/fixture.txt');
    });

    $container
        ->expect('cat /tmp/fixture.txt')
        ->toRunSuccessfully()
        ->toContain('hello-from-host');

    @unlink($fixturePath);
});

it('should return raw command output and raw logs when needed', function () {
    /** @var TestCase $testCase */
    $testCase = $this;

    $container = startIdleContainer($testCase);

    $rawResult = $container->execRaw(['sh', '-lc', "printf 'hello\\nraw'"]);

    expect($rawResult->output)
        ->toContain("\n")
        ->and($rawResult->successful())->toBeTrue();

    $container->expect(['sh', '-lc', 'echo array-subject'])
        ->toRunSuccessfully()
        ->toContain('array-subject');

    $container->exec(['sh', '-lc', 'echo raw-log-check > /proc/1/fd/1']);

    expect($container->rawLogs())->toContain('raw-log-check');
});

it('should reuse the same named container instance', function () {
    /** @var TestCase $testCase */
    $testCase = $this;

    $reuseName = 'pest-plugin-reuse-'.str_replace('.', '-', uniqid('', true));

    $firstContainer = startIdleContainer($testCase, configure: static function ($builder) use ($reuseName): void {
        $builder->reuse($reuseName);
    });

    $secondContainer = startIdleContainer($testCase, configure: static function ($builder) use ($reuseName): void {
        $builder->reuse($reuseName);
    });

    expect($firstContainer->raw()->getId())
        ->toBe($secondContainer->raw()->getId());

    $firstContainer->stop();
});

it('should reject empty reuse names', function () {
    /** @var TestCase $testCase */
    $testCase = $this;

    expect(fn () => $testCase->container('alpine:3.20')->reuse(''))
        ->toThrow(InvalidArgumentException::class, 'Reuse container name cannot be empty.');
});

it('should scope reusable containers per worker token when requested', function () {
    /** @var TestCase $testCase */
    $testCase = $this;

    $reuseName = 'pest-plugin-reuse-worker-'.str_replace('.', '-', uniqid('', true));
    $alphaContainer = null;
    $betaContainer = null;

    try {
        withEnvironmentSnapshot(['TEST_TOKEN'], function () use ($testCase, $reuseName, &$alphaContainer, &$betaContainer): void {
            setEnvironmentValue('TEST_TOKEN', 'alpha');

            $alphaContainer = startIdleContainer($testCase, configure: static function ($builder) use ($reuseName): void {
                $builder->reuse($reuseName, perWorker: true);
            });

            setEnvironmentValue('TEST_TOKEN', 'beta');

            $betaContainer = startIdleContainer($testCase, configure: static function ($builder) use ($reuseName): void {
                $builder->reuse($reuseName, perWorker: true);
            });

            expect($alphaContainer->raw()->getName())
                ->toBe($reuseName.'-worker-alpha');

            expect($betaContainer->raw()->getName())
                ->toBe($reuseName.'-worker-beta');

            expect($alphaContainer->raw()->getId())
                ->not->toBe($betaContainer->raw()->getId());
        });
    } finally {
        $alphaContainer?->stop();
        $betaContainer?->stop();
    }
});

it('should reuse and restart named container when it exists but is stopped', function () {
    /** @var TestCase $testCase */
    $testCase = $this;

    $reuseName = 'pest-plugin-reuse-stopped-'.str_replace('.', '-', uniqid('', true));
    $containerId = null;

    try {
        $firstContainer = startIdleContainer($testCase, configure: static function ($builder) use ($reuseName): void {
            $builder->reuse($reuseName);
        });

        $containerId = $firstContainer->raw()->getId();
        $firstContainer->raw()->getClient()->containerStop($containerId, ['t' => 0]);

        $secondContainer = startIdleContainer($testCase, configure: static function ($builder) use ($reuseName): void {
            $builder->reuse($reuseName);
        });

        expect($containerId)->toBeString()
            ->and($secondContainer->raw()->getId())->toBe($containerId);
    } finally {
        if (is_string($containerId) && $containerId !== '') {
            DockerContainerClient::getDockerClient()->containerDelete($containerId, ['force' => true]);
        }
    }
});
