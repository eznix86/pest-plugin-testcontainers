<?php

declare(strict_types=1);

use Eznix86\PestPluginTestContainers\Container\Reuse\ReusableContainerResolver;
use Testcontainers\ContainerClient\DockerContainerClient;

it('should detect wrapped conflict errors by traversing previous exceptions', function () {
    $resolver = new ReusableContainerResolver;
    $previous = new RuntimeException('Conflict: container name is already in use by container "abc123"');
    $exception = new RuntimeException('Container start failed', previous: $previous);

    expect($resolver->isNameConflict($exception))->toBeTrue();
});

it('should not classify unrelated errors as name conflicts', function () {
    $resolver = new ReusableContainerResolver;
    $exception = new RuntimeException('Bad parameter: failed to parse port bindings');

    expect($resolver->isNameConflict($exception))->toBeFalse();
});

it('should resolve, restart, and wait for named reusable containers', function () {
    $resolver = new ReusableContainerResolver;
    $reuseName = 'resolver-heavy-'.str_replace('.', '-', uniqid('', true));

    $container = $this->container('alpine:3.20')
        ->reuse($reuseName)
        ->command(idleContainerCommand())
        ->start();

    $containerId = $container->raw()->getId();

    try {
        $running = $resolver->resolveRunning($reuseName);

        expect($running)->not->toBeNull()
            ->and($running?->raw()->getId())->toBe($containerId)
            ->and($running?->shouldSkipAutoCleanup())->toBeTrue();

        $container->raw()->getClient()->containerStop($containerId, ['t' => 0]);

        $restarted = $resolver->resolveRunningOrStart($reuseName);

        expect($restarted)->not->toBeNull()
            ->and($restarted?->raw()->getId())->toBe($containerId)
            ->and($resolver->waitUntilRunning($reuseName)?->raw()->getId())->toBe($containerId)
            ->and($resolver->resolveRunning('does-not-exist-'.uniqid()))->toBeNull();
    } finally {
        DockerContainerClient::getDockerClient()->containerDelete($containerId, ['force' => true]);
    }
});
