<?php

declare(strict_types=1);

use Eznix86\PestPluginTestContainers\Container\StartedContainer;
use Eznix86\PestPluginTestContainers\Tests\TestCase;

it('waits for command success before continuing', function () {
    /** @var TestCase $testCase */
    $testCase = $this;

    $container = $testCase->container('alpine:3.20')
        ->command(['sh', '-lc', 'while true; do sleep 1; done'])
        ->waitForCommand(['sh', '-lc', 'test -d /bin'])
        ->start();

    $container
        ->expect('echo ready')
        ->toRunSuccessfully()
        ->toContain('ready');
});

it('waits for log output before continuing', function () {
    /** @var TestCase $testCase */
    $testCase = $this;

    $container = $testCase->container('alpine:3.20')
        ->command(['sh', '-lc', 'echo plugin-ready && while true; do sleep 1; done'])
        ->waitForLog('plugin-ready')
        ->start();

    $container->expect('plugin-ready')->toBeInLogs();
});

it('waits for mapped port before continuing', function () {
    /** @var TestCase $testCase */
    $testCase = $this;

    $container = $testCase->container('nginx:alpine')
        ->ports([80])
        ->waitForPort(80)
        ->start();

    expect($container)->toBeInstanceOf(StartedContainer::class)
        ->and($container->getGeneratedPortFor(80))->toBeInt();
});

it('waits for http endpoint before continuing', function () {
    /** @var TestCase $testCase */
    $testCase = $this;

    $container = $testCase->container('nginx:alpine')
        ->ports([80])
        ->waitForHttp(path: '/', port: 80)
        ->start();

    expect($container)->toBeInstanceOf(StartedContainer::class)
        ->and($container->getGeneratedPortFor(80))->toBeInt();
});
