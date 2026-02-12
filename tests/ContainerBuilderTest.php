<?php

declare(strict_types=1);

use Eznix86\PestPluginTestContainers\Tests\TestCase;
use Testcontainers\ContainerClient\DockerContainerClient;

it('starts a container and runs expectation helpers', function () {
    /** @var TestCase $testCase */
    $testCase = $this;

    $container = $testCase->container('alpine:3.20')
        ->command(['sh', '-lc', 'while true; do sleep 1; done'])
        ->start();

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

it('rejects mapped host ports with protocol suffixes', function () {
    /** @var TestCase $testCase */
    $testCase = $this;

    expect(fn () => $testCase->container('nginx:alpine')
        ->ports(['8080/udp' => '8080/udp']))
        ->toThrow(InvalidArgumentException::class, 'Host port must be an integer');
});

it('mounts a host file as a container volume', function () {
    /** @var TestCase $testCase */
    $testCase = $this;

    if (getenv('ACT') !== false) {
        $this->markTestSkipped('Host bind mount paths are not portable in act local runner setup.');
    }

    $fixturePath = tempnam(sys_get_temp_dir(), 'testcontainers-volume-');

    if (! is_string($fixturePath)) {
        throw new \RuntimeException('Unable to create temporary fixture file.');
    }

    file_put_contents($fixturePath, 'hello-from-host');

    $container = $testCase->container('alpine:3.20')
        ->command(['sh', '-lc', 'while true; do sleep 1; done'])
        ->volume($fixturePath, '/tmp/fixture.txt')
        ->start();

    $container
        ->expect('cat /tmp/fixture.txt')
        ->toRunSuccessfully()
        ->toContain('hello-from-host');

    @unlink($fixturePath);
});

it('returns raw command output and raw logs when needed', function () {
    /** @var TestCase $testCase */
    $testCase = $this;

    $container = $testCase->container('alpine:3.20')
        ->command(['sh', '-lc', 'while true; do sleep 1; done'])
        ->start();

    $rawResult = $container->execRaw(['sh', '-lc', "printf 'hello\\nraw'"]);

    expect($rawResult->output)
        ->toContain("\n")
        ->and($rawResult->successful())->toBeTrue();

    $container->exec(['sh', '-lc', 'echo raw-log-check > /proc/1/fd/1']);

    expect($container->rawLogs())->toContain('raw-log-check');
});

it('reuses the same named container instance', function () {
    /** @var TestCase $testCase */
    $testCase = $this;

    $reuseName = 'pest-plugin-reuse-'.str_replace('.', '-', uniqid('', true));

    $firstContainer = $testCase->container('alpine:3.20')
        ->reuse($reuseName)
        ->command(['sh', '-lc', 'while true; do sleep 1; done'])
        ->start();

    $secondContainer = $testCase->container('alpine:3.20')
        ->reuse($reuseName)
        ->command(['sh', '-lc', 'while true; do sleep 1; done'])
        ->start();

    expect($firstContainer->raw()->getId())
        ->toBe($secondContainer->raw()->getId());

    $firstContainer->stop();
});

it('rejects empty reuse names', function () {
    /** @var TestCase $testCase */
    $testCase = $this;

    expect(fn () => $testCase->container('alpine:3.20')->reuse(''))
        ->toThrow(InvalidArgumentException::class, 'Reuse container name cannot be empty.');
});

it('scopes reusable containers per worker token when requested', function () {
    /** @var TestCase $testCase */
    $testCase = $this;

    $reuseName = 'pest-plugin-reuse-worker-'.str_replace('.', '-', uniqid('', true));
    $existingToken = getenv('TEST_TOKEN');
    $existingServerToken = $_SERVER['TEST_TOKEN'] ?? null;
    $existingEnvToken = $_ENV['TEST_TOKEN'] ?? null;
    $alphaContainer = null;
    $betaContainer = null;

    try {
        putenv('TEST_TOKEN=alpha');
        $_SERVER['TEST_TOKEN'] = 'alpha';
        $_ENV['TEST_TOKEN'] = 'alpha';

        $alphaContainer = $testCase->container('alpine:3.20')
            ->reuse($reuseName, perWorker: true)
            ->command(['sh', '-lc', 'while true; do sleep 1; done'])
            ->start();

        putenv('TEST_TOKEN=beta');
        $_SERVER['TEST_TOKEN'] = 'beta';
        $_ENV['TEST_TOKEN'] = 'beta';

        $betaContainer = $testCase->container('alpine:3.20')
            ->reuse($reuseName, perWorker: true)
            ->command(['sh', '-lc', 'while true; do sleep 1; done'])
            ->start();

        expect($alphaContainer->raw()->getName())
            ->toBe($reuseName.'-worker-alpha');

        expect($betaContainer->raw()->getName())
            ->toBe($reuseName.'-worker-beta');

        expect($alphaContainer->raw()->getId())
            ->not->toBe($betaContainer->raw()->getId());
    } finally {
        if ($alphaContainer !== null) {
            $alphaContainer->stop();
        }

        if ($betaContainer !== null) {
            $betaContainer->stop();
        }

        if (is_string($existingToken) && $existingToken !== '') {
            putenv('TEST_TOKEN='.$existingToken);
        } else {
            putenv('TEST_TOKEN');
        }

        if (is_string($existingServerToken)) {
            $_SERVER['TEST_TOKEN'] = $existingServerToken;
        } else {
            unset($_SERVER['TEST_TOKEN']);
        }

        if (is_string($existingEnvToken)) {
            $_ENV['TEST_TOKEN'] = $existingEnvToken;
        } else {
            unset($_ENV['TEST_TOKEN']);
        }
    }
});

it('reuses and restarts named container when it exists but is stopped', function () {
    /** @var TestCase $testCase */
    $testCase = $this;

    $reuseName = 'pest-plugin-reuse-stopped-'.str_replace('.', '-', uniqid('', true));
    $containerId = null;

    try {
        $firstContainer = $testCase->container('alpine:3.20')
            ->reuse($reuseName)
            ->command(['sh', '-lc', 'while true; do sleep 1; done'])
            ->start();

        $containerId = $firstContainer->raw()->getId();
        $firstContainer->raw()->getClient()->containerStop($containerId, ['t' => 0]);

        $secondContainer = $testCase->container('alpine:3.20')
            ->reuse($reuseName)
            ->command(['sh', '-lc', 'while true; do sleep 1; done'])
            ->start();

        expect($containerId)->toBeString()
            ->and($secondContainer->raw()->getId())->toBe($containerId);
    } finally {
        if (is_string($containerId) && $containerId !== '') {
            DockerContainerClient::getDockerClient()->containerDelete($containerId, ['force' => true]);
        }
    }
});
