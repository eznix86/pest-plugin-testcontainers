<?php

declare(strict_types=1);

use Eznix86\PestPluginTestContainers\Tests\TestCase;

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
