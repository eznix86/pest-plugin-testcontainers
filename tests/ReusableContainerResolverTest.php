<?php

declare(strict_types=1);

use Eznix86\PestPluginTestContainers\Container\Reuse\ReusableContainerResolver;

it('detects wrapped conflict errors by traversing previous exceptions', function () {
    $resolver = new ReusableContainerResolver;
    $previous = new RuntimeException('Conflict: container name is already in use by container "abc123"');
    $exception = new RuntimeException('Container start failed', previous: $previous);

    expect($resolver->isNameConflict($exception))->toBeTrue();
});

it('does not classify unrelated errors as name conflicts', function () {
    $resolver = new ReusableContainerResolver;
    $exception = new RuntimeException('Bad parameter: failed to parse port bindings');

    expect($resolver->isNameConflict($exception))->toBeFalse();
});
