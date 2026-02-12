<?php

declare(strict_types=1);

use Eznix86\PestPluginTestContainers\Container\PortMapping\FixedPortSequenceGenerator;

it('generates ports in a stable cyclic sequence', function () {
    $generator = new FixedPortSequenceGenerator([41001, 41002]);

    expect($generator->generatePort())->toBe(41001)
        ->and($generator->generatePort())->toBe(41002)
        ->and($generator->generatePort())->toBe(41001)
        ->and($generator->generatePort())->toBe(41002);
});

it('throws when configured with no host ports', function () {
    $generator = new FixedPortSequenceGenerator([]);

    expect(fn () => $generator->generatePort())
        ->toThrow(RuntimeException::class, 'Fixed host port mapping requires at least one host port.');
});
